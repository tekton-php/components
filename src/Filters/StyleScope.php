<?php namespace Tekton\Components\Filters;

use Tekton\Components\Tags\TagInfo;
use Tekton\Components\Filters\AbstractFilter;
use Leafo\ScssPhp\Compiler;
use Exception;

class StyleScope extends AbstractFilter
{
    public function process(TagInfo $info)
    {
        try {
            $scss = new Compiler();
            $info->content = $scss->compile('.component-'.$info->component->name.' {'.$info->content.'}');
        }
        catch (Exception $e) {
            // Do nothing
        }

        return $info;
    }
}
