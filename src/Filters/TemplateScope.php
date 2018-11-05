<?php namespace Tekton\Components\Filters;

use Tekton\Components\Filters\AbstractFilter;
use Tekton\Components\Tags\TagInfo;
use artem_c\emmet\Emmet;
use DOMDocument;

class TemplateScope extends AbstractFilter
{
    protected function getIdStatement($lang)
    {
        switch (strtolower($lang)) {
            case 'twig': return '{{ component.getId() }}';
            case 'blade':
            case 'blade.php': return '{{ $component->getId() }}';
            case 'tpl':
            case 'smarty': return '{$component->getId()}';
            case 'jade':
            case 'pug':
            case 'phug':
            case 'php': return '<?= $component->getId(); ?>';
        }

        return '';
    }

    protected function getAllPositions($str, $needle)
    {
        $lastPos = 0;
        $positions = [];

        while (($lastPos = strpos($str, $needle, $lastPos))!== false) {
            $positions[] = $lastPos;
            $lastPos = $lastPos + strlen($needle);
        }

        return $positions;
    }

    protected function parseSelectorString($str)
    {
        $positions = array_filter([
            'id' => strpos($str, '#'),
            'class' => strpos($str, '.'),
            'attribute' => strpos($str, '['),
        ], function($value) {
            return $value !== false;
        });

        $lowest = (empty($positions)) ? 0 : min($positions);

        // Get container
        if (empty($positions)) {
            $container = $str;
        }
        else {
            $container = ($lowest > 0) ? substr($str, 0, $lowest) : 'div';
        }

        // Get id
        $id = false;

        if (isset($positions['id'])) {
            $idPos = $positions['id'];
            unset($positions['id']);

            $lowest = (! empty($positions)) ? min($positions) : false;
            $id = ($lowest) ? substr($str, $idPos+1, $lowest-$idPos-1) : substr($str, $idPos+1);
        }

        // Get classes
        $classes = [];

        if (isset($positions['class'])) {
            $classPos = $this->getAllPositions($str, '.');
            $classCount = count($classPos);
            unset($positions['class']);

            $lowest = (! empty($positions)) ? min($positions) : false;

            foreach ($classPos as $key => $pos) {
                if ($key+1 == $classCount) {
                    $classes[] = ($lowest) ? substr($str, $pos+1, $lowest-$pos-1) : substr($str, $pos+1);
                }
                else {
                    $classes[] = substr($str, $pos+1, $classPos[$key+1]-$pos-1);
                }
            }
        }

        // Get attributes
        $attributes = [];

        if (isset($positions['attribute'])) {
            $attrPos = $this->getAllPositions($str, '[');
            $attrCount = count($attrPos);
            unset($positions['attribute']);

            foreach ($attrPos as $key => $pos) {
                $attrPair = substr($str, $pos+1, strpos($str, ']', $pos+1)-$pos-1);
                $attrPair = explode('=', $attrPair);

                if (count($attrPair) >= 1) {
                    $attributes[$attrPair[0]] = (count($attrPair) > 1) ? $attrPair[1] : true;
                }
            }
        }

        // Add classes to attributes
        if (isset($attributes['class'])) {
            $attributes['class'] = array_merge($attributes['class'], $classes);
        }
        elseif (! empty($classes)) {
            $attributes['class'] = $classes;
        }

        // Add id to attributes
        if ($id) {
            $attributes['id'] = $id;
        }

        // Create HTML element
        return '<'.$container.' '.parse_attributes($attributes).'></'.$container.'>';
    }

    public function process(TagInfo $info)
    {
        // Set element for wrapper
        if (! $container = $info->attributes['container'] ?? false) {
            $container = 'div';
        }

        $html = $this->parseSelectorString($container);

        // Load component file
        $xmlStateEntityLoader = libxml_disable_entity_loader(true);
		$xmlStateInternalErrors = libxml_use_internal_errors(true);

		$doc = new DOMDocument('1.0', 'UTF-8');
        $doc->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        $root = $doc->documentElement->childNodes->item(0)->childNodes->item(0);

        // Add class
        $class = $root->getAttribute('class');
        $classes = array_filter(explode(' ', $class));

        if (! in_array('component', $classes)) {
            $classes[] = 'component';
        }
        if (! in_array('component-'.$info->component->name, $classes)) {
            $classes[] = 'component-'.$info->component->name;
        }

        $root->setAttribute('class', trim(implode(' ', $classes)));

        // Set Id
        if ($id = $info->attributes['id'] ?? $root->getAttribute('id') ?: false) {
            $root->setAttribute('id', $id);
        }
        else {
            if ($lang = $info->attributes['lang'] ?? false) {
                $root->setAttribute('id', $this->getIdStatement($lang));
            }
        }

        // Insert content into container
        if (! empty($info->content)) {
            $fragment = $doc->createDocumentFragment();
            $fragment->appendXML($info->content);
            $root->appendChild($fragment);
        }

        $lines = explode("\n", $doc->saveHTML($root), 2);
        $lines[0] = html_entity_decode($lines[0]);
        $info->content = implode("\n", $lines);

        // Reset XML settings
		libxml_disable_entity_loader($xmlStateEntityLoader);
		libxml_use_internal_errors($xmlStateInternalErrors);
		libxml_clear_errors();

        return $info;
    }
}
