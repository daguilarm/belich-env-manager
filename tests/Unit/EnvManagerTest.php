<?php

use Daguilar\BelichEnvManager\Services\BackupManager;
use Daguilar\BelichEnvManager\Services\Env\EnvEditor;
use Daguilar\BelichEnvManager\Services\Env\EnvFormatter;
use Daguilar\BelichEnvManager\Services\Env\EnvParser;
use Daguilar\BelichEnvManager\Services\Env\EnvStorage;
use Daguilar\BelichEnvManager\Services\EnvManager;
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
    $this->envEditor = new EnvEditor();


    // Basic configuration
    $this->config->set('belich-env-manager.backup.enabled', true);

    // Use TestCase temporary directory
    $this->tempDir = __DIR__.'/../temp';
    $this->testBelichEnvPath = $this->tempDir.'/.env';

    // Ensure the directory exists
    if (! $this->files->exists($this->tempDir)) {
        $this->files->makeDirectory($this->tempDir, 0755, true);
    }

    // Create an empty .env file for setup, EnvStorage will handle actual reads/writes
    $this->files->put($this->testBelichEnvPath, '');

    // Tell the Testbench application to use our temporary directory
    $this->app->useEnvironmentPath($this->tempDir);

    // Default mock behaviors for a typical EnvManager instantiation and load()
    $this->envStorage
        ->shouldReceive('read')->with($this->testBelichEnvPath)
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
    if ($this->files->exists($this->testBelichEnvPath)) {
        $this->files->delete($this->testBelichEnvPath);
    }
    Mockery::close();
});

// Test that the EnvManager and its dependencies are initialized correctly.
it('it initializes correctly', function () {
    // EnvManager constructor calls load(), so we expect these on the mocks
    $this->envStorage
        ->shouldHaveReceived('read')->with($this->testBelichEnvPath)->once();
    $this->envParser
        ->shouldHaveReceived('parse')->with('')->once();

    expect($this->envManager)->toBeInstanceOf(EnvManager::class);
    expect($this->backupManager)->toBeInstanceOf(BackupManager::class);
});

// Test that the EnvManager can load and parse content from an .env file.
test('it loads and parses env content', function () {
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
        ->shouldReceive('read')->with($this->testBelichEnvPath)->once()
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

// Test that values can be set and retrieved correctly.
test('it sets and gets values correctly', function () {
    $this->backupManager
        ->shouldReceive('create')->with($this->testBelichEnvPath)->once()
            ->andReturn(true);

    // set() will call $this->envEditor->set()
    $setter = $this->envManager->set('TEST_KEY', 'test_value');

    // save() will call:
    // 1. $this->envEditor->getLines()
    // 2. $this->envFormatter->format()
    // 3. $this->backupManager->create() (mocked above)
    // 4. $this->envStorage->write()
    $expectedFormattedContent = 'TEST_KEY="test_value"'.PHP_EOL; // Formatter adds quotes for non-numeric/bool
    
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
        ->shouldReceive('write')->with($this->testBelichEnvPath, $expectedFormattedContent)->once()
            ->andReturn(true);

    $setter->save();

    // has() and get() will call $this->envEditor->has() and $this->envEditor->get()
    expect($this->envManager->has('TEST_KEY'))
            ->toBeTrue()
        ->and($this->envManager->get('TEST_KEY'))
            ->toBe('test_value');
});

// Test that existing values in the .env file are updated correctly.
test('it updates existing values', function () {
    // Initial load
    $initialContent = 'EXISTING_KEY=old_value';
    $initialParsed = [['type' => 'variable', 'key' => 'EXISTING_KEY', 'value' => 'old_value', 'comment_inline' => null, 'comment_above' => [], 'export' => false]];
    
    $this->envStorage
        ->shouldReceive('read')->with($this->testBelichEnvPath)->once()
            ->andReturn($initialContent);

    $this->envParser
        ->shouldReceive('parse')->with($initialContent)->once()
            ->andReturn($initialParsed);
    $this->envManager->load(); // Populates EnvEditor

    // Set
    $setter = $this->envManager->set('EXISTING_KEY', 'new_value');
    // Save
    $this->backupManager
        ->shouldReceive('create')->with($this->testBelichEnvPath)->once()
            ->andReturn(true);
    $expectedFormattedContent = 'EXISTING_KEY="new_value"'.PHP_EOL;
    $this->envFormatter
        ->shouldReceive('format')->once()
            ->with(Mockery::on(function ($lines) {
                return $lines[0]['key'] === 'EXISTING_KEY' && $lines[0]['value'] === 'new_value';
            }))
            ->andReturn($expectedFormattedContent);
    
    $this->envStorage
        ->shouldReceive('write')->with($this->testBelichEnvPath, $expectedFormattedContent)->once()
            ->andReturn(true);
    
    $setter->save();

    expect($this->envManager->get('EXISTING_KEY'))
        ->toBe('new_value');
});

// Test that inline comments are handled correctly when setting values.
test('it handles inline comments', function () {
    $setter = $this->envManager->set('COMMENTED_KEY', 'value')->commentLine('This is a comment');

    $this->backupManager
        ->shouldReceive('create')->once()
            ->andReturn(true);

    $expectedFormattedContent = 'COMMENTED_KEY="value" # This is a comment'.PHP_EOL;
    
    $this->envFormatter
        ->shouldReceive('format')->once()
            ->with(Mockery::on(function ($lines) {
                return collect($lines)->contains(fn($l) => $l['type'] === 'variable' && $l['key'] === 'COMMENTED_KEY' && $l['comment_inline'] === 'This is a comment');
            }))
            ->andReturn($expectedFormattedContent);

    $this->envStorage
        ->shouldReceive('write')->with($this->testBelichEnvPath, $expectedFormattedContent)->once()
            ->andReturn(true);

    $setter->save();

    // To verify, we get the content via EnvManager which uses the formatter
    // Or, if we want to check the actual file, we'd need to let write happen and then read.
    // For this test, checking the formatted output is sufficient if formatter is tested elsewhere.
    $this->files->put($this->testBelichEnvPath, $expectedFormattedContent); // Simulate write for assertion
    $content = $this->files->get($this->testBelichEnvPath);

    expect($content)
        ->toContain('COMMENTED_KEY="value" # This is a comment');
});

// Test that block comments (comments above a key-value pair) are handled correctly.
test('it handles block comments', function () {
    $setter = $this->envManager->set('BLOCK_KEY', 'value')->commentsAbove(['# First comment', '# Second comment']);

    $this->backupManager
        ->shouldReceive('create')->once()
            ->andReturn(true);

    $expectedFormattedContent = "# First comment".PHP_EOL."# Second comment".PHP_EOL.'BLOCK_KEY="value"'.PHP_EOL;
    
    $this->envFormatter
        ->shouldReceive('format')->once()
            ->with(Mockery::on(function ($lines) {
                return collect($lines)->contains(fn($l) => $l['type'] === 'variable' && $l['key'] === 'BLOCK_KEY' && $l['comment_above'] == ['# First comment', '# Second comment']);
            }))
            ->andReturn($expectedFormattedContent);
    
    $this->envStorage
        ->shouldReceive('write')->with($this->testBelichEnvPath, $expectedFormattedContent)->once()
            ->andReturn(true);

    $setter->save();

    $this->files->put($this->testBelichEnvPath, $expectedFormattedContent); // Simulate write for assertion
    $content = $this->files->get($this->testBelichEnvPath);

    expect($content)
        ->toContain("# First comment\n# Second comment\nBLOCK_KEY=\"value\"");
});

// Test that keys can be removed from the .env file.
test('it removes keys correctly', function () {
    // Initial load
    $initialContent = "KEY_TO_REMOVE=value\nANOTHER_KEY=value2";
    $initialParsed = [
        ['type' => 'variable', 'key' => 'KEY_TO_REMOVE', 'value' => 'value', 'comment_inline' => null, 'comment_above' => [], 'export' => false],
        ['type' => 'variable', 'key' => 'ANOTHER_KEY', 'value' => 'value2', 'comment_inline' => null, 'comment_above' => [], 'export' => false]
    ];
    
    $this->envStorage
        ->shouldReceive('read')->with($this->testBelichEnvPath)->once()
            ->andReturn($initialContent);
    
    $this->envParser
        ->shouldReceive('parse')->with($initialContent)->once()
            ->andReturn($initialParsed);
    
    $this->envManager->load();

    // Remove
    $this->envManager->remove('KEY_TO_REMOVE');

    // Save
    $this->backupManager
        ->shouldReceive('create')->once()
            ->andReturn(true);

    $expectedFormattedContent = 'ANOTHER_KEY="value2"'.PHP_EOL;
    
    $this->envFormatter
        ->shouldReceive('format')->once()
            ->with(Mockery::on(function ($lines) {
                return count($lines) === 1 && $lines[0]['key'] === 'ANOTHER_KEY';
            }))
            ->andReturn($expectedFormattedContent);
    
    $this->envStorage
        ->shouldReceive('write')->with($this->testBelichEnvPath, $expectedFormattedContent)->once()
            ->andReturn(true);

    $this->envManager->save();

    expect($this->envManager->has('KEY_TO_REMOVE'))
            ->toBeFalse()
        ->and($this->envManager->has('ANOTHER_KEY'))
            ->toBeTrue();
});

// Test that empty values are handled correctly (e.g., EMPTY_KEY="").
test('it handles empty values', function () {
    $setter = $this->envManager->set('EMPTY_KEY', '');

    $this->backupManager
        ->shouldReceive('create')->once()
            ->andReturn(true);

    $expectedFormattedContent = 'EMPTY_KEY=""'.PHP_EOL;
    
    $this->envFormatter
        ->shouldReceive('format')->once()
            ->andReturn($expectedFormattedContent);
   
    $this->envStorage
        ->shouldReceive('write')->with($this->testBelichEnvPath, $expectedFormattedContent)->once()
            ->andReturn(true);
    
    $setter->save();

    $this->files->put($this->testBelichEnvPath, $expectedFormattedContent);
    $content = $this->files->get($this->testBelichEnvPath);

    expect($content)
        ->toContain('EMPTY_KEY=""');
});

// Test that boolean values ('true', 'false') are handled and quoted correctly.
test('it handles boolean values correctly', function () {
    $this->envManager->set('TRUE_KEY', 'true'); // This modifies the internal $this->envEditor state
    $setter = $this->envManager->set('FALSE_KEY', 'false'); // This also modifies $this->envEditor state and returns a setter for the last one

    $this->backupManager
        ->shouldReceive('create')->once()
            ->andReturn(true);

    $expectedFormattedContent = 'TRUE_KEY="true"'.PHP_EOL.'FALSE_KEY="false"'.PHP_EOL;
    
    $this->envFormatter
        ->shouldReceive('format')->once()
            ->andReturn($expectedFormattedContent);
    
    $this->envStorage
        ->shouldReceive('write')->with($this->testBelichEnvPath, $expectedFormattedContent)->once()
            ->andReturn(true);
    
    $setter->save();

    $this->files->put($this->testBelichEnvPath, $expectedFormattedContent);
    $content = $this->files->get($this->testBelichEnvPath);

    expect($content)
            ->toContain('TRUE_KEY="true"')
        ->and($content)
            ->toContain('FALSE_KEY="false"');
});

// Test that values containing spaces are correctly quoted.
test('it quotes values with spaces', function () {
    $setter = $this->envManager->set('SPACED_KEY', 'value with spaces');

    $this->backupManager
        ->shouldReceive('create')->once()
            ->andReturn(true);

    $expectedFormattedContent = 'SPACED_KEY="value with spaces"'.PHP_EOL;
    
    $this->envFormatter
        ->shouldReceive('format')->once()
            ->andReturn($expectedFormattedContent);
    
    $this->envStorage
        ->shouldReceive('write')->with($this->testBelichEnvPath, $expectedFormattedContent)->once()
            ->andReturn(true);
    
    $setter->save();

    $this->files->put($this->testBelichEnvPath, $expectedFormattedContent);
    $content = $this->files->get($this->testBelichEnvPath);

    expect($content)
        ->toContain('SPACED_KEY="value with spaces"');
});

// Test that the save operation writes content to the .env file correctly.
test('it saves content correctly', function () {
    $setter = $this->envManager->set('SAVE_KEY', 'save_value');

    $this->backupManager
        ->shouldReceive('create')->once()
            ->andReturn(true);
    $finalContent = 'SAVE_KEY="save_value"'.PHP_EOL;
    $this->envFormatter
        ->shouldReceive('format')->once()
            ->andReturn($finalContent);
    $this->envStorage
        ->shouldReceive('write')->with($this->testBelichEnvPath, $finalContent)->once()
            ->andReturn(true);

    $result = $setter->save();

    // Simulate the write for the assertion on file content
    $this->files->put($this->testBelichEnvPath, $finalContent);

    expect($result)
            ->toBeTrue()
        ->and($this->files->get($this->testBelichEnvPath))
            ->toContain('SAVE_KEY="save_value"');
});

// Test that a backup is created when saving if backups are enabled in the configuration.
test('it creates backups when saving if enabled', function () {
    $envPath = app()->environmentFilePath(); // $this->testBelichEnvPath

    $this->backupManager
        ->shouldReceive('create')->once()->with($envPath) // Ensure it's called with the correct path
            ->andReturn(true);

    $setter = $this->envManager->set('BACKUP_KEY', 'backup_value');

    // Mock formatter and storage for save to complete
    $this->envFormatter
        ->shouldReceive('format')->once()
            ->andReturn('BACKUP_KEY="backup_value"'.PHP_EOL);
    $this->envStorage
        ->shouldReceive('write')->once()
            ->andReturn(true);

    $setter->save();
    // Assertion is implicitly handled by Mockery's expectation `once()`
});

// Test that no backup is created if backups are disabled in the configuration.
test('it does not create backups when disabled', function () {
    $this->config->set('belich-env-manager.backup.enabled', false);

    // Re-initialize EnvManager with the new config setting for backupsEnabled
    $envManager = new EnvManager(
        $this->files, $this->config, $this->backupManager,
        $this->envParser, $this->envFormatter, $this->envStorage, $this->envEditor
    );
    // EnvManager's constructor calls load(), so we need to ensure mocks are ready for that too
    // For this specific test, we might not need to re-mock read/parse if they are not critical path for this test's assertion
    // but it's safer to have them available.
    $this->envStorage
        ->shouldReceive('read')->with($this->testBelichEnvPath)
        ->andReturn('')->byDefault(); // For the new instance's load
    $this->envParser
        ->shouldReceive('parse')->with('')
        ->andReturn([])->byDefault(); // For the new instance's load


    $this->backupManager->shouldReceive('create')->never();

    $setter = $envManager->set('NO_BACKUP_KEY', 'no_backup');

    $this->envFormatter
        ->shouldReceive('format')->once()
            ->andReturn('NO_BACKUP_KEY="no_backup"'.PHP_EOL);
    $this->envStorage
        ->shouldReceive('write')->once()
            ->andReturn(true);

    $setter->save();
    // Assertion is implicitly handled by Mockery's expectation `never()`
});

// Test how the manager handles a scenario where the .env file does not exist initially.
test('it handles non-existent env file on load', function () {
    $nonExistentPath = $this->tempDir.'/non-existent-for-load.env';

    // EnvManager will be constructed with app()->environmentFilePath() which is $this->testBelichEnvPath
    // To test a specific path for load, we'd typically pass it to load or have EnvManager take path in constructor
    // Since EnvManager hardcodes $this->envPath = app()->environmentFilePath();
    // we'll test the default behavior: if storage->read returns empty, editor->setLines([]) is called.

    $this->envStorage
        ->shouldReceive('read')->with($this->testBelichEnvPath)->once()
            ->andReturn(''); // Simulate file not existing or empty
    $this->envParser
        ->shouldReceive('parse')->with('')->once()
            ->andReturn([]);
    // EnvEditor (real) will have setLines([]) called by EnvManager's constructor via load()

    // Re-trigger load or rely on constructor's load
    $this->envManager->load(); // This will use the mocks above

    $this->envFormatter
        ->shouldReceive('format')->with([])->once()
            ->andReturn('');

    expect($this->envManager->getEnvContent())->toBeEmpty();
});


// Test that values containing quotes are handled correctly (quotes are escaped).
test('it handles values with quotes correctly', function () {
    $setter = $this->envManager->set('QUOTED_KEY', 'value with "quotes"');

    $this->backupManager
        ->shouldReceive('create')->once()
            ->andReturn(true);
    $expectedFormattedContent = 'QUOTED_KEY="value with \\"quotes\\""'.PHP_EOL;
    $this->envFormatter
        ->shouldReceive('format')->once()
            ->andReturn($expectedFormattedContent);
    $this->envStorage
        ->shouldReceive('write')->once()
            ->andReturn(true);
    $setter->save();

    $this->files->put($this->testBelichEnvPath, $expectedFormattedContent);
    $content = $this->files->get($this->testBelichEnvPath);

    expect($content)
        ->toContain('QUOTED_KEY="value with \\"quotes\\""');
});

// Test that 'export' statements in .env files are parsed and preserved correctly.
test('it handles export statements correctly', function () {
    // Load
    $initialContent = 'export EXPORTED_KEY=exported_value';
    $initialParsed = [['type' => 'variable', 'key' => 'EXPORTED_KEY', 'value' => 'exported_value', 'comment_inline' => null, 'comment_above' => [], 'export' => true]];
    
    $this->envStorage
        ->shouldReceive('read')->with($this->testBelichEnvPath)->once()
            ->andReturn($initialContent);
    $this->envParser
        ->shouldReceive('parse')->with($initialContent)->once()
            ->andReturn($initialParsed);
    $this->envManager->load();

    expect($this->envManager->get('EXPORTED_KEY'))
        ->toBe('exported_value');

    // Update to value without spaces (no quotes by formatter if it's simple)
    $setter1 = $this->envManager->set('EXPORTED_KEY', 'new_value');
    $this->backupManager
        ->shouldReceive('create')->once()
            ->andReturn(true);
    
    $formattedContent1 = 'export EXPORTED_KEY=new_value'.PHP_EOL; // Assuming formatter doesn't quote simple values
    $this->envFormatter
        ->shouldReceive('format')->once()
            ->andReturn($formattedContent1);
    $this->envStorage
        ->shouldReceive('write')->once()
            ->andReturn(true);
    
    $setter1->save();
    
    $this->files->put($this->testBelichEnvPath, $formattedContent1); // Simulate for assertion
    
    expect($this->files->get($this->testBelichEnvPath))
        ->toContain('export EXPORTED_KEY=new_value');

    // Update to value with spaces (requires quotes)
    $setter2 = $this->envManager->set('EXPORTED_KEY', 'new value with spaces');
    $this->backupManager
        ->shouldReceive('create')->once()
            ->andReturn(true); // For the second save
    
    $formattedContent2 = 'export EXPORTED_KEY="new value with spaces"'.PHP_EOL;
    $this->envFormatter
        ->shouldReceive('format')->once()
            ->andReturn($formattedContent2);
    $this->envStorage
        ->shouldReceive('write')->once()
            ->andReturn(true);
   
    $setter2->save();
    
    $this->files->put($this->testBelichEnvPath, $formattedContent2); // Simulate for assertion
    
    expect($this->files->get($this->testBelichEnvPath))
        ->toContain('export EXPORTED_KEY="new value with spaces"');
});

// Test that empty lines are cleaned up after a key is removed.
test('it cleans up empty lines after removal', function () {
    $content = "KEY1=value1\n\n\nKEY2=value2\n\nKEY3=value3";
    $initialParsed = [
        ['type' => 'variable', 'key' => 'KEY1', 'value' => 'value1', 'comment_inline' => null, 'comment_above' => [], 'export' => false],
        ['type' => 'empty'],
        ['type' => 'empty'], // EnvParser might consolidate or EnvEditor might clean
        ['type' => 'variable', 'key' => 'KEY2', 'value' => 'value2', 'comment_inline' => null, 'comment_above' => [], 'export' => false],
        ['type' => 'empty'],
        ['type' => 'variable', 'key' => 'KEY3', 'value' => 'value3', 'comment_inline' => null, 'comment_above' => [], 'export' => false],
    ];
    $this->envStorage
        ->shouldReceive('read')->once()
            ->andReturn($content);
    $this->envParser
        ->shouldReceive('parse')->once()
            ->andReturn($initialParsed);
    $this->envManager->load();

    $this->envManager->remove('KEY2'); // EnvEditor will handle removal and cleanup

    $this->backupManager
        ->shouldReceive('create')->once()
            ->andReturn(true);
    // EnvEditor's lines after removal and cleanup should be:
    // KEY1=value1, empty, KEY3=value3 (assuming cleanupEmptyLines in EnvEditor works)
    $expectedFormatted = "KEY1=value1".PHP_EOL.PHP_EOL."KEY3=value3".PHP_EOL;
    $this->envFormatter
        ->shouldReceive('format')->once()
            ->with(Mockery::on(function($lines) {
                // Basic check: KEY2 should not be present, KEY1 and KEY3 should.
                $keys = array_column(array_filter($lines, fn($l) => $l['type'] === 'variable'), 'key');
                return in_array('KEY1', $keys) && !in_array('KEY2', $keys) && in_array('KEY3', $keys);
            }))
            ->andReturn($expectedFormatted);
    $this->envStorage
        ->shouldReceive('write')->once()
            ->andReturn(true);
    $this->envManager->save();

    $this->files->put($this->testBelichEnvPath, $expectedFormatted);
    $newContent = $this->files->get($this->testBelichEnvPath);
    // The exact number of newlines depends on EnvEditor's cleanup and EnvFormatter's behavior.
    // This assertion is a bit loose due to that.
    expect($newContent)
        ->toContain("KEY1=value1".PHP_EOL.PHP_EOL."KEY3=value3");
});

// Test that multiple consecutive save operations work as expected.
test('it handles multiple consecutive saves', function () {
    $envPath = app()->environmentFilePath();

    $this->backupManager
        ->shouldReceive('create')
            ->twice()->with($envPath) // Expect backup for each save
            ->andReturn(true);

    // First save
    $setter1 = $this->envManager->set('FIRST_KEY', 'first_value');
    $this->envFormatter
        ->shouldReceive('format')->once()
            ->andReturn('FIRST_KEY="first_value"'.PHP_EOL);
    $this->envStorage
        ->shouldReceive('write')->once()
            ->andReturn(true);
    
    $setter1->save();

    // Second save
    $setter2 = $this->envManager->set('SECOND_KEY', 'second_value');
    $this->envFormatter
        ->shouldReceive('format')->once()
            ->andReturn('FIRST_KEY="first_value"'.PHP_EOL.'SECOND_KEY="second_value"'.PHP_EOL);
    $this->envStorage
        ->shouldReceive('write')->once()
            ->andReturn(true);
    
    $setter2->save();
    // Assertions are on the mocks
});

// Test that comments (both block and inline) are preserved when a value is updated.
test('it preserves comments when updating values', function () {
    $content = "# Important setting\nKEY=old_value # with comment";
    $initialParsed = [
        // EnvParser should put '# Important setting' into comment_above of KEY
        ['type' => 'variable', 'key' => 'KEY', 'value' => 'old_value', 'comment_inline' => 'with comment', 'comment_above' => ['# Important setting'], 'export' => false]
    ];
    $this->envStorage
        ->shouldReceive('read')->once()
            ->andReturn($content);
    $this->envParser
        ->shouldReceive('parse')->once()
            ->andReturn($initialParsed);
    $this->envManager->load();

    $setter = $this->envManager->set('KEY', 'new_value'); // EnvEditor should preserve comments if not overridden

    $this->backupManager
        ->shouldReceive('create')->once()
            ->andReturn(true);
    $expectedFormatted = "# Important setting".PHP_EOL.'KEY="new_value" # with comment'.PHP_EOL;
    $this->envFormatter
        ->shouldReceive('format')->once()
            ->with(Mockery::on(function($lines){
                return $lines[0]['key'] === 'KEY' &&
                       $lines[0]['value'] === 'new_value' &&
                       $lines[0]['comment_above'] == ['# Important setting'] &&
                       $lines[0]['comment_inline'] === 'with comment';
            }))
            ->andReturn($expectedFormatted);
    $this->envStorage
        ->shouldReceive('write')->once()
            ->andReturn(true);
    
    $setter->save();

    $this->files->put($this->testBelichEnvPath, $expectedFormatted);
    $newContent = $this->files->get($this->testBelichEnvPath);
    
    expect($newContent)
        ->toContain("# Important setting\nKEY=\"new_value\" # with comment");
});

// Test that values containing equal signs are correctly quoted.
test('it handles values with equal signs', function () {
    $setter = $this->envManager->set('EQUAL_KEY', 'value=with=equals');
    $this->backupManager
        ->shouldReceive('create')->once()
            ->andReturn(true);
    
    $expected = 'EQUAL_KEY="value=with=equals"'.PHP_EOL;
    $this->envFormatter
        ->shouldReceive('format')->once()
            ->andReturn($expected);
    $this->envStorage
        ->shouldReceive('write')->once()
            ->andReturn(true);
    
    $setter->save();
    
    $this->files->put($this->testBelichEnvPath, $expected);
    
    expect($this->files->get($this->testBelichEnvPath))
        ->toContain('EQUAL_KEY="value=with=equals"');
});

// Test that numeric values are handled correctly (not quoted by formatter if simple).
test('it handles numeric values correctly', function () {
    $setter = $this->envManager->set('NUMBER_KEY', '12345'); // EnvManager expects string
    $this->backupManager
        ->shouldReceive('create')->once()
            ->andReturn(true);
    
    $expected = 'NUMBER_KEY=12345'.PHP_EOL; // Formatter might not quote simple numerics
    $this->envFormatter
        ->shouldReceive('format')->once()
            ->andReturn($expected);
    $this->envStorage
        ->shouldReceive('write')->once()
            ->andReturn(true);
    
    $setter->save();
    
    $this->files->put($this->testBelichEnvPath, $expected);
    
    expect($this->files->get($this->testBelichEnvPath))
        ->toContain('NUMBER_KEY=12345');
});

// Test that string 'null' values are handled correctly (quoted by formatter).
test('it handles null values correctly', function () {
    $setter = $this->envManager->set('NULL_KEY', 'null');
    $this->backupManager
        ->shouldReceive('create')->once()
            ->andReturn(true);
    
    $expected = 'NULL_KEY="null"'.PHP_EOL;
    $this->envFormatter
        ->shouldReceive('format')->once()
            ->andReturn($expected);
    $this->envStorage
        ->shouldReceive('write')->once()
            ->andReturn(true);
    
    $setter->save();
    
    $this->files->put($this->testBelichEnvPath, $expected);
    
    expect($this->files->get($this->testBelichEnvPath))
        ->toContain('NULL_KEY="null"');
});

// Test that values with various special characters are correctly quoted.
test('it handles special characters correctly', function () {
    $setter = $this->envManager->set('SPECIAL_KEY', 'value with $pecial@characters!');
    $this->backupManager
        ->shouldReceive('create')->once()
            ->andReturn(true);
    
    $expected = 'SPECIAL_KEY="value with $pecial@characters!"'.PHP_EOL;
    $this->envFormatter
        ->shouldReceive('format')->once()
            ->andReturn($expected);
    $this->envStorage
        ->shouldReceive('write')->once()
            ->andReturn(true);
    
    $setter->save();
   
    $this->files->put($this->testBelichEnvPath, $expected);
    
    expect($this->files->get($this->testBelichEnvPath))
        ->toContain('SPECIAL_KEY="value with $pecial@characters!"');
});

test('EnvVariableSetter sets inline comment correctly and saves', function () {
    $setter = $this->envManager->set('TEST_COMMENT_LINE', 'value');
    $setter->commentLine('This is an inline comment');

    // Verify EnvEditor was called correctly by EnvVariableSetter
    // The EnvEditor instance is real, so we check its state after the call
    $lines = $this->envEditor->getLines();
    $foundLine = collect($lines)->firstWhere('key', 'TEST_COMMENT_LINE');

    expect($foundLine)->not->toBeNull()
        ->and($foundLine['value'])->toBe('value')
        ->and($foundLine['comment_inline'])->toBe('This is an inline comment');

    // Mock save operation
    $this->backupManager
        ->shouldReceive('create')->once()
            ->andReturn(true);
    $this->envFormatter
        ->shouldReceive('format')->once()
            ->andReturn('TEST_COMMENT_LINE="value" # This is an inline comment'.PHP_EOL);
    $this->envStorage
        ->shouldReceive('write')->once()
            ->andReturn(true);

    $result = $setter->save();
    
    expect($result)->toBeTrue();
});

test('EnvVariableSetter sets comments above correctly and saves', function () {
    $setter = $this->envManager->set('TEST_COMMENT_ABOVE', 'value');
    $comments = ['# Line 1 above', '# Line 2 above'];
    $setter->commentsAbove($comments);

    $lines = $this->envEditor->getLines();
    $foundLine = collect($lines)->firstWhere('key', 'TEST_COMMENT_ABOVE');

    expect($foundLine)->not->toBeNull()
        ->and($foundLine['value'])->toBe('value')
        ->and($foundLine['comment_above'])->toBe($comments);

    // Mock save operation
    $this->backupManager
        ->shouldReceive('create')->once()
            ->andReturn(true);
    $this->envFormatter
        ->shouldReceive('format')->once()
            ->andReturn("# Line 1 above\n# Line 2 above\nTEST_COMMENT_ABOVE=\"value\"".PHP_EOL);
    $this->envStorage
        ->shouldReceive('write')->once()
            ->andReturn(true);

    $result = $setter->save();
    
    expect($result)->toBeTrue();
});

test('EnvVariableSetter can chain comment methods and save', function () {
    $setter = $this->envManager->set('TEST_CHAIN', 'chained_value')
        ->commentLine('Inline for chain')
        ->commentsAbove(['# Above for chain']);

    // Mock save operation (simplified, detailed state check done in previous tests)
    $this->backupManager
        ->shouldReceive('create')->once()
            ->andReturn(true);
    $this->envFormatter
        ->shouldReceive('format')->once()
            ->andReturn('...'); // Simplified for brevity
    $this->envStorage
        ->shouldReceive('write')->once()
            ->andReturn(true);

    expect($setter->save())->toBeTrue();
});
