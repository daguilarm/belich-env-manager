<?php

use Daguilar\BelichEnvManager\Env\EnvManager;
use Daguilar\BelichEnvManager\Facades\Env;
use Illuminate\Support\Facades\App;

test('facade resolves to env manager instance', function () {
    // Asegurar que el ServiceProvider ha registrado la clase
    expect(App::bound(EnvManager::class))->toBeTrue();

    // Resolver la instancia a través de la Facade
    // Esto indirectamente prueba getFacadeAccessor()
    $resolvedInstance = Env::getFacadeRoot();

    expect($resolvedInstance)->toBeInstanceOf(EnvManager::class);
});

test('facade can call env manager methods', function () {
    // Mockear la instancia subyacente de EnvManager
    $this->mock(EnvManager::class)
        ->shouldReceive('get')
        ->with('TEST_KEY_FACADE') // La expectativa debe coincidir con los argumentos pasados en la llamada
        ->once()->andReturn('facade_value');

    expect(Env::get('TEST_KEY_FACADE'))->toBe('facade_value');
});
