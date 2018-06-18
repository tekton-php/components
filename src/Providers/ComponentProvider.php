<?php namespace Tekton\Components\Providers;

use Illuminate\Support\ServiceProvider;
use Tekton\Components\ComponentManager;
use Tekton\Components\Component;

class ComponentProvider extends ServiceProvider
{
    public function provides()
    {
        return ['components'];
    }

    public function register()
    {
        $this->app->singleton('components', function($app) {
            $components = new ComponentManager($app);
            $directory = $this->app['config']->get('components.directory');

            // Set up cache dir
            $cacheDir = get_path('cache').DS.'components';
            $this->app->registerPath('cache.components', $cacheDir);

            foreach ($components->find($directory) as $name => $parts) {
                $template = $parts['template'] ?? null;
                $scripts = $parts['scripts'] ?? null;
                $styles = $parts['styles'] ?? null;
                $serverConfig = $parts['server-config'] ?? null;
                $clientConfig = $parts['client-config'] ?? null;

                $component = new Component($template, $scripts, $styles, $serverConfig, $clientConfig);
                $components->register($name, $component);
            }

            return $components;
        });
    }
}
