<?php namespace Tekton\Components\Tags;

use Tekton\Components\Tags\TagInfo;
use Tekton\Components\Tags\AbstractTag;

class StyleTag extends AbstractTag
{
    public function getTagName()
    {
        return 'style';
    }

    public function getFileName(TagInfo $info)
    {
        return $info->component->name.'.css';
    }
}
