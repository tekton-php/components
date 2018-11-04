<?php namespace Tekton\Components;

class ComponentInfo
{
    public $name;
    public $id;
    public $path;
    public $base;
    public $partial;
    public $directory;

    public function __construct($path, $base)
    {
        $name = strstr(basename($path), '.', true);
        $rel = rel_path($path, $base);
        $dir = (dirname($rel) == '.') ? '' : dirname($rel);
        $id = ($dir) ? str_replace(DS, '.', $dir).'.'.$name : $name;
        $partial = ($dir) ? $base.DS.$dir.DS.$name : $base.DS.$name;

        $this->name = $name;
        $this->id = $id;
        $this->path = $path;
        $this->base = $base;
        $this->partial = $partial;
        $this->directory = $dir;
    }
}
