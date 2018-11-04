<?php namespace Tekton\Components;

use Tekton\Components\Tags\TagInfo;
use DOMDocument;
use Exception;

class ComponentCompiler
{
    protected $cacheDir;
    protected $tags;

    public function __construct($cacheDir, $scoped = false)
    {
        $this->cacheDir = $cacheDir;

        if (! file_exists($this->cacheDir)) {
            mkdir($this->cacheDir, 0775, true);
            $this->cacheDir = realpath($cacheDir);
        }
    }

    public function registerTags($name, $class)
    {
        if (is_array($name)) {
            foreach ($name as $key => $val) {
                $this->registerTags($key, $val);
            }
        }
        else {
            $this->tags[$name] = (is_string($class)) ? new $class : $class;
        }

        return $this;
    }

    public function compile($path, $basePath = null)
    {
        // Support compiling files from array
        if (is_array($path)) {
            $result = [];

            foreach ($path as $key => $val) {
                $result = array_merge($result, $this->compile($val, $basePath));
            }

            return $result;
        }

        if (! file_exists($path)) {
            throw new Exception('Component file not found: '.$path);
        }
        else {
            $path = realpath($path);
        }

        if (is_null($basePath)) {
            $basePath = dirname($path);
        }

        // Load component file
        $xmlStateEntityLoader = libxml_disable_entity_loader(true);
		$xmlStateInternalErrors = libxml_use_internal_errors(true);

		$doc = new DOMDocument('1.0', 'UTF-8');
        $doc->loadHTML('<?xml encoding="utf-8" ?>'.file_get_contents($path));

        // Process tags
        $tagNodes = $doc->documentElement->childNodes->item(0)->childNodes;
        $tags = [];

        foreach ($tagNodes as $node) {
            $attr = [];
            $tag = $node->tagName;
            $content = '';
            $tagPath = '';

            // Get attributes
            if ($node->hasAttributes()) {
                foreach ($node->attributes as $nodeAttr) {
                    $attr[$nodeAttr->nodeName] = (empty($nodeAttr->nodeValue)) ? true : $nodeAttr->nodeValue;
                }
            }

            // Either load content from src attribute or tag content
            if (isset($attr['src']) && ! empty($attr['src'])) {
                if (file_exists($attr['src'])) {
                    $tagPath = realpath($attr['src']);
                }
                elseif (file_exists($src = dirname($path).DS.$attr['src'])) {
                    $tagPath = realpath($src);
                }

                if (! empty($tagPath)) {
                    $content = file_get_contents($tagPath);
                }
            }
            else {
                // Extract innerHTML
                foreach ($node->childNodes as $child) {
                    $content .= $node->ownerDocument->saveHTML($child);
                }
            }

            // Save to result
            $component = new ComponentInfo($path, $basePath);
            $tags[$tag] = new TagInfo($tag, $tagPath, $content, $attr, $component);
        }

        // Reset XML settings
		libxml_disable_entity_loader($xmlStateEntityLoader);
		libxml_use_internal_errors($xmlStateInternalErrors);
		libxml_clear_errors();

        // Pass content to tag handlers and filters
        $result[$component->name] = [];

        foreach ($tags as $tag => $info) {
            if (isset($this->tags[$tag])) {
                // Create tag handler
                $tagHandler = $this->tags[$tag];
                $filename = $tagHandler->getFileName($info);
                $cachePath = $this->cacheDir.DS.$filename;
                $result[$component->name][$tag] = $cachePath;

                // Skip compilation if cache file is up to date
                if (file_exists($cachePath)) {
                    $cacheTime = filemtime($cachePath);

                    if ($cacheTime > filemtime($info->component->path) || (! empty($info->path) && $cacheTime > filemtime($info->path))) {
                        continue;
                    }
                }

                // Process file
                $info = $tagHandler->processPreFilters($info);
                $info = $tagHandler->process($info);
                $info = $tagHandler->processPostFilters($info);

                // Write compiled file to cache
                if (! file_put_contents($cachePath, $info->content)) {
                    throw new Exception('Failed to write cache file: '.$cachePath);
                }
            }
        }

        return $result;
    }
}
