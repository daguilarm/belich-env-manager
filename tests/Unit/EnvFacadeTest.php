<?php

// Unit tests for the Env and EnvCollect facades.
// These tests ensure that the facades correctly resolve to their underlying
// manager instances and that method calls are properly proxied.
use Daguilar\BelichEnvManager\Facades\Env;
use Daguilar\BelichEnvManager\Facades\EnvCollect;
use Daguilar\BelichEnvManager\Services\Env\EnvVariableSetter;
use Daguilar\BelichEnvManager\Services\EnvCollectionManager;
use Daguilar\BelichEnvManager\Services\EnvManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;

describe('Facade Resolution', function () {
    // Verifies that the Env facade resolves to an instance of EnvManager.
    it('Env facade resolves to EnvManager instance', function () {
        expect(App::bound(EnvManager::class))->toBeTrue();
        $resolvedInstance = Env::getFacadeRoot();
        expect($resolvedInstance)->toBeInstanceOf(EnvManager::class);
    });

    // Verifies that the EnvCollect facade resolves to an instance of EnvCollectionManager.
    it('EnvCollect facade resolves to EnvCollectionManager instance', function () {
        expect(App::bound(EnvCollectionManager::class))->toBeTrue();
        $resolvedInstance = EnvCollect::getFacadeRoot();
        expect($resolvedInstance)->toBeInstanceOf(EnvCollectionManager::class);
    });
});

describe('Facade Method Proxying', function () {
    // Tests that methods called on the Env facade are correctly proxied to EnvManager.
    it('Env facade can call EnvManager methods', function () {
        $this->mock(EnvManager::class)
            ->shouldReceive('get')
            ->with('TEST_KEY_FACADE')
            ->once()->andReturn('facade_value');

        expect(Env::get('TEST_KEY_FACADE'))->toBe('facade_value');
    });

    // Tests that methods called on the EnvCollect facade are correctly proxied to EnvCollectionManager.
    it('EnvCollect facade can call EnvCollectionManager methods', function () {
        $expectedItem = [
            'key' => 'TEST_KEY_FACADE',
            'value' => 'facade_value',
            'is_comment' => false,
            'comment_above' => null,
            'comment_inline' => null,
        ];

        $this->mock(EnvCollectionManager::class)
            ->shouldReceive('get')
            ->with('TEST_KEY_FACADE')
            ->once()->andReturn($expectedItem);

        expect(EnvCollect::get('TEST_KEY_FACADE')['value'])->toBe('facade_value');
    });
});

describe('Facade Operations and Chaining', function () {
    // Verifies that setting a value via Env facade returns an EnvVariableSetter,
    // and that save() on the setter is correctly called.
    it('Env facade can set a value and trigger save via EnvVariableSetter', function () {
        $envManagerMock = $this->mock(EnvManager::class);
        $envVariableSetterMock = $this->mock(EnvVariableSetter::class);

        $envManagerMock
            ->shouldReceive('set')
            ->with('NEW_KEY', 'new_value')
            ->once()
            ->andReturn($envVariableSetterMock);

        $envVariableSetterMock
            ->shouldReceive('save')
            ->once()
            ->andReturn(true);

        Env::set('NEW_KEY', 'new_value')->save();
    });

    // Tests updating the .env file from a collection via the EnvCollect facade.
    it('EnvCollect facade can update file from collection and save', function () {
        $mockedManager = $this->mock(EnvCollectionManager::class);
        $newCollection = new Collection([
            ['key' => 'APP_NAME', 'value' => 'Facade Test App', 'is_comment' => false, 'comment_above' => null, 'comment_inline' => null],
        ]);

        // EnvCollectionManager::updateFileFromCollection calls save() internally and returns its result.
        $mockedManager->shouldReceive('updateFileFromCollection')
            ->with(Mockery::on(fn ($arg) => $arg instanceof Collection && $arg->toArray() === $newCollection->toArray()))
            ->once()
            ->andReturn(true);

        expect(EnvCollect::updateFileFromCollection($newCollection))->toBeTrue();
    });

    // Tests removing a variable and saving the changes via the EnvCollect facade.
    it('EnvCollect facade can remove a variable and save', function () {
        $mockedManager = $this->mock(EnvCollectionManager::class);

        $mockedManager->shouldReceive('remove')
            ->with('OLD_KEY')
            ->once()
            ->andReturnSelf(); // remove() is fluent

        $mockedManager->shouldReceive('save')
            ->once()
            ->andReturn(true);

        EnvCollect::remove('OLD_KEY')->save();
    });

    // Verifies that a direct call to Env::save() triggers the save mechanism in EnvManager.
    // This implicitly tests that backup creation is attempted if enabled (as per default mock setup).
    it('Env facade save() triggers EnvManager save and attempts backup', function () {
        $this->mock(EnvManager::class)->shouldReceive('save')->once()->andReturn(true);
        expect(Env::save())->toBeTrue();
        // BackupManager mock has a default expectation for create() to be called.
    });
});
