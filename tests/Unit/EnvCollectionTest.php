<?php

// Unit tests for the EnvCollectionManager class.
// This class is responsible for managing .env file content as a collection,
// allowing for easier manipulation and transformation of environment variables,
// comments, and empty lines.

use Daguilar\EnvManager\Services\BackupManager;
use Daguilar\EnvManager\Services\Env\EnvParser;
use Daguilar\EnvManager\Services\Env\EnvStorage;
use Daguilar\EnvManager\Services\EnvCollectionManager;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;

// Setup common mocks and configurations before each test.
beforeEach(function () {
    // Mock the dependencies
    $this->config = Mockery::mock(ConfigRepository::class);
    $this->backupManager = Mockery::mock(BackupManager::class);
    // Add default expectation for backup creation
    $this->backupManager->shouldReceive('create')->andReturn(true)->byDefault();
    $this->parser = new EnvParser;
    $this->storage = Mockery::mock(EnvStorage::class);
    $this->filesystem = Mockery::mock(Filesystem::class);

    // Basic configuration
    $this->envPath = base_path('.env');
    // Add default expectation for storage write
    $this->storage->shouldReceive('write')->with($this->envPath, Mockery::any())->andReturn(true)->byDefault();
    // Ensure backup is configured as enabled by default for most tests.
    $this->config->shouldReceive('get')->with('env-manager.backup.enabled', true)->once()->andReturn(true);

    // EnvCollectionManager instance
    $this->manager = new EnvCollectionManager(
        $this->config,
        $this->backupManager,
        $this->parser,
        $this->storage
    );
});

// Clean up mocks after each test.
afterEach(function () {
    Mockery::close();
});

describe('File Loading and Parsing', function () {
    it('correctly loads the file contents from .env', function () {
        // Simulates reading a typical .env file structure.
        $envContent = <<<'EOL'
        # App Config
        APP_NAME=Laravel # App name
        APP_ENV=local

        # Database
        DB_CONNECTION=mysql
        DB_HOST=127.0.0.1
        EOL;

        $this->storage->shouldReceive('read')->with($this->envPath)->andReturn($envContent);
        // Trigger the load operation which internally parses the content.
        $this->manager->load();

        $collection = $this->manager->getEnvContent();

        expect($collection)->toBeInstanceOf(Collection::class);
        // Verifies that all lines (variables, comments, empty lines) are parsed.
        expect($collection)->toHaveCount(5);
    });

    it('correctly transforms the content to collection', function () {
        // Tests the structure of the parsed collection items.
        $envContent = <<<'EOL'
        # App Name
        APP_NAME="Laravel" # Application name

        APP_ENV=production
        EOL;

        $this->storage->shouldReceive('read')->with($this->envPath)->andReturn($envContent);
        $this->manager->load();

        $collection = $this->manager->getEnvContent();

        // Check structure for a variable with both comments above and inline.
        $appName = $collection[0];
        expect($appName['key'])->toBe('APP_NAME');
        expect($appName['value'])->toBe('Laravel');
        expect($appName['comment_above'])->toBe('# App Name');
        expect($appName['comment_inline'])->toBe('Application name');

        // Check structure for a parsed empty line.
        $emptyLine = $collection[1];
        expect($emptyLine['key'])->toBeNull();
        expect($emptyLine['value'])->toBeNull();
        expect($emptyLine['is_comment'])->toBeFalse();

        // Check structure for a simple variable without comments.
        $appEnv = $collection[2];
        expect($appEnv['key'])->toBe('APP_ENV');
        expect($appEnv['value'])->toBe('production');
        expect($appEnv['comment_above'])->toBeNull();
        expect($appEnv['comment_inline'])->toBeNull();
    });

    it('does not fail with completely empty .env file', function () {
        // Edge case: ensures robustness when the .env file is empty.
        $this->storage->shouldReceive('read')->with($this->envPath)->andReturn('');

        $this->manager->load();

        $collection = $this->manager->getEnvContent();
        expect($collection)->toBeInstanceOf(Collection::class);
        expect($collection)->toBeEmpty();
    });
});

describe('Collection Updates', function () {
    // Tests the ability to replace the internal collection with an external one.
    it('updates from an external collection', function () {
        // Define a new collection structure to replace the existing one.
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

        $this->storage->shouldReceive('write')->andReturn(true);

        // Perform the update and verify the internal state.
        $this->manager->updateFileFromCollection($newCollection);
        $collection = $this->manager->getEnvContent();

        expect($collection)->toHaveCount(2);
        expect($collection[0]['key'])->toBe('APP_NAME');
        expect($collection[1]['key'])->toBe('NEW_KEY');
    });

    // Verifies that the internal collection is correctly formatted back into a string for .env file.
    it('correctly formats the collection to .env content', function () {
        // A collection with various types: standalone comment, variable with multiline above and inline comments, and a variable with special characters.
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

        // Expected string output after formatting. Note the handling of quotes and newlines.
        $expectedContent = <<<EOL
        # Top comment
        # App Name
        # Second line
        APP_NAME="Laravel" # Inline comment
        ESCAPED_VALUE="value with spaces#and#special\"chars"
        EOL;

        $this->storage->shouldReceive('write')
            // Assert that the storage `write` method is called with the correctly formatted string.
            ->with($this->envPath, $expectedContent)
            ->andReturn(true);

        $result = $this->manager->updateFileFromCollection($collection);
        expect($result)->toBeTrue();
    });
});

describe('Variable Operations', function () {
    it('gets elements by key and returns null if it does not exist', function () {
        // Basic get operation test.
        $this->storage->shouldReceive('read')->with($this->envPath)->andReturn('APP_NAME=Laravel');
        $this->manager->load();

        $item = $this->manager->get('NON_EXISTING_KEY');
        expect($item)->toBeNull();
    });

    // Tests the fluent interface for setting a new variable with comments and saving.
    it('sets and gets with fluid interface for new variable', function () {
        // Start with an empty .env content.
        $this->storage->shouldReceive('read')->with($this->envPath)->andReturn('');
        $this->manager->load();

        $this->manager->set('NEW_FLUENT_KEY', 'fluent_value')
            ->commentsAbove('Comment for fluent key')
            ->commentLine('Inline for fluent')
            ->save();

        // Verify the variable was added correctly to the internal collection.
        $item = $this->manager->get('NEW_FLUENT_KEY');
        expect($item)->not->toBeNull();
        expect($item['value'])->toBe('fluent_value');
        expect($item['comment_above'])->toBe('Comment for fluent key');
        expect($item['comment_inline'])->toBe('Inline for fluent');
    });

    // Tests updating an existing variable using the fluent interface.
    it('sets and gets with fluid interface to update existing variable', function () {
        // Initial .env content with an existing key.
        $initialContent = "EXISTING_KEY=old_value # old_inline\n";
        $this->storage->shouldReceive('read')->with($this->envPath)->andReturn($initialContent);
        $this->manager->load();

        $this->manager->set('EXISTING_KEY', 'new_fluent_value')
            ->commentsAbove('New comment above')
            ->commentLine('New inline comment')
            ->save();

        // Verify the existing variable was updated correctly.
        $item = $this->manager->get('EXISTING_KEY');
        expect($item)->not->toBeNull();
        expect($item['value'])->toBe('new_fluent_value');
        expect($item['comment_above'])->toBe('New comment above');
        expect($item['comment_inline'])->toBe('New inline comment');
    });

    // Tests the removal of a variable and ensures its comments are also removed.
    it('removes a variable and its associated comments', function () {
        // .env content with a key to be removed, including comments.
        $envContent = <<<'EOL'
        # Comment for KEY_TO_REMOVE
        KEY_TO_REMOVE=some_value # Inline for KEY_TO_REMOVE
        ANOTHER_KEY=another_value
        EOL;

        $this->storage->shouldReceive('read')->with($this->envPath)->andReturn($envContent);
        $this->manager->load();

        expect($this->manager->get('KEY_TO_REMOVE'))->not->toBeNull();

        // Expected content after removal. Only the other key should remain.
        $expectedFormattedContent = "ANOTHER_KEY=another_value\n";
        $this->storage->shouldReceive('write')
            ->with($this->envPath, $expectedFormattedContent)
            ->once()
            ->andReturn(true);

        $this->manager->remove('KEY_TO_REMOVE')->save();

        // Verify the key is no longer in the collection.
        expect($this->manager->get('KEY_TO_REMOVE'))->toBeNull();
        expect($this->manager->get('ANOTHER_KEY'))->not->toBeNull();
    });
});

describe('Backup Handling', function () {
    it('does not create backups when they are disabled', function () {
        // Override the default config to disable backups for this test.
        $this->config->shouldReceive('get')
            // This expectation for `true` is for the `beforeEach` setup.
            ->with('env-manager.backup.enabled', true)
            ->andReturn(false);

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

        $this->storage->shouldReceive('write')->andReturn(true);
        // Crucial assertion: backupManager->create should not be called.
        $this->backupManager->shouldNotReceive('create');

        $result = $this->manager->save();
        expect($result)->toBeTrue();
    });

    it('returns false if the write fails', function () {
        // Simulate a scenario where writing to the .env file fails.
        $this->storage->shouldReceive('read')->with($this->envPath)->andReturn('APP_NAME=Test');
        $this->manager->load();

        // Mock storage write to return false (failure).
        $this->storage->shouldReceive('write')->with($this->envPath, Mockery::any())->once()->andReturn(false);
        // Backup should still be attempted before the write failure is known.
        $this->backupManager->shouldReceive('create')->once()->andReturn(true);

        $result = $this->manager->save();
        expect($result)->toBeFalse();
    });
});

describe('Value Handling', function () {
    // Tests how various types of values (with spaces, special characters, etc.) are formatted.
    it('handles complex values correctly', function () {
        // Data provider for different input values and their expected formatted output.
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

            // The expected line in the .env file.
            $expectedLine = "TEST_VAR={$case['expected']}";
            $this->storage->shouldReceive('write')
                ->with($this->envPath, $expectedLine)
                ->andReturn(true);

            $result = $this->manager->updateFileFromCollection($collection);
            expect($result)->toBeTrue();
        }
    });

    // Ensures that empty lines and standalone comments are preserved during formatting.
    it('maintains empty lines and standalone comments', function () {
        // Collection containing an empty line, a standalone comment, and a variable.
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

        // Expected .env content, preserving the empty line and comment.
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

    // Verifies correct formatting of multiline comments (comments_above).
    it('handles multiline comments correctly', function () {
        // A variable with a multiline comment above it.
        $collection = new Collection([
            [
                'key' => 'APP_NAME',
                'value' => 'Laravel',
                'is_comment' => false,
                'comment_above' => "First line\nSecond line",
                'comment_inline' => null,
            ],
        ]);

        // Each line of the 'comment_above' should be prefixed with '# '.
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
});
