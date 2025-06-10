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
        // Registra la configuración del paquete si tienes un archivo config
        // $this->mergeConfigFrom(__DIR__.'/../config/belich-env-manager.php', 'belich-env-manager');

        // Registra tus clases principales aquí si son singleton o necesitan binding
        // $this->app->singleton(EnvManager::class, function ($app) {
        //     return new EnvManager();
        // });
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        // Publica archivos de configuración, vistas, migraciones, etc.
        // if ($this->app->runningInConsole()) {
        //     $this->publishes([
        //         __DIR__.'/../config/belich-env-manager.php' => config_path('belich-env-manager.php'),
        //     ], 'belich-env-manager-config');

        //     $this->publishes([
        //         __DIR__.'/../resources/views' => resource_path('views/vendor/belich-env-manager'),
        //     ], 'belich-env-manager-views');

        //     // Publica tus archivos de definición YAML/JSON aquí
        //     $this->publishes([
        //         __DIR__.'/../config/env-definitions.yaml' => config_path('env-definitions.yaml'),
        //     ], 'belich-env-manager-definitions');

        //     // Registra comandos de Artisan
        //     // $this->commands([
        //     //     YourEnvManagerCommand::class,
        //     // ]);
        // }

        // Carga vistas
        // $this->loadViewsFrom(__DIR__.'/../resources/views', 'belich-env-manager');

        // Carga rutas
        // $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
    }
}