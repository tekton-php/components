<?php namespace Tekton\Components\Filters;

use Tekton\Components\Tags\TagInfo;
use Tekton\Components\Filters\AbstractFilter;
use Exception;

class ScriptScope extends AbstractFilter
{
    public function process(TagInfo $info)
    {
        $singleton = $info->attributes['singleton'] ?? false;
        $singleton = ($singleton) ? 'true' : 'false';

        $info->content = <<<HEREDOC
if (typeof(scriptScope) === 'undefined') {
    var scriptScope = {
        scripts: [],
        included: [],
        singleton: [],
        executed: [],
    };
}

scriptScope.singleton['{$info->component->name}'] = {$singleton};
scriptScope.executed['{$info->component->name}'] = false;
scriptScope.scripts['{$info->component->name}'] = function(name, id, selector) {
    (function(window, document, name, id, selector){
{$info->content}
    })(window, document, name, id, selector);
};
HEREDOC;

        return $info;
    }

    static public function getIncludedComponentsList($manager)
    {
        $included = [];

        foreach ($manager->instances() as $type => $instances) {
            $included[$type] = array_keys($instances);
        }

        return $included;
    }

    static public function getIncludedComponentsScript($manager)
    {
        $json = json_encode(self::getIncludedComponentsList($manager));

        return <<<HEREDOC
if (typeof(scriptScope) === 'undefined') {
    var scriptScope = {
        scripts: [],
        included: [],
        singleton: [],
        executed: [],
    };
}

scriptScope.included = {$json};
HEREDOC;
    }

    static public function getScopeScriptPath()
    {
        return realpath(__DIR__.DS.'..'.DS.'assets'.DS.'script-scope.js');
    }

    static public function getScopeScript()
    {
        return file_get_contents(self::getScopeScriptPath());
    }
}
