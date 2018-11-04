<?php namespace Tekton\Components\Tags;

use Tekton\Components\Filters\FilterInterface;
use Tekton\Components\Tags\TagInterface;
use Tekton\Components\Tags\TagInfo;

abstract class AbstractTag implements TagInterface
{
    protected $preFilters = [];
    protected $postFilters = [];

    abstract public function getTagName();

    abstract public function getFileName(TagInfo $info);

    public function process(TagInfo $info)
    {
        return $info;
    }

    public function addPreFilter(FilterInterface $filter)
    {
        $this->preFilters[] = (is_string($filter)) ? new $filter() : $filter;
        return $this;
    }

    public function addPostFilter(FilterInterface $filter)
    {
        $this->postFilters[] = (is_string($filter)) ? new $filter() : $filter;
        return $this;
    }

    public function processPreFilters(TagInfo $info)
    {
        foreach ($this->preFilters as $filter) {
            $info = $filter->process($info);
        }

        return $info;
    }

    public function processPostFilters(TagInfo $info)
    {
        foreach ($this->postFilters as $filter) {
            $info = $filter->process($info);
        }

        return $info;
    }
}
