<?php

namespace Daguilar\BelichEnvManager;

use Illuminate\Support\ServiceProvider;

class BelichEnvManagerServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/belich-env-manager.php', 'belich-env-manager');

        $this->app->singleton(EnvManager::class, function ($app) {
            return new EnvManager($app['files'], $app['config']);
        });
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/belich-env-manager.php' => config_path('belich-env-manager.php'),
            ], 'belich-env-manager-config');
    }
}