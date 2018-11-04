<?php namespace Tekton\Components\Filters;

use Tekton\Components\Tags\TagInfo;

interface FilterInterface
{
    public function match(TagInfo $info);
    
    public function process(TagInfo $info);
}
