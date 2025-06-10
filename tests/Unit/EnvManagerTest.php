<?php

use Daguilar\BelichEnvManager\Backup\BackupManager;
use Daguilar\BelichEnvManager\Env\EnvManager;
use Illuminate\Config\Repository as Config;
use Illuminate\Filesystem\Filesystem;

beforeEach(function () {
    // Crear instancias necesarias
    $this->files = new Filesystem;
    $this->config = new Config([]);
    $this->backupManager = Mockery::mock(BackupManager::class);

    // Configuración básica
    $this->config->set('belich-env-manager.backup.enabled', true);

    // Usar directorio temporal del TestCase
    $this->tempDir = __DIR__.'/../temp';
    $this->testBelichEnvPath = $this->tempDir.'/.env';

    // Asegurar que el directorio existe
    if (! $this->files->exists($this->tempDir)) {
        $this->files->makeDirectory($this->tempDir, 0755, true);
    }

    // Crear archivo .env vacío
    $this->files->put($this->testBelichEnvPath, '');

    // Indicar a la aplicación Testbench que use nuestro directorio temporal
    $this->app->useEnvironmentPath($this->tempDir);

    $this->envManager = new EnvManager($this->files, $this->config, $this->backupManager);
});

afterEach(function () {
    // Limpiar archivos temporales
    if ($this->files->exists($this->testBelichEnvPath)) {
        $this->files->delete($this->testBelichEnvPath);
    }
    Mockery::close();
});

it('it initializes correctly', function () {
    expect($this->envManager)->toBeInstanceOf(EnvManager::class);
    expect($this->backupManager)->toBeInstanceOf(backupManager::class);
});

test('it loads and parses env content', function () {
    $content = <<<'EOT'
        # Comment above
        APP_NAME=Test App
        APP_ENV=local

        # DB Config
        DB_CONNECTION=mysql
        DB_HOST=127.0.0.1
    EOT;

    $this->files->put($this->testBelichEnvPath, $content);
    $this->envManager->load();

    expect($this->envManager->has('APP_NAME'))->toBeTrue()
        ->and($this->envManager->get('APP_NAME'))->toBe('Test App')
        ->and($this->envManager->has('DB_CONNECTION'))->toBeTrue();
});

test('it sets and gets values correctly', function () {
    $this->backupManager->shouldReceive('create')->andReturn(true);
    $this->envManager->set('TEST_KEY', 'test_value');
    $this->envManager->save();

    expect($this->envManager->has('TEST_KEY'))->toBeTrue()
        ->and($this->envManager->get('TEST_KEY'))->toBe('test_value');
});

test('it updates existing values', function () {
    $this->backupManager->shouldReceive('create')->andReturn(true);
    $this->files->put($this->testBelichEnvPath, 'EXISTING_KEY=old_value');
    $this->envManager->load();

    $this->envManager->set('EXISTING_KEY', 'new_value');
    $this->envManager->save();

    expect($this->envManager->get('EXISTING_KEY'))->toBe('new_value');
});

test('it handles inline comments', function () {
    $this->backupManager->shouldReceive('create')->andReturn(true);
    $this->envManager->set('COMMENTED_KEY', 'value', 'This is a comment');
    $this->envManager->save();
    $content = $this->files->get($this->testBelichEnvPath);

    expect($content)->toContain('COMMENTED_KEY=value # This is a comment');
});

test('it handles block comments', function () {
    $this->backupManager->shouldReceive('create')->andReturn(true);
    $this->envManager->set('BLOCK_KEY', 'value', null, ['# First comment', '# Second comment']);
    $this->envManager->save();
    $content = $this->files->get($this->testBelichEnvPath);

    expect($content)->toContain("# First comment\n# Second comment\nBLOCK_KEY=value");
});

test('it removes keys correctly', function () {
    $this->backupManager->shouldReceive('create')->andReturn(true);
    $this->files->put($this->testBelichEnvPath, "KEY_TO_REMOVE=value\nANOTHER_KEY=value2");
    $this->envManager->load();

    $this->envManager->remove('KEY_TO_REMOVE');
    $this->envManager->save();

    expect($this->envManager->has('KEY_TO_REMOVE'))->toBeFalse()
        ->and($this->envManager->has('ANOTHER_KEY'))->toBeTrue();
});

test('it handles empty values', function () {
    $this->backupManager->shouldReceive('create')->andReturn(true);
    $this->envManager->set('EMPTY_KEY', '');
    $this->envManager->save();
    $content = $this->files->get($this->testBelichEnvPath);

    expect($content)->toContain('EMPTY_KEY=""');
});

test('it handles boolean values correctly', function () {
    $this->backupManager->shouldReceive('create')->andReturn(true);
    $this->envManager->set('TRUE_KEY', 'true');
    $this->envManager->set('FALSE_KEY', 'false');
    $this->envManager->save();
    $content = $this->files->get($this->testBelichEnvPath);

    expect($content)->toContain('TRUE_KEY="true"')
        ->and($content)->toContain('FALSE_KEY="false"');
});

test('it quotes values with spaces', function () {
    $this->backupManager->shouldReceive('create')->andReturn(true);
    $this->envManager->set('SPACED_KEY', 'value with spaces');
    $this->envManager->save();
    $content = $this->files->get($this->testBelichEnvPath);

    expect($content)->toContain('SPACED_KEY="value with spaces"');
});

test('it saves content correctly', function () {
    $this->backupManager->shouldReceive('create')->andReturn(true);
    $this->envManager->set('SAVE_KEY', 'save_value');
    $result = $this->envManager->save();

    expect($result)->toBeTrue()
        ->and($this->files->get($this->testBelichEnvPath))->toContain('SAVE_KEY=save_value');
});

test('it creates backups when saving if enabled', function () {
    $envPath = app()->environmentFilePath();

    $this->backupManager
        ->shouldReceive('create')->once()
        ->with($envPath)
        ->andReturn(true);

    $this->envManager->set('BACKUP_KEY', 'backup_value');
    $this->envManager->save();
});

test('it does not create backups when disabled', function () {
    $this->config->set('belich-env-manager.backup.enabled', false);
    $envManager = new EnvManager($this->files, $this->config, $this->backupManager);

    $this->backupManager->shouldReceive('create')->never();
    $envManager->set('NO_BACKUP_KEY', 'no_backup')->save();
});

test('it handles non-existent env file', function () {
    $nonExistentPath = $this->tempDir.'/non-existent.env';
    $envManager = new EnvManager($this->files, $this->config, $this->backupManager);

    $reflection = new ReflectionClass($envManager);
    $property = $reflection->getProperty('envPath');
    $property->setAccessible(true);
    $property->setValue($envManager, $nonExistentPath);

    $envManager->load();
    expect($envManager->getEnvContent())->toBeEmpty();
});

test('it handles values with quotes correctly', function () {
    $this->backupManager->shouldReceive('create')->andReturn(true);
    $this->envManager->set('QUOTED_KEY', 'value with "quotes"');
    $this->envManager->save();
    $content = $this->files->get($this->testBelichEnvPath);

    expect($content)->toContain('QUOTED_KEY="value with \"quotes\""');
});

test('it handles export statements correctly', function () {
    $this->backupManager->shouldReceive('create')->andReturn(true);
    $content = 'export EXPORTED_KEY=exported_value';
    $this->files->put($this->testBelichEnvPath, $content);
    $this->envManager->load();

    expect($this->envManager->get('EXPORTED_KEY'))->toBe('exported_value');

    // Update to value without spaces (no quotes)
    $this->envManager->set('EXPORTED_KEY', 'new_value');
    $this->envManager->save();
    $newContent = $this->files->get($this->testBelichEnvPath);
    expect($newContent)->toContain('export EXPORTED_KEY=new_value');

    // Update to value with spaces (requires quotes)
    $this->envManager->set('EXPORTED_KEY', 'new value with spaces');
    $this->envManager->save();
    $newContent = $this->files->get($this->testBelichEnvPath);
    expect($newContent)->toContain('export EXPORTED_KEY="new value with spaces"');
});

test('it cleans up empty lines after removal', function () {
    $this->backupManager->shouldReceive('create')->andReturn(true);
    $content = "KEY1=value1\n\n\nKEY2=value2\n\nKEY3=value3";
    $this->files->put($this->testBelichEnvPath, $content);
    $this->envManager->load();

    $this->envManager->remove('KEY2');
    $this->envManager->save();

    $newContent = $this->files->get($this->testBelichEnvPath);
    $expected = "KEY1=value1\n\nKEY3=value3";
    expect($newContent)->toContain($expected);
});

test('it handles multiple consecutive saves', function () {
    $envPath = app()->environmentFilePath();

    $this->backupManager
        ->shouldReceive('create')
        ->twice()
        ->with($envPath)
        ->andReturn(true);

    $this->envManager->set('FIRST_KEY', 'first_value')->save();
    $this->envManager->set('SECOND_KEY', 'second_value')->save();
});

test('it preserves comments when updating values', function () {
    $this->backupManager->shouldReceive('create')->andReturn(true);
    $content = "# Important setting\nKEY=old_value # with comment";
    $this->files->put($this->testBelichEnvPath, $content);
    $this->envManager->load();

    $this->envManager->set('KEY', 'new_value');
    $this->envManager->save();

    $newContent = $this->files->get($this->testBelichEnvPath);
    expect($newContent)->toContain("# Important setting\nKEY=new_value # with comment");
});

// Test for values with equal signs
test('it handles values with equal signs', function () {
    $this->backupManager->shouldReceive('create')->andReturn(true);
    $this->envManager->set('EQUAL_KEY', 'value=with=equals');
    $this->envManager->save();
    $content = $this->files->get($this->testBelichEnvPath);

    expect($content)->toContain('EQUAL_KEY="value=with=equals"');
});

// Test for numeric values
test('it handles numeric values correctly', function () {
    $this->backupManager->shouldReceive('create')->andReturn(true);
    $this->envManager->set('NUMBER_KEY', 12345);
    $this->envManager->save();
    $content = $this->files->get($this->testBelichEnvPath);

    expect($content)->toContain('NUMBER_KEY=12345');
});

// Test for null values
test('it handles null values correctly', function () {
    $this->backupManager->shouldReceive('create')->andReturn(true);
    $this->envManager->set('NULL_KEY', 'null');
    $this->envManager->save();
    $content = $this->files->get($this->testBelichEnvPath);

    expect($content)->toContain('NULL_KEY="null"');
});

// Test for special characters
test('it handles special characters correctly', function () {
    $this->backupManager->shouldReceive('create')->andReturn(true);
    $this->envManager->set('SPECIAL_KEY', 'value with $pecial@characters!');
    $this->envManager->save();
    $content = $this->files->get($this->testBelichEnvPath);

    expect($content)->toContain('SPECIAL_KEY="value with $pecial@characters!"');
});
