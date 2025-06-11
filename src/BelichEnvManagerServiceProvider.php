<?php

namespace Daguilar\BelichEnvManager;

use Daguilar\BelichEnvManager\Services\BackupManager;
use Daguilar\BelichEnvManager\Services\Env\EnvEditor;
use Daguilar\BelichEnvManager\Services\Env\EnvFormatter;
use Daguilar\BelichEnvManager\Services\Env\EnvParser;
use Daguilar\BelichEnvManager\Services\Env\EnvStorage;
use Daguilar\BelichEnvManager\Services\EnvManager;
use Illuminate\Support\ServiceProvider; // Asegúrate de la ruta correcta

class BelichEnvManagerServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/belich-env-manager.php', 'belich-env-manager');

        $this->app->singleton(BackupManager::class, function ($app) {
            return BackupManager::fromConfig($app['files'], $app['config']);
        });

        $this->app->singleton(EnvParser::class, function ($app) {
            return new EnvParser;
        });

        $this->app->singleton(EnvFormatter::class, function ($app) {
            return new EnvFormatter;
        });

        $this->app->singleton(EnvStorage::class, function ($app) {
            return new EnvStorage($app['files']);
        });

        $this->app->singleton(EnvEditor::class, function ($app) {
            return new EnvEditor;
        });

        $this->app->singleton(EnvManager::class, function ($app) {
            return new EnvManager($app['files'], $app['config'], $app[BackupManager::class], $app[EnvParser::class], $app[EnvFormatter::class], $app[EnvStorage::class], $app[EnvEditor::class]);
        });

        // Register alias for the Facade
        $this->app->alias(EnvManager::class, 'belich.env-manager');
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
}
