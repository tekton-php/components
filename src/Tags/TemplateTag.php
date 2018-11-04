<?php namespace Tekton\Components\Tags;

use artem_c\emmet\Emmet;
use DOMDocument;
use Tekton\Components\Tags\AbstractTag;
use Tekton\Components\Tags\TagInfo;

class TemplateTag extends AbstractTag
{
    public function getTagName()
    {
        return 'template';
    }

    public function getFileName(TagInfo $info)
    {
        if ($lang = $info->attributes['lang'] ?? false) {
            switch (strtolower($lang)) {
                case 'smarty': $lang = 'tpl'; break;
                case 'blade': $lang = 'blade.php'; break;
            }

            return $info->component->name.'.'.$lang;
        }
        else {
            return $info->component->name.'.html';
        }
    }
}
