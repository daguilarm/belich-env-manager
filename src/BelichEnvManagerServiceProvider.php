<?php

namespace Daguilar\BelichEnvManager;

use Daguilar\BelichEnvManager\Backup\BackupManager;
use Daguilar\BelichEnvManager\Env\EnvManager;
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

        $this->app->singleton(EnvManager::class, function ($app) {
            return new EnvManager($app['files'], $app['config'], $app[BackupManager::class]);
        });

        // Registrar el alias para la Facade
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
