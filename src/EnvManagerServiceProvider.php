<?php

namespace Daguilar\EnvManager;

use Daguilar\EnvManager\Services\BackupManager;
use Daguilar\EnvManager\Services\Env\EnvEditor;
use Daguilar\EnvManager\Services\Env\EnvFormatter;
use Daguilar\EnvManager\Services\Env\EnvParser;
use Daguilar\EnvManager\Services\Env\EnvStorage;
use Daguilar\EnvManager\Services\EnvCollectionManager;
use Daguilar\EnvManager\Services\EnvManager;
use Illuminate\Support\ServiceProvider; // Asegúrate de la ruta correcta

class EnvManagerServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/env-manager.php', 'env-manager');

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

        $this->app->singleton(EnvCollectionManager::class, function ($app) {
            return new EnvCollectionManager($app['config'], $app[BackupManager::class], $app[EnvParser::class], $app[EnvStorage::class]);
        });

        // Register alias for the Facade
        $this->app->alias(EnvManager::class, 'env-manager');
        $this->app->alias(EnvCollectionManager::class, 'env-collection-manager');
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/env-manager.php' => config_path('env-manager.php'),
            ], 'env-manager-config');
        }
    }
}
