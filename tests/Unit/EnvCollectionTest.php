<?php

use Daguilar\BelichEnvManager\Services\BackupManager;
use Daguilar\BelichEnvManager\Services\Env\EnvParser;
use Daguilar\BelichEnvManager\Services\Env\EnvStorage;
use Daguilar\BelichEnvManager\Services\EnvCollectionManager;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;

beforeEach(function () {
    // Mock de dependencias
    $this->config = Mockery::mock(ConfigRepository::class);
    $this->backupManager = Mockery::mock(BackupManager::class);
    // Añadir expectativa por defecto para la creación de backups
    $this->backupManager->shouldReceive('create')->andReturn(true)->byDefault();
    $this->parser = new EnvParser;
    $this->storage = Mockery::mock(EnvStorage::class);
    $this->filesystem = Mockery::mock(Filesystem::class);

    // Configuración básica
    $this->envPath = base_path('.env');
    // Añadir expectativa por defecto para la escritura en storage
    $this->storage->shouldReceive('write')->with($this->envPath, Mockery::any())->andReturn(true)->byDefault();
    $this->config->shouldReceive('get')->with('belich-env-manager.backup.enabled', true)->once()->andReturn(true); // Consumido por $this->manager

    // Instancia del manager
    $this->manager = new EnvCollectionManager(
        $this->config,
        $this->backupManager,
        $this->parser,
        $this->storage
    );
});

afterEach(function () {
    Mockery::close();
});

test('carga correctamente el contenido del archivo .env', function () {
    $envContent = <<<'EOL'
# App Config
APP_NAME=Laravel # App name
APP_ENV=local

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
EOL;

    $this->storage->shouldReceive('read')->with($this->envPath)->andReturn($envContent);
    $this->manager->load();

    $collection = $this->manager->asCollection();

    expect($collection)->toBeInstanceOf(Collection::class);
    // EnvParser produce:
    // 1. Variable APP_NAME (con '# App Config' como comment_above)
    // 2. Variable APP_ENV
    // 3. Línea vacía
    // 4. Variable DB_CONNECTION (con '# Database' como comment_above)
    // 5. Variable DB_HOST
    expect($collection)->toHaveCount(5);
});

test('transforma correctamente el contenido a collection', function () {
    $envContent = <<<'EOL'
# App Name
APP_NAME="Laravel" # Application name

APP_ENV=production
EOL;

    $this->storage->shouldReceive('read')->with($this->envPath)->andReturn($envContent);
    $this->manager->load();

    $collection = $this->manager->asCollection();

    // First element: variable with comment above
    $appName = $collection[0];
    expect($appName['key'])->toBe('APP_NAME');
    expect($appName['value'])->toBe('Laravel');
    expect($appName['comment_above'])->toBe('# App Name');
    expect($appName['comment_inline'])->toBe('Application name');

    // Second element: empty line
    $emptyLine = $collection[1];
    expect($emptyLine['key'])->toBeNull();
    expect($emptyLine['value'])->toBeNull();
    expect($emptyLine['is_comment'])->toBeFalse();

    // Third element: variable without comments
    $appEnv = $collection[2];
    expect($appEnv['key'])->toBe('APP_ENV');
    expect($appEnv['value'])->toBe('production');
    expect($appEnv['comment_above'])->toBeNull();
    expect($appEnv['comment_inline'])->toBeNull();
});

// In test 'actualiza desde una colección externa'
test('actualiza desde una colección externa', function () {
    $newCollection = new Collection([
        [
            'key' => 'APP_NAME',
            'value' => 'New App',
            'is_comment' => false,
            'comment_above' => 'Updated comment',
            'comment_inline' => null,
        ],
        [
            'key' => 'NEW_KEY',
            'value' => 'new_value',
            'is_comment' => false,
            'comment_above' => null,
            'comment_inline' => 'Inline comment',
        ],
    ]);

    // Add write expectation
    $this->storage->shouldReceive('write')->andReturn(true);

    $this->manager->updateFileFromCollection($newCollection);
    $collection = $this->manager->asCollection();

    expect($collection)->toHaveCount(2);
    expect($collection[0]['key'])->toBe('APP_NAME');
    expect($collection[1]['key'])->toBe('NEW_KEY');
});

// In test 'formatea correctamente la colección a contenido .env'
test('formatea correctamente la colección a contenido .env', function () {
    $collection = new Collection([
        [
            'key' => null,
            'value' => null,
            'is_comment' => true,
            'comment_above' => null,
            'comment_inline' => 'Top comment',
        ],
        [
            'key' => 'APP_NAME',
            'value' => 'Laravel',
            'is_comment' => false,
            'comment_above' => "App Name\nSecond line",
            'comment_inline' => 'Inline comment',
        ],
        [
            'key' => 'ESCAPED_VALUE',
            'value' => 'value with spaces#and#special"chars',
            'is_comment' => false,
            'comment_above' => null,
            'comment_inline' => null,
        ],
    ]);

    // Add write expectation
    $expectedContent = <<<EOL
# Top comment
# App Name
# Second line
APP_NAME="Laravel" # Inline comment
ESCAPED_VALUE="value with spaces#and#special\"chars"
EOL;

    $this->storage->shouldReceive('write')
        ->with($this->envPath, $expectedContent)
        ->andReturn(true);

    $result = $this->manager->updateFileFromCollection($collection);
    expect($result)->toBeTrue();
});

test('obtiene elementos por clave devuelve null si no existe', function () {
    $this->storage->shouldReceive('read')->with($this->envPath)->andReturn('APP_NAME=Laravel');
    $this->manager->load();

    $item = $this->manager->get('NON_EXISTING_KEY');
    expect($item)->toBeNull();
});

test('set y get con interfaz fluida para nueva variable', function () {
    $this->storage->shouldReceive('read')->with($this->envPath)->andReturn(''); // Start with empty
    $this->manager->load();

    $this->manager->set('NEW_FLUENT_KEY', 'fluent_value')
        ->commentsAbove('Comment for fluent key')
        ->commentLine('Inline for fluent')
        ->save(); // This will trigger mocks for backupManager and storage->write

    // Reload or check internal collection state
    // For simplicity, let's assume save updated the internal collection correctly
    // or that FluentEnvItem updates the manager's collection upon its methods.
    // Given the current FluentEnvItem, it updates the manager's collection.

    $item = $this->manager->get('NEW_FLUENT_KEY');
    expect($item)->not->toBeNull();
    expect($item['value'])->toBe('fluent_value');
    expect($item['comment_above'])->toBe('Comment for fluent key');
    expect($item['comment_inline'])->toBe('Inline for fluent');
});

test('set y get con interfaz fluida para actualizar variable existente', function () {
    $initialContent = "EXISTING_KEY=old_value # old_inline\n";
    $this->storage->shouldReceive('read')->with($this->envPath)->andReturn($initialContent);
    $this->manager->load();

    $this->manager->set('EXISTING_KEY', 'new_fluent_value')
        ->commentsAbove('New comment above') // Overwrites if any
        ->commentLine('New inline comment')  // Overwrites
        ->save();

    $item = $this->manager->get('EXISTING_KEY');
    expect($item)->not->toBeNull();
    expect($item['value'])->toBe('new_fluent_value');
    expect($item['comment_above'])->toBe('New comment above');
    expect($item['comment_inline'])->toBe('New inline comment');
});

test('remove elimina una variable y sus comentarios asociados', function () {
    $envContent = <<<'EOL'
# Comment for KEY_TO_REMOVE
KEY_TO_REMOVE=some_value # Inline for KEY_TO_REMOVE
ANOTHER_KEY=another_value
EOL;
    $this->storage->shouldReceive('read')->with($this->envPath)->andReturn($envContent);
    $this->manager->load();

    expect($this->manager->get('KEY_TO_REMOVE'))->not->toBeNull();

    // Verify the formatted output doesn't contain KEY_TO_REMOVE or its comments
    $expectedFormattedContent = "ANOTHER_KEY=another_value\n";
    $this->storage->shouldReceive('write')
        ->with($this->envPath, $expectedFormattedContent)
        ->once() // This expectation might conflict if already set by default, adjust as needed
        ->andReturn(true);

    $this->manager->remove('KEY_TO_REMOVE')->save();

    expect($this->manager->get('KEY_TO_REMOVE'))->toBeNull();
    expect($this->manager->get('ANOTHER_KEY'))->not->toBeNull(); // Ensure other keys remain

    // Re-trigger save if the above expectation is specific for this test's save call
    // Or ensure the default write expectation is flexible enough.
    // For this test, we are verifying the state *after* remove and save.
    // The save() call in remove() already triggered the write.
    // We might need to capture the argument to the default write mock.
});

test('save devuelve false si la escritura falla', function () {
    $this->storage->shouldReceive('read')->with($this->envPath)->andReturn('APP_NAME=Test');
    $this->manager->load();

    // Override the default write expectation for this test
    $this->storage->shouldReceive('write')->with($this->envPath, Mockery::any())->once()->andReturn(false);
    // Backup should still be attempted if enabled
    $this->backupManager->shouldReceive('create')->once()->andReturn(true);

    $result = $this->manager->save();
    expect($result)->toBeFalse();
});

test('load no falla con archivo .env completamente vacío', function () {
    $this->storage->shouldReceive('read')->with($this->envPath)->andReturn('');

    // load() should not throw an exception
    $this->manager->load();

    $collection = $this->manager->asCollection();
    expect($collection)->toBeInstanceOf(Collection::class);
    expect($collection)->toBeEmpty();
});

// In test 'no crea backups cuando están deshabilitados'
test('no crea backups cuando están deshabilitados', function () {
    $this->config->shouldReceive('get')
        ->with('belich-env-manager.backup.enabled', true)
        ->andReturn(false);

    // Create new manager with updated config
    $this->manager = new EnvCollectionManager(
        $this->config,
        $this->backupManager,
        $this->parser,
        $this->storage
    );

    $envContent = 'APP_NAME=Laravel';
    $this->storage->shouldReceive('read')->with($this->envPath)->andReturn($envContent);
    $this->manager->load();

    $this->manager->set('APP_NAME', 'Updated');

    // Add write expectation
    $this->storage->shouldReceive('write')->andReturn(true);

    // Expect no backup creation
    $this->backupManager->shouldNotReceive('create');

    $result = $this->manager->save();
    expect($result)->toBeTrue();
});

// In test 'maneja valores complejos correctamente'
test('maneja valores complejos correctamente', function () {
    $testCases = [
        ['input' => 'simple', 'expected' => 'simple'],
        ['input' => 'with spaces', 'expected' => '"with spaces"'],
        ['input' => 'with#hash', 'expected' => '"with#hash"'],
        ['input' => 'with"quotes', 'expected' => '"with\"quotes"'],
        ['input' => 'with\nnewline', 'expected' => '"with\\nnewline"'],
        ['input' => 'with=equal', 'expected' => '"with=equal"'],
    ];

    foreach ($testCases as $case) {
        $collection = new Collection([
            [
                'key' => 'TEST_VAR',
                'value' => $case['input'],
                'is_comment' => false,
                'comment_above' => null,
                'comment_inline' => null,
            ],
        ]);

        // Add write expectation inside loop
        $expectedLine = "TEST_VAR={$case['expected']}";
        $this->storage->shouldReceive('write')
            ->with($this->envPath, $expectedLine)
            ->andReturn(true);

        $result = $this->manager->updateFileFromCollection($collection);
        expect($result)->toBeTrue();
    }
});

// In test 'mantiene líneas vacías y comentarios sueltos'
test('mantiene líneas vacías y comentarios sueltos', function () {
    $collection = new Collection([
        [
            'key' => null,
            'value' => null,
            'is_comment' => false,
            'comment_above' => null,
            'comment_inline' => null,
        ],
        [
            'key' => null,
            'value' => null,
            'is_comment' => true,
            'comment_above' => null,
            'comment_inline' => 'Standalone comment',
        ],
        [
            'key' => 'APP_NAME',
            'value' => 'Laravel',
            'is_comment' => false,
            'comment_above' => null,
            'comment_inline' => null,
        ],
    ]);

    // Add write expectation
    $expectedContent = <<<'EOL'

# Standalone comment
APP_NAME=Laravel
EOL;

    $this->storage->shouldReceive('write')
        ->with($this->envPath, $expectedContent)
        ->andReturn(true);

    $result = $this->manager->updateFileFromCollection($collection);
    expect($result)->toBeTrue();
});

// In test 'maneja comentarios multilinea correctamente'
test('maneja comentarios multilinea correctamente', function () {
    $collection = new Collection([
        [
            'key' => 'APP_NAME',
            'value' => 'Laravel',
            'is_comment' => false,
            'comment_above' => "First line\nSecond line",
            'comment_inline' => null,
        ],
    ]);

    // Add write expectation
    $expectedContent = <<<'EOL'
# First line
# Second line
APP_NAME=Laravel
EOL;

    $this->storage->shouldReceive('write')
        ->with($this->envPath, $expectedContent)
        ->andReturn(true);

    $result = $this->manager->updateFileFromCollection($collection);
    expect($result)->toBeTrue();
});
