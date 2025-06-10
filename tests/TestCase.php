<?php

namespace Daguilar\BelichEnvManager\Tests;

use Daguilar\BelichEnvManager\BelichEnvManagerServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app)
    {
        return [
            BelichEnvManagerServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        // Configura el entorno de la aplicación de prueba
        // $app['config']->set('database.default', 'sqlite');
        // $app['config']->set('database.connections.sqlite', [
        //     'driver' => 'sqlite',
        //     'database' => ':memory:',
        //     'prefix' => '',
        // ]);

        // Configura la ruta base para simular el proyecto Laravel
        $app->instance('path.base', __DIR__.'/../'); // Apunta a la raíz del paquete para pruebas

        // Configura la ruta de storage para pruebas
        $app->instance('path.storage', __DIR__.'/temp/storage');
        
        // Asegúrate de que el directorio de storage de prueba exista
        if (! file_exists(__DIR__.'/temp/storage')) {
            mkdir(__DIR__.'/temp/storage', 0777, true);
        }

        // Configura la ruta de config para pruebas
        $app->instance('path.config', __DIR__.'/temp/config');

         // Asegúrate de que el directorio de config de prueba exista
        if (! file_exists(__DIR__.'/temp/config')) {
            mkdir(__DIR__.'/temp/config', 0777, true);
        }

        // Configura la ruta de bootstrap/cache para pruebas
        $app->instance('path.bootstrap', __DIR__.'/temp/bootstrap');
         if (! file_exists(__DIR__.'/temp/bootstrap/cache')) {
            mkdir(__DIR__.'/temp/bootstrap/cache', 0777, true);
        }
    }
}
