<?php namespace Tekton\Components;

use Exception;
use DOMDocument;
use FilesystemIterator;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

use Tekton\Components\Tags\TagInfo;
use Tekton\Components\ComponentInfo;

class ComponentCompiler
{
    protected $cacheDir;
    protected $cacheMap = [];
    protected $cacheMapPath;
    protected $componentMapPath;
    protected $componentMap = [];
    protected $tags;

    public function __construct($cacheDir, $scoped = false)
    {
        $this->cacheDir = $cacheDir;
        $this->cacheMapPath = $cacheDir.DS.'_cache-map.php';
        $this->componentMapPath = $cacheDir.DS.'_component-map.php';

        if (! file_exists($this->cacheDir)) {
            mkdir($this->cacheDir, 0775, true);
            $this->cacheDir = realpath($cacheDir);
        }

        if (file_exists($this->componentMapPath)) {
            $this->componentMap = include $this->componentMapPath;
        }
        if (file_exists($this->cacheMapPath)) {
            $this->cacheMap = include $this->cacheMapPath;
        }
    }

    public function getComponentMap()
    {
        return $this->componentMap;
    }

    protected function saveComponentMap()
    {
        return write_object_to_file($this->componentMapPath, $this->componentMap);
    }

    protected function saveCacheMap()
    {
        return write_object_to_file($this->cacheMapPath, $this->cacheMap);
    }

    public function clearCache()
    {
        $this->componentMap = [];
        $this->cacheMap = [];
        
        $di = new RecursiveDirectoryIterator($this->cacheDir, FilesystemIterator::SKIP_DOTS);
        $ri = new RecursiveIteratorIterator($di, RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($ri as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        return true;
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

    protected function parseSingleFileComponent($path, ComponentInfo $componentInfo)
    {
        // Configure DOMDocument
        $xmlStateEntityLoader = libxml_disable_entity_loader(true);
		$xmlStateInternalErrors = libxml_use_internal_errors(true);

        // Load component file
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
            $tags[$tag] = new TagInfo($tag, $tagPath, $content, $attr, $componentInfo);
        }

        // Reset XML settings
		libxml_disable_entity_loader($xmlStateEntityLoader);
		libxml_use_internal_errors($xmlStateInternalErrors);
		libxml_clear_errors();

        return $tags;
    }

    public function compile($path, $basePath = null)
    {
        $cacheMap = $this->cacheMap;
        $componentMap = $this->componentMap;
        $result = [];
        $files = (array) $path;

        // Process files
        foreach ($files as $key => $path) {
            // Validate file
            if (! file_exists($path)) {
                throw new Exception('Component file not found: '.$path);
            }
            else {
                $path = realpath($path);
            }

            // Get info
            $componentInfo = new ComponentInfo($path, $basePath ?? dirname($path));
            $name = $componentInfo->name;

            // Only compile if cache is invalid
            if (! isset($this->componentMap[$name]) || ! isset($this->cacheMap[$path]) || filemtime($path) > $this->cacheMap[$path]) {
                $tags = $this->parseSingleFileComponent($path, $componentInfo);

                if (empty($tags)) {
                    unset($this->cacheMap[$path]);
                    continue;
                }

                // Pass content to tag handlers and filters
                $result[$name] = [];

                foreach ($tags as $tag => $tagInfo) {
                    if (isset($this->tags[$tag])) {
                        // Create tag handler
                        $tagHandler = $this->tags[$tag];
                        $filename = $tagHandler->getFileName($tagInfo);
                        $cachePath = $this->cacheDir.DS.$filename;
                        $result[$name][$tag] = $cachePath;

                        // Process file
                        $tagInfo = $tagHandler->processPreFilters($tagInfo);
                        $tagInfo = $tagHandler->process($tagInfo);
                        $tagInfo = $tagHandler->processPostFilters($tagInfo);

                        // Write compiled file to cache
                        if (! file_put_contents($cachePath, $tagInfo->content)) {
                            throw new Exception('Failed to write cache file: '.$cachePath);
                        }

                        $this->cacheMap[$path] = filemtime($cachePath);
                    }
                }

                // Update component map
                $this->componentMap[$name] = $result[$name];
            }
            else {
                // If it's already been compiled we return the component definition
                $result[$name] = $this->componentMap[$name];
            }
        }

        // If maps have been changed save them
        if ($this->cacheMap != $cacheMap) {
            $this->saveCacheMap();
        }
        if ($this->componentMap != $componentMap) {
            $this->saveComponentMap();
        }

        // Return array with all components that have been compiled with name
        // as key and their resources as value
        return $result;
    }
}
