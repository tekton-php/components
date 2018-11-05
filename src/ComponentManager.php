<?php namespace Tekton\Components;

use Tekton\Components\Component;
use Tekton\Components\ComponentInfo;
use Tekton\Components\ComponentInterface;
use Exception;

class ComponentManager
{
    protected $compiler;
    protected $components = [];
    protected $included = [];
    protected $instances = [];
    protected $componentClass;

    public function __construct($compiler = null, $componentClass = null)
    {
        $this->compiler = $compiler;

        if (is_string($componentClass) && ! empty($componentClass)) {
            $this->setComponentClass($componentClass);
        }
        else {
            $this->setComponentClass(Component::class);
        }
    }

    public function setComponentClass(string $class)
    {
        $this->componentClass = $class;
    }

    public function getComponentClass()
    {
        return $this->componentClass;
    }

    protected function createComponent($name, $resources)
    {
        $class = $this->componentClass;
        $component = new $class($resources);

        if (is_string($name)) {
            $component->setName($name);
        }

        return $component;
    }

    public function find($path, $typeMap = null, $register = false)
    {
        if (! is_array($typeMap)) {
            $typeMap = [
                'template' => ['html', 'php'],
                'styles' => 'css',
                'scripts' => 'js',
            ];
        }

        $files = [];
        $locations = (! is_array($path)) ? [$path] : $path;

        // Process an base directories/path
        foreach (array_unique($locations) as $location) {
            $base = $location;
            $files[$base] = [];

            if (is_dir($location)) {
                // Error here, why don't we find what we need?'
                foreach (ls_files($location) as $file) {
                    $files[$base][] = $file;
                }
            }
            else {
                $files[$base][] = $file;
            }
        }

        // By here we should have all files found in the directory
        $components = [];

        foreach ($files as $base => $baseFiles) {
            foreach ($baseFiles as $file) {
                $info = new ComponentInfo($file, $base);

                if (! isset($components[$info->id])) {
                    $resource = $this->assembleResource($info, $typeMap);
                    $components[$info->id] = $resource;
                }
            }
        }

        // If asked to register directly
        if ($register) {
            return $this->register($components);
        }

        return $components;
    }

    protected function assembleResource($info, $typeMap)
    {
        $parts = [];

        foreach ($typeMap as $type => $format) {
            // Support multiple types with pipe character
            if (is_array($format)) {
                foreach ($format as $optionalFormat) {
                    $parts[$type] = (file_exists($file = $info->partial.'.'.$optionalFormat)) ? $file : null;
                }
            }
            else {
                $parts[$type] = (file_exists($file = $info->partial.'.'.$format)) ? $file : null;
            }
        }

        // Filter out not found resources
        return array_filter($parts);
    }

    public function all()
    {
        return $this->components;
    }

    public function included()
    {
        return $this->included;
    }

    public function instances()
    {
        return $this->instances;
    }

    public function compile($path, $basePath = null)
    {
        if (! $this->compiler) {
            throw new Exception('No compiler has been set in '.self::class);
        }

        return $this->compiler->compile($path, $basePath);
    }

    public function register($primary, $secondary = null)
    {
        if (is_string($primary)) {
            // name + component
            if (! isset($this->components[$primary]) && $secondary instanceof ComponentInterface) {
                $component->setName($primary);
                $this->components[$primary] = $secondary;
            }
            // single file component path + base path
            elseif (file_exists($primary)) {
                foreach ($this->compile($primary, $secondary) as $name => $resources) {
                    $this->components[$name] = $this->createComponent($name, $resources);
                }
            }
            // Register component by name and resources array
            elseif (is_array($secondary)) {
                $this->components[$primary] = $this->createComponent($primary, $secondary);
            }
            else {
                throw new Exception('Invalid argument for '.self::class.'::'.__FUNCTION__);
            }
        }
        elseif (is_array($primary)) {
            $component = reset($primary);

            // name => component array
            if ($component instanceof Component) {
                foreach ($primary as $name => $component) {
                    $this->components[$name] = $component;
                }
            }
            // name => resources array
            elseif (is_array($component)) {
                foreach ($primary as $name => $resources) {
                    $this->components[$name] = $this->createComponent($name, $resources);
                }
            }
            // single file component paths array + base path
            elseif (is_string($component)) {
                foreach ($this->compile($primary, $secondary) as $name => $resources) {
                    $this->components[$name] = $this->createComponent($name, $resources);
                }
            }
            else {
                throw new Exception('Invalid argument for '.self::class.'::'.__FUNCTION__);
            }
        }
        else {
            throw new Exception('Invalid argument for '.self::class.'::'.__FUNCTION__);
        }

        return true;
    }

    public function include($name, $data = [])
    {
        // Provided component
        if ($name instanceof ComponentInterface) {
            $component = $name;
            $name = $component->getName();
        }
        // Provided component matching name
        elseif (isset($this->components[$name]) && $data instanceof ComponentInterface) {
            if ($component->getName() != $name) {
                throw new Exception("Component doesn't match type: ".$name);
            }

            $component = $data;
            $data = [];
        }
        // Find registered component by name
        elseif (isset($this->components[$name])) {
            $component = $this->components[$name];
        }
        else {
            throw new Exception("Trying to include unregistered component: ".$name);
        }

        // Make sure that the component is added to the included list
        if (! isset($this->included[$name])) {
            $this->included[$name] = $component;
        }

        // Include component
        if ($component) {
            if (! isset($this->instances[$name])) {
                $this->instances[$name] = [];
            }

            $index = count($this->instances[$name]);
            $id = $name.'-'.$index;

            $component = clone $component;
            $component->setIndex($index);
            $component->setId($id);
            $this->instances[$name][$id] = $component;

            return $component->render($data);
        }

        return '';
    }

    public function get($name)
    {
        return isset($this->components[$name]) ? $this->components[$name] : null;
    }

    public function resources($type, $flatten = false, $filter = true)
    {
        $resources = [];

        foreach ($this->components as $name => $component) {
            $resource[$name] = $component->get($type);

            if (! is_null($resource)) {
                if (is_array($resource)) {
                    $resources = array_merge($resources, $resource);
                }
                else {
                    $resources[] = $resource;
                }
            }
        }

        $resources = ($filter) ? array_filter($resources) : $resources;
        $resources = ($flatten) ? array_flatten($resources) : $resources;
        return $resources;
    }

    public function includedResources($type, $flatten = false, $filter = true)
    {
        $resources = [];

        foreach ($this->included as $name => $component) {
            $resource[$name] = $component->get($type);

            if (! is_null($resource)) {
                if (is_array($resource)) {
                    $resources = array_merge($resources, $resource);
                }
                else {
                    $resources[] = $resource;
                }
            }
        }

        $resources = ($filter) ? array_filter($resources) : $resources;
        $resources = ($flatten) ? array_flatten($resources) : $resources;
        return $resources;
    }
}
