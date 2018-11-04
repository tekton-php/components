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

    public function process(TagInfo $info)
    {
        // Set element for wrapper
        if (! $container = $info->attributes['container'] ?? false) {
            $container = 'div';
        }

        $html = (new Emmet($container))->create();

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
