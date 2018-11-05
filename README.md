Tekton Components
=================

This is a PHP component library that is purposefully built modularly so that it can be easily integrated into various frameworks.

## Installation

```sh
composer require tekton/components
```

### JS compiler

If you wish to compile components as part of your build process and are using a Node.js build environment you can configure [gulp-single-file-components](github.com/nsrosenqvist/gulp-single-file-components) to do this for you. Unfortunately that project is not actively maintained anymore due to the functionality being integrated into Tekton Components instead.

## Usage

### Registering components

The ComponentManager and ComponentCompiler can be used separately and configured to compile files in advance and load from cache, or you can configure a compiler for the ComponentManager to use automatically if a single file component file (SCF) is registered.

By default the project includes filter and tag classes that can emulate the functionality of the [Vue.js component system](https://vuejs.org/v2/guide/single-file-components.html).

```php
use Tekton\Components\ComponentManager;
use Tekton\Components\ComponentCompiler;

$cacheDir = __DIR__.'/cache';
$compiler = new ComponentCompiler($cacheDir);
$compiler->registerTags('template', new TemplateTag);
$compiler->registerTags('style', new StyleTag);
$compiler->registerTags('script', new ScriptTag);

$manager = new ComponentManager($compiler);

$manager->register('button.vue');
```

Other ways the register method accepts arguments are as follows:

```php
// Register by list of components where keys are the name to register
$manager->register([
    'button' => [
        'template' => 'cache/button.html',
        'style' => 'cache/button.css',
        'script' => 'cache/button.js',
    ]
    // ...
]);

// If non-associative array is passed then it must be component files and not
// array of resources. The second argument can optionally be set to specify
// the base directory so that components in sub-dirs don't create naming conflicts
$manager->register([
    'button.vue',        // will be named "button"
    'contact/button.vue' // will be named "contact.button"
    // ...
], __DIR__);

// Register by name and component instance
$manager->register('button', new Component(['template' => 'cache/button.html']));

// Register by component path and optional base path
$manager->register('components/button.vue', 'components/');

// Register component by name and resources array
$manager->register('button', [
    'template' => 'cache/button.html',
    'style' => 'cache/button.css',
    'script' => 'cache/button.js',
]);
```

You can retrieve all the components that have been compiled by the compiler in an associative array with names and resources, or if you have a directory with pre-compiled components, created externally, you can process the directory contents by matching file extensions to a map:

```php
// From the compiler
$components = $compiler->getComponentMap();

// Or
$components = $manager->find('cache/', [
    'template' => ['html', 'php'], // Priority goes from end to beginning
    'scripts' => 'js',
    'style' => 'css',
]); // Passing (bool) true as the third argument registers them directly

$manager->register($components);
```

### Using components

To include a component into the page you simply call.

```php
$manager->include('button');
```

**button.vue**
```html
<template lang="php">
    <div class="button">
        <?php if (true): ?>
            Button
        <?php endif; ?>
    </div>
</template>

<style lang="scss">
    $myColor: #00ff00;

    .button {
        color: $myColor;
    }
</style>

<script>
    alert('component included')
</script>
```

Doing so simply renders the template and CSS and JS need to be handled separately in your templates due to the many different ways frameworks handle assets.

```php
// Combine all script files and only make one http request
$cacheScripts = $cacheDir.'/components.js';

if (! file_exists($cacheScripts)) {
    $files = $manager->resources('script');
    $combined = concat_files($files);

    file_put_contents($cacheScripts, $combined);
}

echo '<script src="'.$cacheScripts.'"></script>';

// Or include every file separately per request and only load those that have
// been included in the page
foreach ($manager->includedResources('script') as $name => $script) {
    echo '<script src="'.$script.'"></script>';
}
```

### Filters

Filters run on the registered tags upon compilation and can use the tag attributes to determine if they are supposed to run or not (e.g. lang="scss" on the style tag). They are configured to run either pre or post the tag processes the tag content. To enable SCSS compilation for the style tag you can do this:

```php
use Tekton\Components\ComponentManager;
use Tekton\Components\ComponentCompiler;
use Tekton\Components\Filters\ScssFilter;

$styleTag = (new StyleTag)->addPostFilter(new ScssFilter);

$cacheDir = __DIR__.'/cache';
$compiler = new ComponentCompiler($cacheDir);
$compiler->registerTags('style', $styleTag);

$manager = new ComponentManager($compiler);
```

### Scope

To prevent styles and scripts from clashing you can either implement your own scope filters or use the ones included. The StyleScope prefixes all the CSS rules with ".component-button" and the TemplateScope wraps the template in an element and adds the component id and "component-button" class. From within a template `$this` is always set to the component instance so you can easily access the index, name and id even if you don't use the TemplateScope filter.

```php
use Tekton\Components\ComponentManager;
use Tekton\Components\ComponentCompiler;

use Tekton\Components\Tags\StyleTag;
use Tekton\Components\Tags\ScriptTag;
use Tekton\Components\Tags\TemplateTag;
use Tekton\Components\Filters\StyleScope;
use Tekton\Components\Filters\ScriptScope;
use Tekton\Components\Filters\TemplateScope;

$cacheDir = __DIR__.DS.'cache';
$compiler = new ComponentCompiler($cacheDir);
$compiler->registerTags('template', (new TemplateTag)->addPostFilter(new TemplateScope));
$compiler->registerTags('style', (new StyleTag)->addPostFilter(new StyleScope));
$compiler->registerTags('script', (new ScriptTag)->addPostFilter(new ScriptScope));

$manager = new ComponentManager($compiler);
```

The template scope supports [Emmet-like](https://docs.emmet.io/) syntax (`div#myId.myclass[rel=myAttr]`) to configure the automatically created container element. It only allows container, id, class and attributes. Any of these can be excluded but they must be in that order. Multiple classes and attributes are allowed.

```html
<template container="section[rel=home]" src="hero/full-page.html" />
```

Would result in...

```html
<section id="<?= $this->getId() ?>" class="component component-hero" rel="home">
    <!-- contents of hero/full-page.html -->
</section>
```

To make sure that the scripts only run once per component, or if all are compiled into one file, you want some way to control so that the component script is only executed upon inclusion. The ScriptScope filter has some extra helper methods for this. These must run after all components have been included, so either in head if you're using a template system that parses templates from the bottom up or in the footer of the body.

```html
<!-- first include all the component scripts -->
<script src="cache/compiled-scripts.js"></script>

<script>
    // Pass manager to ScriptScope to create a list of all components included in the page
    <?= ScriptScope::getIncludedComponentsScript($manager); ?>

    // lastly include the script that handles execution
    <?= ScriptScope::getScopeScript(); ?>
</script>
```

Now the contents of the script tag will be executed only when it's included and once per component. When it's executed `this` is set to the wrapper element for the component. Name, selector (class) and id are also passed along to enable you to only process the current instance of the component if multiple ones have been included.

You can also set the attribute "singleton" on the script tag to make sure that the script is only executed once, no matter how many components are included.

### Custom Renderers

In order to integrate the rendering of the component into different templating systems you will most likely need to extend the Component class (implement ComponentInterface) and change the class the ComponentManager uses to instance Components automatically.

```
// Either set it when creating the ComponentManager
$manager = new ComponentManager($compiler, MyCustomComponent::class);

// Or after
$manager->setComponentClass(MyCustomComponent::class);
```

## License

MIT
