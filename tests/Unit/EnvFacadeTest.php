<?php

use Daguilar\BelichEnvManager\Facades\Env;
use Daguilar\BelichEnvManager\Services\EnvManager;
use Illuminate\Support\Facades\App;

test('facade resolves to env manager instance', function () {
    // Ensure the ServiceProvider has registered the class
    expect(App::bound(EnvManager::class))->toBeTrue();

    // Resolve the instance through the Facade
    // This indirectly tests getFacadeAccessor()
    $resolvedInstance = Env::getFacadeRoot();

    expect($resolvedInstance)->toBeInstanceOf(EnvManager::class);
});

test('facade can call env manager methods', function () {
    // Mock the underlying EnvManager instance
    $this->mock(EnvManager::class)
        ->shouldReceive('get')
        ->with('TEST_KEY_FACADE') // The expectation must match the arguments passed in the call
        ->once()->andReturn('facade_value');

    expect(Env::get('TEST_KEY_FACADE'))->toBe('facade_value');
});
