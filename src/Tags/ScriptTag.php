<?php namespace Tekton\Components\Tags;

use Tekton\Components\Tags\TagInfo;
use Tekton\Components\Tags\AbstractTag;

class ScriptTag extends AbstractTag
{
    public function getTagName()
    {
        return 'script';
    }

    public function getFileName(TagInfo $info)
    {
        return $info->component->name.'.js';
    }
}
