<?php namespace Tekton\Components;

use Tekton\Components\ComponentInterface;
use ErrorException;
use Exception;
use Throwable;

class Component implements ComponentInterface
{
    protected $id;
    protected $name;
    protected $index;
    protected $resources = [];

    public function __construct($resources = [])
    {
        $this->resources = $resources;
    }

    public function set($key, $value)
    {
        $this->resources[$key] = $value;
    }

    public function get($key, $default = null)
    {
        return (isset($this->resources[$key])) ? $this->resources[$key] : $default;
    }

    public function has($key)
    {
        return isset($this->resources[$key]);
    }

    public function processData($data)
    {
        return $data;
    }

    public function setId(string $id)
    {
        $this->id = $id;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setIndex(int $index)
    {
        $this->index = $index;
    }

    public function getIndex()
    {
        return $this->index;
    }

    public function setName(string $name)
    {
        $this->name = $name;
    }

    public function getName()
    {
        return $this->name;
    }

    public function render($data = [])
    {
        $__path = $this->get('template');
        $__data = $this->processData(array_merge($data, ['component' => $this]));
        unset($data);

        // Make sure we have a template to render
        if (! $__path) {
            return '';
        }

        $obLevel = ob_get_level();
        ob_start();

        extract($__data, EXTR_SKIP);

        // We'll evaluate the contents of the view inside a try/catch block so we can
        // flush out any stray output that might get out before an error occurs or
        // an exception is thrown. This prevents any partial views from leaking.
        try {
            if (pathinfo($__path, PATHINFO_EXTENSION) == 'php') {
                include $__path;
            }
            else {
                echo file_get_contents($__path);
            }

        }
        catch (Exception $e) {
            // Do nothing
        }

        return ltrim(ob_get_clean());
    }
}
