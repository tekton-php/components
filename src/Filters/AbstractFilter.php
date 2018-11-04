<?php namespace Tekton\Components\Filters;

use Tekton\Components\Tags\TagInfo;
use Tekton\Components\Filters\FilterInterface;

abstract class AbstractFilter implements FilterInterface
{
    public function match(TagInfo $info)
    {
        return true;
    }

    abstract public function process(TagInfo $info);
}
