<?php namespace Tekton\Components\Tags;

use Tekton\Components\ComponentInfo;

class TagInfo
{
    public $name;
    public $path;
    public $content;
    public $attributes;
    public $component;

    public function __construct($name, $path, $content, $attributes, ComponentInfo $component)
    {
        $this->name = $name;
        $this->path = $path;
        $this->content = $content;
        $this->attributes = $attributes;
        $this->component = $component;
    }
}
