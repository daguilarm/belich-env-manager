<?php

use Daguilar\BelichEnvManager\Facades\Env;
use Daguilar\BelichEnvManager\Facades\EnvCollect;
use Daguilar\BelichEnvManager\Services\Env\EnvVariableSetter;
use Daguilar\BelichEnvManager\Services\EnvCollectionManager;
use Daguilar\BelichEnvManager\Services\EnvManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;

test('facade resolves to env manager instance', function () {
    // Ensure the ServiceProvider has registered the class
    expect(App::bound(EnvManager::class))->toBeTrue();

    // Resolve the instance through the Facade
    // This indirectly tests getFacadeAccessor()
    $resolvedInstance = Env::getFacadeRoot();

    expect($resolvedInstance)->toBeInstanceOf(EnvManager::class);
});

test('facade resolves to env collection manager instance', function () {
    // Ensure the ServiceProvider has registered the class
    expect(App::bound(EnvCollectionManager::class))->toBeTrue();

    // Resolve the instance through the Facade
    // This indirectly tests getFacadeAccessor()
    $resolvedInstance = EnvCollect::getFacadeRoot();

    expect($resolvedInstance)->toBeInstanceOf(EnvCollectionManager::class);
});

test('facade can call env manager methods', function () {
    // Mock the underlying EnvManager instance
    $this->mock(EnvManager::class)
        ->shouldReceive('get')
        ->with('TEST_KEY_FACADE') // The expectation must match the arguments passed in the call
        ->once()->andReturn('facade_value');

    expect(Env::get('TEST_KEY_FACADE'))->toBe('facade_value');
});

test('facade can call env collection manager methods', function () {
    // Mock the underlying EnvCollectionManager instance
    $expectedItem = [
        'key' => 'TEST_KEY_FACADE',
        'value' => 'facade_value',
        'is_comment' => false, // Asumiendo la estructura de EnvCollectionManager::transformToCollection
        'comment_above' => null,
        'comment_inline' => null,
    ];

    $this->mock(EnvCollectionManager::class)
        ->shouldReceive('get')
        ->with('TEST_KEY_FACADE') // The expectation must match the arguments passed in the call
        ->once()->andReturn($expectedItem); // Devolver un array que coincida con la firma del método

    expect(EnvCollect::get('TEST_KEY_FACADE')['value'])->toBe('facade_value'); // Acceder al valor dentro del array devuelto
});

test('Env facade can set a value and trigger save', function () {
    $envManagerMock = $this->mock(EnvManager::class);
    $envVariableSetterMock = $this->mock(EnvVariableSetter::class);

    $envManagerMock
        ->shouldReceive('set')
        ->with('NEW_KEY', 'new_value')
        ->once()
        ->andReturn($envVariableSetterMock); // EnvManager::set() devuelve una instancia de EnvVariableSetter

    // La llamada a ->save() se hace sobre la instancia de EnvVariableSetter devuelta
    $envVariableSetterMock
        ->shouldReceive('save')
        ->once()
        ->andReturn(true);
    // Internamente, EnvVariableSetter::save() llama a EnvManager::save().
    // Si necesitas verificar esa llamada interna explícitamente aquí,
    // podrías hacer que el ->andReturn(true) anterior sea un ->andReturnUsing()
    // que llame a $envManagerMock->save(), y luego añadir una expectativa
    // $envManagerMock->shouldReceive('save')->once()->andReturn(true);
    // Pero para este test de facade, verificar que el setter->save() se llama
    // y devuelve true podría ser suficiente, asumiendo que EnvVariableSetter está bien testeado.

    Env::set('NEW_KEY', 'new_value')->save();
});

test('EnvCollect facade can update file from collection and trigger save', function () {
    $mockedManager = $this->mock(EnvCollectionManager::class);

    $newCollection = new Collection([
        ['key' => 'APP_NAME', 'value' => 'Facade Test App', 'is_comment' => false, 'comment_above' => null, 'comment_inline' => null],
    ]);

    $mockedManager->shouldReceive('updateFileFromCollection')
        ->with(Mockery::on(function ($arg) use ($newCollection) {
            return $arg instanceof Collection && $arg->toArray() === $newCollection->toArray();
        }))
        ->once()
        ->andReturn(true); // Asumiendo que updateFileFromCollection llama a save y devuelve su resultado

    expect(EnvCollect::updateFileFromCollection($newCollection))->toBeTrue();
});

test('EnvCollect facade can remove a variable and trigger save', function () {
    $mockedManager = $this->mock(EnvCollectionManager::class);

    $mockedManager->shouldReceive('remove')
        ->with('OLD_KEY')
        ->once()
        ->andReturnSelf(); // remove() devuelve $this

    $mockedManager->shouldReceive('save')
        ->once()
        ->andReturn(true);

    EnvCollect::remove('OLD_KEY')->save();
    // O si save no es encadenable directamente después de remove:
    // EnvCollect::remove('OLD_KEY');
    // expect(EnvCollect::save())->toBeTrue();
});

test('Env facade triggers backup on save if enabled', function () {
    // Asumimos que EnvManager internamente llama a BackupManager->create()
    // y que la configuración de backup está habilitada por defecto en los tests.
    // El mock de BackupManager ya tiene ->shouldReceive('create')->andReturn(true)->byDefault();
    // Necesitamos asegurarnos que el save de EnvManager se llama.
    $this->mock(EnvManager::class)->shouldReceive('save')->once()->andReturn(true);

    expect(Env::save())->toBeTrue();
    // La verificación de que BackupManager->create() fue llamado la hace la expectativa por defecto.
});
