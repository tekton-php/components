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

    protected function createComponent($resources)
    {
        $class = $this->componentClass;
        return new $class($resources);
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

    public function register($name, $component = null)
    {
        // Register by list of components
        if (is_array($name)) {
            $result = true;
            $basePath = $component;

            foreach ($name as $name => $component) {
                // If non-associative array we register component by path
                // this requires a compiler to have been set
                if (is_int($name)) {
                    $current = $this->register($component, $basePath);
                }
                else {
                    $current = $this->register($name, $component);
                }

                $result = (! $current) ? false : $result;
            }

            return $result;
        }

        // Register by name and component instance
        if (! isset($this->components[$name]) && $component instanceof ComponentInterface) {
            $component->setName($name);
            $this->components[$name] = $component;

            return true;
        }
        // Register by name and component path or component path and base path
        elseif (is_string($name) && (is_null($component) || is_string($component))) {
            if (! $this->compiler) {
                throw new Exception('No compiler has been set in '.self::class);
            }

            // Component path and base path
            if (file_exists($name)) {
                $result = $this->compiler->compile($name, $component);
                $keys = array_keys($result);
                $name = reset($keys);
                $component = $this->createComponent($result[$name]);

                return $this->register($name, $component);
            }
            // Name and component path
            else {
                $result = $this->compiler->compile($component);
                $resources = reset($result);
                $component = $this->createComponent($component);

                return $this->register($name, $component);
            }
        }
        // Register component by name and resources array
        elseif (is_string($name) && is_array($component)) {
            $component = $this->createComponent($component);
            return $this->register($name, $component);
        }

        return false;
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
