<?php require "../vendor/autoload.php";

use Tekton\Components\ComponentManager;
use Tekton\Components\ComponentCompiler;

use Tekton\Components\Tags\StyleTag;
use Tekton\Components\Tags\ScriptTag;
use Tekton\Components\Tags\TemplateTag;
use Tekton\Components\Filters\ScssFilter;
use Tekton\Components\Filters\StyleScope;
use Tekton\Components\Filters\ScriptScope;
use Tekton\Components\Filters\TemplateScope;

$cacheDir = __DIR__.DS.'cache';
$compiler = new ComponentCompiler($cacheDir);
$compiler->registerTags('template', (new TemplateTag)->addPostFilter(new TemplateScope));
$compiler->registerTags('style', (new StyleTag)->addPostFilter(new ScssFilter)->addPostFilter(new StyleScope));
$compiler->registerTags('script', (new ScriptTag)->addPostFilter(new ScriptScope));

$manager = new ComponentManager($compiler);

$manager->register(glob(__DIR__.DS.'*.vue'), __DIR__);
?>
<html>
<head>
</head>
<body>
    <?php
        echo $manager->include('component');
        echo $manager->include('component');
        echo $manager->include('button');
    ?>

    <?php
        // first include all the component scripts
        foreach ($manager->includedResources('script') as $name => $script) {
            echo '<script src="cache/'.basename($script).'"></script>'.PHP_EOL;
        }
    ?>

    <script>
        // Pass manager to ScriptScope to create a list of all components included in the page
        <?= ScriptScope::getIncludedComponentsScript($manager); ?>

        // lastly include the script that handles execution
        <?= ScriptScope::getScopeScript(); ?>
    </script>
</body>
