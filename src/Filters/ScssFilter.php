<?php namespace Tekton\Components\Filters;

use Tekton\Components\Tags\TagInfo;
use Tekton\Components\Filters\AbstractFilter;
use Leafo\ScssPhp\Compiler;
use Exception;

class ScssFilter extends AbstractFilter
{
    public $scss;

    public function __construct()
    {
        $this->scss = new Compiler();
    }
    public function match(TagInfo $info)
    {
        if ($lang = $info->attributes['lang'] ?? false) {
            if (in_array(strtolower($lang), ['scss','sass'])) {
                return true;
            }
        }

        return false;
    }

    public function process(TagInfo $info)
    {
        try {
            $info->content = $this->scss->compile($info->content);
        }
        catch (Exception $e) {
            // Do nothing
        }

        return $info;
    }
}
