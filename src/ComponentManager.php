<?php namespace Tekton\Components;

use Tekton\Components\Component;
use Tekton\Components\Contracts\Component as ComponentContract;
use Exception;

class ComponentManager
{
    protected $app;
    protected $components = [];
    protected $typeMap = [];
    protected $included = [];
    protected $instances = [];

    public function __construct($app, $typeMap = null)
    {
        $this->app = $app;
        $this->typeMap = $typeMap ?? [
            'template' => 'php',
            'styles' => 'css',
            'scripts' => 'js',
        ];
    }

    public function find($path)
    {
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
                extract($info = $this->getResourceInfo($file, $base));

                if (! isset($components[$id])) {
                    $resource = $this->assembleResource($info);
                    $components[$id] = $resource;
                }
            }
        }

        return $components;
    }

    protected function getResourceInfo($file, $base)
    {
        $name = strstr(basename($file), '.', true);
        $rel = rel_path($file, $base);
        $dir = (dirname($rel) == '.') ? '' : dirname($rel);
        $id = ($dir) ? str_replace(DS, '.', $dir).'.'.$name : $name;
        $partial = ($dir) ? $base.DS.$dir.DS.$name : $base.DS.$name;

        return [
            'name' => $name,
            'id' => $id,
            'path' => $file,
            'base' => $base,
            'partial' => $partial,
            'directory' => $dir,
        ];
    }

    protected function assembleResource($info)
    {
        extract($info);
        $parts = [];

        foreach ($this->typeMap as $type => $format) {
            // Support multiple types with pipe character
            if (str_contains($format, '|')) {
                foreach (explode('|', $format) as $optionalFormat) {
                    $parts[$type] = (file_exists($file = $partial.'.'.$optionalFormat)) ? $file : null;
                }
            }
            else {
                $parts[$type] = (file_exists($file = $partial.'.'.$format)) ? $file : null;
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

    public function register($name, Component $component)
    {
        if (is_array($name)) {
            $result = true;

            foreach ($name as $name => $component) {
                $current = $this->register($name, $component);
                $result = (! $current) ? false : $result;
            }

            return $result;
        }

        if (! isset($this->components[$name])) {
            $component->setName($name);
            $this->components[$name] = $component;

            return true;
        }

        return false;
    }

    public function include($name, $data = [])
    {
        // Provided component
        if ($name instanceof ComponentContract) {
            $component = $name;
            $name = $component->getName();
        }
        // Provided component matching name
        elseif (isset($this->components[$name]) && $data instanceof ComponentContract) {
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
