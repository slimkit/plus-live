<?php

namespace Slimkit\PlusLive\Providers;

use Zhiyi\Plus\Support\PackageHandler;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Boorstrap the service provider.
     *
     * @return void
     */
    public function boot()
    {
        // Register a database migration path.
        $this->loadMigrationsFrom($this->app->make('path.plus-live.migrations'));

        // Register translations.
        $this->loadTranslationsFrom($this->app->make('path.plus-live.lang'), 'plus-live');

        // Register view namespace.
        $this->loadViewsFrom($this->app->make('path.plus-live.views'), 'plus-live');

        // Publish public resource.
        $this->publishes([
            $this->app->make('path.plus-live.assets') => $this->app->publicPath().'/assets/plus-live',
        ], 'public');

        // Publish config.
        $this->publishes([
            $this->app->make('path.plus-live.config').'/plus-live.php' => $this->app->configPath('plus-live.php'),
        ], 'config');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        // Bind all of the package paths in the container.
        $this->app->instance('path.plus-live', $path = dirname(dirname(__DIR__)));
        $this->app->instance('path.plus-live.database', $path.'/database');
        $this->app->instance('path.plus-live.migrations', $path.'/database/migrations');
        $this->app->instance('path.plus-live.seeds', $path.'/database/seeds');
        $this->app->instance('path.plus-live.assets', $path.'/assets');
        $this->app->instance('path.plus-live.resources', $resourcePath = $path.'/resources');
        $this->app->instance('path.plus-live.lang', $resourcePath.'/lang');
        $this->app->instance('path.plus-live.views', $resourcePath.'/views');
        $this->app->instance('path.plus-live.config', $configPath = $path.'/config');

        // Merge config.
        $this->mergeConfigFrom($configPath.'/live.php', 'live');

        // register cntainer aliases
        $this->registerContainerAliases();

        // Register singletons.
        $this->registerSingletions();

        // Register Plus package handlers.
        $this->registerPackageHandlers();
    }

    /**
     * Register singletons.
     *
     * @return void
     */
    protected function registerSingletions()
    {
        // Owner handler.
        $this->app->singleton('plus-live:handler', function () {
            return new \Slimkit\PlusLive\Handlers\PackageHandler();
        });

        // Develop handler.
        $this->app->singleton('plus-live:dev-handler', function ($app) {
            return new \Slimkit\PlusLive\Handlers\DevPackageHandler($app);
        });
    }

    /**
     * Register container aliases.
     *
     * @return void
     */
    protected function registerContainerAliases()
    {
        $aliases = [
            'plus-live:handler' => [
                \Slimkit\PlusLive\Handlers\PackageHandler::class,
            ],
            'plus-live:dev-handler' => [
                \Slimkit\PlusLive\Handlers\DevPackageHandler::class,
            ],
        ];

        foreach ($aliases as $key => $aliases) {
            foreach ($aliases as $key => $alias) {
                $this->app->alias($key, $alias);
            }
        }
    }

    /**
     * Register Plus package handlers.
     *
     * @return void
     */
    protected function registerPackageHandlers()
    {
        $this->loadHandleFrom('plus-live', 'plus-live:handler');
        $this->loadHandleFrom('plus-live-dev', 'plus-live:dev-handler');
    }

    /**
     * Register handler.
     *
     * @param string $name
     * @param \Zhiyi\Plus\Support\PackageHandler|string $handler
     * @return void
     */
    private function loadHandleFrom(string $name, $handler)
    {
        PackageHandler::loadHandleFrom($name, $handler);
    }
}
