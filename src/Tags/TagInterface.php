<?php namespace Tekton\Components\Tags;

use Tekton\Components\Filters\FilterInterface;
use Tekton\Components\Tags\TagInfo;

interface TagInterface
{
    public function process(TagInfo $info);

    public function getTagName();

    public function getFileName(TagInfo $info);

    public function addPreFilter(FilterInterface $filter);

    public function addPostFilter(FilterInterface $filter);

    public function processPreFilters(TagInfo $info);

    public function processPostFilters(TagInfo $info);
}
