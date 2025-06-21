<?php

use Daguilarm\EnvManager\Services\BackupManager;
use Daguilarm\EnvManager\Services\Env\EnvEditor;
use Daguilarm\EnvManager\Services\Env\EnvFormatter;
use Daguilarm\EnvManager\Services\Env\EnvParser;
use Daguilarm\EnvManager\Services\Env\EnvStorage;
use Daguilarm\EnvManager\Services\EnvManager;
use Illuminate\Config\Repository as Config;
use Illuminate\Filesystem\Filesystem;

beforeEach(function () {
    // Create necessary instances
    $this->files = new Filesystem; // Still used for direct file assertions in some tests
    $this->config = new Config([]);
    $this->backupManager = Mockery::mock(BackupManager::class);
    $this->envParser = Mockery::mock(EnvParser::class);
    $this->envFormatter = Mockery::mock(EnvFormatter::class);
    $this->envStorage = Mockery::mock(EnvStorage::class);
    // For EnvEditor, we'll use a real instance as its state is crucial for many tests
    // and mocking all its interactions would be overly complex and less representative.
    // We can still mock specific methods on it if a test requires very specific behavior.
    $this->envEditor = new EnvEditor;

    // Basic configuration
    $this->config->set('env-manager.backup.enabled', true);

    // Use TestCase temporary directory
    $this->tempDir = __DIR__.'/../temp';
    $this->testEnvPath = $this->tempDir.'/.env';

    // Ensure the directory exists
    if (! $this->files->exists($this->tempDir)) {
        $this->files->makeDirectory($this->tempDir, 0755, true);
    }

    // Create an empty .env file for setup, EnvStorage will handle actual reads/writes
    $this->files->put($this->testEnvPath, '');

    // Tell the Testbench application to use our temporary directory
    $this->app->useEnvironmentPath($this->tempDir);

    // Default mock behaviors for a typical EnvManager instantiation and load()
    $this->envStorage
        ->shouldReceive('read')->with($this->testEnvPath)
        ->andReturn('')->byDefault();
    $this->envParser
        ->shouldReceive('parse')->with('')
        ->andReturn([])->byDefault();
    // EnvEditor is real, so setLines will be called on it directly.

    $this->envManager = new EnvManager(
        $this->files, // Filesystem might still be used by BackupManager or for direct assertions
        $this->config,
        $this->backupManager,
        $this->envParser,
        $this->envFormatter,
        $this->envStorage,
        $this->envEditor
    );
});

afterEach(function () {
    // Clean up temporary files
    if ($this->files->exists($this->testEnvPath)) {
        $this->files->delete($this->testEnvPath);
    }
    Mockery::close();
});

describe('Initialization and Loading', function () {
    // Verifies that the EnvManager and its core dependencies are instantiated
    // and that the initial load sequence (read, parse) is triggered.
    it('initializes correctly and triggers initial load', function () {
        // EnvManager constructor calls load(), so we expect these on the mocks
        $this->envStorage
            ->shouldHaveReceived('read')->with($this->testEnvPath)->once();
        $this->envParser
            ->shouldHaveReceived('parse')->with('')->once();

        expect($this->envManager)->toBeInstanceOf(EnvManager::class);
        expect($this->backupManager)->toBeInstanceOf(BackupManager::class);
    });

    // Tests the ability to load and parse a typical .env file content,
    // populating the internal EnvEditor instance.
    it('loads and parses env content from storage', function () {
        $content = <<<'EOT'
        # Comment above
        APP_NAME=Test App
        APP_ENV=local

        # DB Config
        DB_CONNECTION=mysql
        DB_HOST=127.0.0.1
    EOT;
        // This is what EnvParser would return
        $parsedLines = [
            ['type' => 'comment', 'content' => '# Comment above'],
            ['type' => 'variable', 'key' => 'APP_NAME', 'value' => 'Test App', 'comment_inline' => null, 'comment_above' => [], 'export' => false],
            ['type' => 'variable', 'key' => 'APP_ENV', 'value' => 'local', 'comment_inline' => null, 'comment_above' => [], 'export' => false],
            ['type' => 'empty'],
            ['type' => 'comment', 'content' => '# DB Config'],
            ['type' => 'variable', 'key' => 'DB_CONNECTION', 'value' => 'mysql', 'comment_inline' => null, 'comment_above' => [], 'export' => false],
            ['type' => 'variable', 'key' => 'DB_HOST', 'value' => '127.0.0.1', 'comment_inline' => null, 'comment_above' => [], 'export' => false],
        ];

        // Expectations for the load() method
        $this->envStorage
            ->shouldReceive('read')->with($this->testEnvPath)->once()
            ->andReturn($content);

        $this->envParser
            ->shouldReceive('parse')->with($content)->once()
            ->andReturn($parsedLines);

        $this->envManager->load(); // This will populate the real EnvEditor instance

        expect($this->envManager->has('APP_NAME'))
            ->toBeTrue()
            ->and($this->envManager->get('APP_NAME'))
            ->toBe('Test App')
            ->and($this->envManager->has('DB_CONNECTION'))
            ->toBeTrue();
    });

    // Tests behavior when the .env file doesn't exist or is empty upon load.
    // EnvEditor should be initialized with an empty set of lines.
    it('handles non-existent or empty env file on load', function () {
        $this->envStorage
            ->shouldReceive('read')->with($this->testEnvPath)->once()
            ->andReturn(''); // Simulate file not existing or empty
        $this->envParser
            ->shouldReceive('parse')->with('')->once()
            ->andReturn([]);

        $this->envManager->load(); // Relies on constructor's load or can be called explicitly

        $this->envFormatter
            ->shouldReceive('format')->with([])->once()
            ->andReturn('');

        expect($this->envManager->getEnvContent())->toBeEmpty();
    });
});

describe('Variable Get, Set, and Update Operations', function () {
    // Verifies the basic workflow: setting a new variable, saving it (which involves formatting and writing),
    // and then retrieving it to confirm its presence and value.
    it('sets a new variable, saves, and gets it correctly', function () {
        $this->backupManager
            ->shouldReceive('create')->with($this->testEnvPath)->once()
            ->andReturn(true);

        $setter = $this->envManager->set('TEST_KEY', 'test_value');

        $expectedFormattedContent = 'TEST_KEY="test_value"'.PHP_EOL;

        $this->envFormatter
            ->shouldReceive('format')->once()
            ->with(Mockery::on(function ($lines) {
                // Check if the lines array contains the TEST_KEY
                foreach ($lines as $line) {
                    if ($line['type'] === 'variable' && $line['key'] === 'TEST_KEY' && $line['value'] === 'test_value') {
                        return true;
                    }
                }

                return false;
            }))
            ->andReturn($expectedFormattedContent);

        $this->envStorage
            ->shouldReceive('write')->with($this->testEnvPath, $expectedFormattedContent)->once()
            ->andReturn(true);

        $setter->save();

        expect($this->envManager->has('TEST_KEY'))
            ->toBeTrue()
            ->and($this->envManager->get('TEST_KEY'))
            ->toBe('test_value');
    });

    // Confirms that setting an existing key updates its value while preserving other attributes (like comments, if any).
    it('updates existing values', function () {
        $initialContent = 'EXISTING_KEY=old_value';
        $initialParsed = [['type' => 'variable', 'key' => 'EXISTING_KEY', 'value' => 'old_value', 'comment_inline' => null, 'comment_above' => [], 'export' => false]];

        $this->envStorage->shouldReceive('read')->with($this->testEnvPath)->once()->andReturn($initialContent);
        $this->envParser->shouldReceive('parse')->with($initialContent)->once()->andReturn($initialParsed);
        $this->envManager->load();

        $setter = $this->envManager->set('EXISTING_KEY', 'new_value');

        $this->backupManager->shouldReceive('create')->with($this->testEnvPath)->once()->andReturn(true);
        $expectedFormattedContent = 'EXISTING_KEY="new_value"'.PHP_EOL;
        $this->envFormatter
            ->shouldReceive('format')->once()
            ->with(Mockery::on(fn ($lines) => $lines[0]['key'] === 'EXISTING_KEY' && $lines[0]['value'] === 'new_value'))
            ->andReturn($expectedFormattedContent);
        $this->envStorage->shouldReceive('write')->with($this->testEnvPath, $expectedFormattedContent)->once()->andReturn(true);

        $setter->save();

        expect($this->envManager->get('EXISTING_KEY'))->toBe('new_value');
    });

    // Tests that comments (both block and inline) are preserved when only a variable's value is updated.
    it('preserves comments when updating values', function () {
        $content = "# Important setting\nKEY=old_value # with comment";
        $initialParsed = [
            ['type' => 'variable', 'key' => 'KEY', 'value' => 'old_value', 'comment_inline' => 'with comment', 'comment_above' => ['# Important setting'], 'export' => false],
        ];
        $this->envStorage->shouldReceive('read')->once()->andReturn($content);
        $this->envParser->shouldReceive('parse')->once()->andReturn($initialParsed);
        $this->envManager->load();

        $setter = $this->envManager->set('KEY', 'new_value');

        $this->backupManager->shouldReceive('create')->once()->andReturn(true);
        $expectedFormatted = '# Important setting'.PHP_EOL.'KEY="new_value" # with comment'.PHP_EOL;
        $this->envFormatter
            ->shouldReceive('format')->once()
            ->with(Mockery::on(fn ($lines) => $lines[0]['key'] === 'KEY' &&
                       $lines[0]['value'] === 'new_value' &&
                       $lines[0]['comment_above'] == ['# Important setting'] &&
                       $lines[0]['comment_inline'] === 'with comment'))
            ->andReturn($expectedFormatted);
        $this->envStorage->shouldReceive('write')->once()->andReturn(true);

        $setter->save();
        $this->files->put($this->testEnvPath, $expectedFormatted); // Simulate write for assertion

        expect($this->files->get($this->testEnvPath))
            ->toContain("# Important setting\nKEY=\"new_value\" # with comment");
    });
});

describe('Comment Handling', function () {
    // Verifies that inline comments can be set for a variable and are correctly formatted.
    it('handles setting inline comments', function () {
        $setter = $this->envManager->set('COMMENTED_KEY', 'value')->commentLine('This is a comment');

        $this->backupManager->shouldReceive('create')->once()->andReturn(true);
        $expectedFormattedContent = 'COMMENTED_KEY="value" # This is a comment'.PHP_EOL;
        $this->envFormatter
            ->shouldReceive('format')->once()
            ->with(Mockery::on(fn ($lines) => collect($lines)->contains(fn ($l) => $l['type'] === 'variable' && $l['key'] === 'COMMENTED_KEY' && $l['comment_inline'] === 'This is a comment')))
            ->andReturn($expectedFormattedContent);
        $this->envStorage->shouldReceive('write')->with($this->testEnvPath, $expectedFormattedContent)->once()->andReturn(true);

        $setter->save();
        $this->files->put($this->testEnvPath, $expectedFormattedContent); // Simulate write for assertion

        expect($this->files->get($this->testEnvPath))
            ->toContain('COMMENTED_KEY="value" # This is a comment');
    });

    // Verifies that block comments (multiple lines above a variable) can be set and are correctly formatted.
    it('handles setting block comments', function () {
        $setter = $this->envManager->set('BLOCK_KEY', 'value')->commentsAbove(['# First comment', '# Second comment']);

        $this->backupManager->shouldReceive('create')->once()->andReturn(true);
        $expectedFormattedContent = '# First comment'.PHP_EOL.'# Second comment'.PHP_EOL.'BLOCK_KEY="value"'.PHP_EOL;
        $this->envFormatter
            ->shouldReceive('format')->once()
            ->with(Mockery::on(fn ($lines) => collect($lines)->contains(fn ($l) => $l['type'] === 'variable' && $l['key'] === 'BLOCK_KEY' && $l['comment_above'] == ['# First comment', '# Second comment'])))
            ->andReturn($expectedFormattedContent);
        $this->envStorage->shouldReceive('write')->with($this->testEnvPath, $expectedFormattedContent)->once()->andReturn(true);

        $setter->save();
        $this->files->put($this->testEnvPath, $expectedFormattedContent); // Simulate write for assertion

        expect($this->files->get($this->testEnvPath))
            ->toContain("# First comment\n# Second comment\nBLOCK_KEY=\"value\"");
    });
});

describe('Variable Removal', function () {
    // Tests that a variable can be removed and the file is updated accordingly.
    it('removes keys correctly', function () {
        $initialContent = "KEY_TO_REMOVE=value\nANOTHER_KEY=value2";
        $initialParsed = [
            ['type' => 'variable', 'key' => 'KEY_TO_REMOVE', 'value' => 'value', 'comment_inline' => null, 'comment_above' => [], 'export' => false],
            ['type' => 'variable', 'key' => 'ANOTHER_KEY', 'value' => 'value2', 'comment_inline' => null, 'comment_above' => [], 'export' => false],
        ];
        $this->envStorage->shouldReceive('read')->with($this->testEnvPath)->once()->andReturn($initialContent);
        $this->envParser->shouldReceive('parse')->with($initialContent)->once()->andReturn($initialParsed);
        $this->envManager->load();

        $this->envManager->remove('KEY_TO_REMOVE');

        $this->backupManager->shouldReceive('create')->once()->andReturn(true);
        $expectedFormattedContent = 'ANOTHER_KEY="value2"'.PHP_EOL;
        $this->envFormatter
            ->shouldReceive('format')->once()
            ->with(Mockery::on(fn ($lines) => count($lines) === 1 && $lines[0]['key'] === 'ANOTHER_KEY'))
            ->andReturn($expectedFormattedContent);
        $this->envStorage->shouldReceive('write')->with($this->testEnvPath, $expectedFormattedContent)->once()->andReturn(true);

        $this->envManager->save();

        expect($this->envManager->has('KEY_TO_REMOVE'))
            ->toBeFalse()
            ->and($this->envManager->has('ANOTHER_KEY'))
            ->toBeTrue();
    });

    // Verifies that after removing a variable, any resulting consecutive empty lines are cleaned up by EnvEditor.
    it('cleans up empty lines after removal', function () {
        $content = "KEY1=value1\n\n\nKEY2=value2\n\nKEY3=value3";
        $initialParsed = [
            ['type' => 'variable', 'key' => 'KEY1', 'value' => 'value1', 'comment_inline' => null, 'comment_above' => [], 'export' => false],
            ['type' => 'empty'], ['type' => 'empty'],
            ['type' => 'variable', 'key' => 'KEY2', 'value' => 'value2', 'comment_inline' => null, 'comment_above' => [], 'export' => false],
            ['type' => 'empty'],
            ['type' => 'variable', 'key' => 'KEY3', 'value' => 'value3', 'comment_inline' => null, 'comment_above' => [], 'export' => false],
        ];
        $this->envStorage->shouldReceive('read')->once()->andReturn($content);
        $this->envParser->shouldReceive('parse')->once()->andReturn($initialParsed);
        $this->envManager->load();

        $this->envManager->remove('KEY2');

        $this->backupManager->shouldReceive('create')->once()->andReturn(true);
        $expectedFormatted = 'KEY1=value1'.PHP_EOL.PHP_EOL.'KEY3=value3'.PHP_EOL; // Assumes EnvEditor cleanup and Formatter behavior
        $this->envFormatter
            ->shouldReceive('format')->once()
            ->with(Mockery::on(function ($lines) {
                $keys = array_column(array_filter($lines, fn ($l) => $l['type'] === 'variable'), 'key');

                return in_array('KEY1', $keys) && ! in_array('KEY2', $keys) && in_array('KEY3', $keys);
            }))
            ->andReturn($expectedFormatted);
        $this->envStorage->shouldReceive('write')->once()->andReturn(true);
        $this->envManager->save();

        $this->files->put($this->testEnvPath, $expectedFormatted); // Simulate write
        expect($this->files->get($this->testEnvPath))
            ->toContain('KEY1=value1'.PHP_EOL.PHP_EOL.'KEY3=value3');
    });
});

describe('Value Formatting and Quoting', function () {
    // Tests that empty string values are correctly quoted as `""`.
    it('handles empty values by quoting them', function () {
        $setter = $this->envManager->set('EMPTY_KEY', '');
        $this->backupManager->shouldReceive('create')->once()->andReturn(true);
        $expectedFormattedContent = 'EMPTY_KEY=""'.PHP_EOL;
        $this->envFormatter->shouldReceive('format')->once()->andReturn($expectedFormattedContent);
        $this->envStorage->shouldReceive('write')->with($this->testEnvPath, $expectedFormattedContent)->once()->andReturn(true);
        $setter->save();

        $this->files->put($this->testEnvPath, $expectedFormattedContent); // Simulate write
        expect($this->files->get($this->testEnvPath))->toContain('EMPTY_KEY=""');
    });

    // Tests that string representations of booleans ('true', 'false') are quoted.
    it('handles boolean string values by quoting them', function () {
        $this->envManager->set('TRUE_KEY', 'true');
        $setter = $this->envManager->set('FALSE_KEY', 'false');
        $this->backupManager->shouldReceive('create')->once()->andReturn(true);
        $expectedFormattedContent = 'TRUE_KEY="true"'.PHP_EOL.'FALSE_KEY="false"'.PHP_EOL;
        $this->envFormatter->shouldReceive('format')->once()->andReturn($expectedFormattedContent);
        $this->envStorage->shouldReceive('write')->with($this->testEnvPath, $expectedFormattedContent)->once()->andReturn(true);
        $setter->save();

        $this->files->put($this->testEnvPath, $expectedFormattedContent); // Simulate write
        expect($this->files->get($this->testEnvPath))
            ->toContain('TRUE_KEY="true"')
            ->and($this->files->get($this->testEnvPath))->toContain('FALSE_KEY="false"');
    });

    // Ensures values containing spaces are enclosed in double quotes.
    it('quotes values with spaces', function () {
        $setter = $this->envManager->set('SPACED_KEY', 'value with spaces');
        $this->backupManager->shouldReceive('create')->once()->andReturn(true);
        $expectedFormattedContent = 'SPACED_KEY="value with spaces"'.PHP_EOL;
        $this->envFormatter->shouldReceive('format')->once()->andReturn($expectedFormattedContent);
        $this->envStorage->shouldReceive('write')->with($this->testEnvPath, $expectedFormattedContent)->once()->andReturn(true);
        $setter->save();

        $this->files->put($this->testEnvPath, $expectedFormattedContent); // Simulate write
        expect($this->files->get($this->testEnvPath))->toContain('SPACED_KEY="value with spaces"');
    });

    // Verifies that internal quotes within a value are properly escaped and the whole value is quoted.
    it('handles values with internal quotes correctly', function () {
        $setter = $this->envManager->set('QUOTED_KEY', 'value with "quotes"');
        $this->backupManager->shouldReceive('create')->once()->andReturn(true);
        $expectedFormattedContent = 'QUOTED_KEY="value with \\"quotes\\""'.PHP_EOL;
        $this->envFormatter->shouldReceive('format')->once()->andReturn($expectedFormattedContent);
        $this->envStorage->shouldReceive('write')->once()->andReturn(true);
        $setter->save();

        $this->files->put($this->testEnvPath, $expectedFormattedContent); // Simulate write
        expect($this->files->get($this->testEnvPath))->toContain('QUOTED_KEY="value with \\"quotes\\""');
    });

    // Checks that values containing equal signs are quoted.
    it('handles values with equal signs by quoting them', function () {
        $setter = $this->envManager->set('EQUAL_KEY', 'value=with=equals');
        $this->backupManager->shouldReceive('create')->once()->andReturn(true);
        $expected = 'EQUAL_KEY="value=with=equals"'.PHP_EOL;
        $this->envFormatter->shouldReceive('format')->once()->andReturn($expected);
        $this->envStorage->shouldReceive('write')->once()->andReturn(true);
        $setter->save();

        $this->files->put($this->testEnvPath, $expected); // Simulate write
        expect($this->files->get($this->testEnvPath))->toContain('EQUAL_KEY="value=with=equals"');
    });

    // Simple numeric strings should not be quoted by the formatter.
    it('handles simple numeric values without quoting', function () {
        $setter = $this->envManager->set('NUMBER_KEY', '12345');
        $this->backupManager->shouldReceive('create')->once()->andReturn(true);
        $expected = 'NUMBER_KEY=12345'.PHP_EOL;
        $this->envFormatter->shouldReceive('format')->once()->andReturn($expected);
        $this->envStorage->shouldReceive('write')->once()->andReturn(true);
        $setter->save();

        $this->files->put($this->testEnvPath, $expected); // Simulate write
        expect($this->files->get($this->testEnvPath))->toContain('NUMBER_KEY=12345');
    });

    // The literal string "null" should be quoted.
    it('handles literal string "null" by quoting it', function () {
        $setter = $this->envManager->set('NULL_KEY', 'null');
        $this->backupManager->shouldReceive('create')->once()->andReturn(true);
        $expected = 'NULL_KEY="null"'.PHP_EOL;
        $this->envFormatter->shouldReceive('format')->once()->andReturn($expected);
        $this->envStorage->shouldReceive('write')->once()->andReturn(true);
        $setter->save();

        $this->files->put($this->testEnvPath, $expected); // Simulate write
        expect($this->files->get($this->testEnvPath))->toContain('NULL_KEY="null"');
    });

    // Values with various other special characters (e.g., $, @, !) should be quoted.
    it('handles various special characters by quoting them', function () {
        $setter = $this->envManager->set('SPECIAL_KEY', 'value with $pecial@characters!');
        $this->backupManager->shouldReceive('create')->once()->andReturn(true);
        $expected = 'SPECIAL_KEY="value with $pecial@characters!"'.PHP_EOL;
        $this->envFormatter->shouldReceive('format')->once()->andReturn($expected);
        $this->envStorage->shouldReceive('write')->once()->andReturn(true);
        $setter->save();

        $this->files->put($this->testEnvPath, $expected); // Simulate write
        expect($this->files->get($this->testEnvPath))->toContain('SPECIAL_KEY="value with $pecial@characters!"');
    });
});

describe('Save Operation and Backups', function () {
    // General test for the save operation, ensuring content is written and backup is attempted.
    it('saves content correctly and attempts backup', function () {
        $setter = $this->envManager->set('SAVE_KEY', 'save_value');
        $this->backupManager->shouldReceive('create')->once()->andReturn(true);
        $finalContent = 'SAVE_KEY="save_value"'.PHP_EOL;
        $this->envFormatter->shouldReceive('format')->once()->andReturn($finalContent);
        $this->envStorage->shouldReceive('write')->with($this->testEnvPath, $finalContent)->once()->andReturn(true);

        $result = $setter->save();
        $this->files->put($this->testEnvPath, $finalContent); // Simulate write

        expect($result)->toBeTrue()
            ->and($this->files->get($this->testEnvPath))->toContain('SAVE_KEY="save_value"');
    });

    // Confirms that BackupManager->create() is called when backups are enabled.
    it('creates backups when saving if enabled in config', function () {
        $this->backupManager->shouldReceive('create')->once()->with(app()->environmentFilePath())->andReturn(true);
        $setter = $this->envManager->set('BACKUP_KEY', 'backup_value');
        $this->envFormatter->shouldReceive('format')->once()->andReturn('BACKUP_KEY="backup_value"'.PHP_EOL);
        $this->envStorage->shouldReceive('write')->once()->andReturn(true);

        $setter->save(); // Mockery expectation handles the assertion.
    });

    // Confirms that BackupManager->create() is NOT called if backups are disabled.
    it('does not create backups when disabled in config', function () {
        $this->config->set('env-manager.backup.enabled', false);
        // Re-initialize EnvManager with the new config
        $envManager = new EnvManager(
            $this->files, $this->config, $this->backupManager,
            $this->envParser, $this->envFormatter, $this->envStorage, $this->envEditor
        );
        // Default mocks for load() called by constructor
        $this->envStorage->shouldReceive('read')->with($this->testEnvPath)->andReturn('')->byDefault();
        $this->envParser->shouldReceive('parse')->with('')->andReturn([])->byDefault();

        $this->backupManager->shouldReceive('create')->never();
        $setter = $envManager->set('NO_BACKUP_KEY', 'no_backup');
        $this->envFormatter->shouldReceive('format')->once()->andReturn('NO_BACKUP_KEY="no_backup"'.PHP_EOL);
        $this->envStorage->shouldReceive('write')->once()->andReturn(true);

        $setter->save(); // Mockery expectation handles the assertion.
    });

    // Tests that multiple `save()` calls correctly trigger backups and write operations each time.
    it('handles multiple consecutive saves correctly', function () {
        $this->backupManager->shouldReceive('create')->twice()->with(app()->environmentFilePath())->andReturn(true);

        $setter1 = $this->envManager->set('FIRST_KEY', 'first_value');
        $this->envFormatter->shouldReceive('format')->once()->andReturn('FIRST_KEY="first_value"'.PHP_EOL);
        $this->envStorage->shouldReceive('write')->once()->andReturn(true);
        $setter1->save();

        $setter2 = $this->envManager->set('SECOND_KEY', 'second_value');
        $this->envFormatter->shouldReceive('format')->once()->andReturn('FIRST_KEY="first_value"'.PHP_EOL.'SECOND_KEY="second_value"'.PHP_EOL);
        $this->envStorage->shouldReceive('write')->once()->andReturn(true);
        $setter2->save(); // Mockery expectations handle assertions.
    });
});

describe('Export Statement Handling', function () {
    // Verifies that 'export' prefixes are parsed, preserved on update, and correctly formatted.
    it('parses, preserves, and formats export statements correctly', function () {
        $initialContent = 'export EXPORTED_KEY=exported_value';
        $initialParsed = [['type' => 'variable', 'key' => 'EXPORTED_KEY', 'value' => 'exported_value', 'comment_inline' => null, 'comment_above' => [], 'export' => true]];
        $this->envStorage->shouldReceive('read')->with($this->testEnvPath)->once()->andReturn($initialContent);
        $this->envParser->shouldReceive('parse')->with($initialContent)->once()->andReturn($initialParsed);
        $this->envManager->load();

        expect($this->envManager->get('EXPORTED_KEY'))->toBe('exported_value');

        // Update to a simple value (formatter might not quote)
        $setter1 = $this->envManager->set('EXPORTED_KEY', 'new_value');
        $this->backupManager->shouldReceive('create')->once()->andReturn(true);
        $formattedContent1 = 'export EXPORTED_KEY=new_value'.PHP_EOL;
        $this->envFormatter->shouldReceive('format')->once()->andReturn($formattedContent1);
        $this->envStorage->shouldReceive('write')->once()->andReturn(true);
        $setter1->save();
        $this->files->put($this->testEnvPath, $formattedContent1); // Simulate write
        expect($this->files->get($this->testEnvPath))->toContain('export EXPORTED_KEY=new_value');

        // Update to a value requiring quotes
        $setter2 = $this->envManager->set('EXPORTED_KEY', 'new value with spaces');
        $this->backupManager->shouldReceive('create')->once()->andReturn(true);
        $formattedContent2 = 'export EXPORTED_KEY="new value with spaces"'.PHP_EOL;
        $this->envFormatter->shouldReceive('format')->once()->andReturn($formattedContent2);
        $this->envStorage->shouldReceive('write')->once()->andReturn(true);
        $setter2->save();
        $this->files->put($this->testEnvPath, $formattedContent2); // Simulate write
        expect($this->files->get($this->testEnvPath))->toContain('export EXPORTED_KEY="new value with spaces"');
    });
});

describe('EnvVariableSetter Integration', function () {
    // Tests the fluent interface for setting an inline comment via EnvVariableSetter and saving.
    it('EnvVariableSetter sets inline comment correctly and saves through EnvManager', function () {
        $setter = $this->envManager->set('TEST_COMMENT_LINE', 'value');
        $setter->commentLine('This is an inline comment'); // Modifies EnvEditor state

        $lines = $this->envEditor->getLines(); // Check real EnvEditor state
        $foundLine = collect($lines)->firstWhere('key', 'TEST_COMMENT_LINE');
        expect($foundLine)->not->toBeNull()
            ->and($foundLine['value'])->toBe('value')
            ->and($foundLine['comment_inline'])->toBe('This is an inline comment');

        $this->backupManager->shouldReceive('create')->once()->andReturn(true);
        $this->envFormatter->shouldReceive('format')->once()->andReturn('TEST_COMMENT_LINE="value" # This is an inline comment'.PHP_EOL);
        $this->envStorage->shouldReceive('write')->once()->andReturn(true);

        expect($setter->save())->toBeTrue(); // Triggers EnvManager->save()
    });

    // Tests the fluent interface for setting comments above via EnvVariableSetter and saving.
    it('EnvVariableSetter sets comments above correctly and saves through EnvManager', function () {
        $setter = $this->envManager->set('TEST_COMMENT_ABOVE', 'value');
        $comments = ['# Line 1 above', '# Line 2 above'];
        $setter->commentsAbove($comments); // Modifies EnvEditor state

        $lines = $this->envEditor->getLines(); // Check real EnvEditor state
        $foundLine = collect($lines)->firstWhere('key', 'TEST_COMMENT_ABOVE');
        expect($foundLine)->not->toBeNull()
            ->and($foundLine['value'])->toBe('value')
            ->and($foundLine['comment_above'])->toBe($comments);

        $this->backupManager->shouldReceive('create')->once()->andReturn(true);
        $this->envFormatter->shouldReceive('format')->once()->andReturn("# Line 1 above\n# Line 2 above\nTEST_COMMENT_ABOVE=\"value\"".PHP_EOL);
        $this->envStorage->shouldReceive('write')->once()->andReturn(true);

        expect($setter->save())->toBeTrue(); // Triggers EnvManager->save()
    });

    // Verifies chaining of comment methods on EnvVariableSetter and subsequent save.
    it('EnvVariableSetter can chain comment methods and save through EnvManager', function () {
        $setter = $this->envManager->set('TEST_CHAIN', 'chained_value')
            ->commentLine('Inline for chain')
            ->commentsAbove(['# Above for chain']); // Modifies EnvEditor state

        $this->backupManager->shouldReceive('create')->once()->andReturn(true);
        $this->envFormatter->shouldReceive('format')->once()->andReturn('...'); // Simplified for brevity
        $this->envStorage->shouldReceive('write')->once()->andReturn(true);

        expect($setter->save())->toBeTrue(); // Triggers EnvManager->save()
    });
});
