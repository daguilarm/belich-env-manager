<?php

// Unit tests for the EnvEditor class.
// The EnvEditor is responsible for the direct, low-level manipulation
// of the .env file content, which is represented as an array of "lines".
// Each line is an associative array detailing its type (variable, comment, empty),
// key, value, and associated comments.
use Daguilar\BelichEnvManager\Services\Env\EnvEditor;

beforeEach(function () {
    $this->editor = new EnvEditor;
});

describe('Basic Operations', function () {
    // Tests the fundamental ability to set and retrieve the raw lines array.
    it('can set and get lines', function () {
        $lines = [
            ['type' => 'variable', 'key' => 'APP_NAME', 'value' => 'Laravel', 'comment_inline' => null, 'comment_above' => [], 'export' => false],
            ['type' => 'empty'],
        ];
        $this->editor->setLines($lines);
        expect($this->editor->getLines())->toBe($lines);
    });

    // Verifies the 'has' method correctly identifies existing keys.
    it('returns true for existing key in has() check', function () {
        $this->editor->setLines([
            ['type' => 'variable', 'key' => 'APP_ENV', 'value' => 'local', 'comment_inline' => null, 'comment_above' => [], 'export' => false],
        ]);
        expect($this->editor->has('APP_ENV'))->toBeTrue();
    });

    // Verifies the 'has' method correctly identifies non-existing keys.
    it('returns false for non-existing key in has() check', function () {
        $this->editor->setLines([
            ['type' => 'variable', 'key' => 'APP_ENV', 'value' => 'local', 'comment_inline' => null, 'comment_above' => [], 'export' => false],
        ]);
        expect($this->editor->has('NON_EXISTING_KEY'))->toBeFalse();
    });

    // Tests retrieving the value of an existing key.
    it('returns value for existing key in get()', function () {
        $this->editor->setLines([
            ['type' => 'variable', 'key' => 'APP_DEBUG', 'value' => 'true', 'comment_inline' => null, 'comment_above' => [], 'export' => false],
        ]);
        expect($this->editor->get('APP_DEBUG'))->toBe('true');
    });

    // Tests the default value mechanism of the 'get' method.
    it('returns default value for non-existing key in get()', function () {
        expect($this->editor->get('NON_EXISTING_KEY', 'default_value'))->toBe('default_value');
    });

    // Ensures 'get' returns null if a key doesn't exist and no default is provided.
    it('returns null for non-existing key without default in get()', function () {
        expect($this->editor->get('NON_EXISTING_KEY'))->toBeNull();
    });
});

describe('Variable Setting', function () {
    it('adds a new variable', function () {
        // When adding a new variable, it's appended to the lines array.
        $this->editor->set('NEW_KEY', 'new_value');
        $lines = $this->editor->getLines();
        $foundLine = collect($lines)->firstWhere('key', 'NEW_KEY');

        expect($foundLine)->not->toBeNull()
            ->and($foundLine['value'])->toBe('new_value')
            ->and($foundLine['comment_inline'] ?? null)->toBeNull()
            ->and($foundLine['comment_above'])->toBe([]);
    });

    it('adds a new variable with inline comment', function () {
        // Tests setting an inline comment during variable creation.
        $this->editor->set('KEY_WITH_INLINE', 'value_inline', 'This is inline');
        $lines = $this->editor->getLines();
        $foundLine = collect($lines)->firstWhere('key', 'KEY_WITH_INLINE');

        expect($foundLine['comment_inline'])->toBe('This is inline');
    });

    it('adds a new variable with comments above', function () {
        // Tests setting comments above a variable during creation.
        $comments = ['# Comment 1', '# Comment 2'];
        $this->editor->set('KEY_WITH_ABOVE', 'value_above', null, $comments);
        $lines = $this->editor->getLines();
        $foundLine = collect($lines)->firstWhere('key', 'KEY_WITH_ABOVE');

        expect($foundLine['comment_above'])->toBe($comments);
    });

    it('adds a new variable with export flag', function () {
        // Tests setting the export flag during variable creation.
        $this->editor->set('EXPORTED_KEY', 'export_value', null, [], true);
        $lines = $this->editor->getLines();
        $foundLine = collect($lines)->firstWhere('key', 'EXPORTED_KEY');

        expect($foundLine)->not->toBeNull()
            ->and($foundLine['value'])->toBe('export_value')
            ->and($foundLine['export'])->toBeTrue();
    });


    // The editor intelligently adds an empty line for separation
    // if a new variable is added and the previous line wasn't empty or a comment.
    it('adds an empty line before new variable if last line was not empty', function () {
        $this->editor->setLines([
            ['type' => 'variable', 'key' => 'EXISTING_KEY', 'value' => 'existing_value', 'comment_inline' => null, 'comment_above' => [], 'export' => false],
        ]);
        $this->editor->set('NEW_KEY_AFTER_VAR', 'new_value');
        $lines = $this->editor->getLines();

        expect($lines[0]['key'])->toBe('EXISTING_KEY');
        expect($lines[1]['type'])->toBe('empty');
        expect($lines[2]['key'])->toBe('NEW_KEY_AFTER_VAR');
    });

    // However, if the new variable has comments above, an empty line is not prepended,
    // as the comments themselves provide separation.
    it('does not add empty line if comments above are provided', function () {
        $this->editor->setLines([
            ['type' => 'variable', 'key' => 'EXISTING_KEY', 'value' => 'existing_value', 'comment_inline' => null, 'comment_above' => [], 'export' => false],
        ]);
        $this->editor->set('NEW_KEY_WITH_ABOVE', 'new_value_comm', null, ['# A comment']);
        $lines = $this->editor->getLines();

        expect($lines[0]['key'])->toBe('EXISTING_KEY');
        expect($lines[1]['key'])->toBe('NEW_KEY_WITH_ABOVE');
        expect($lines[1]['comment_above'])->toBe(['# A comment']);
    });

    // When setting an existing key, only the value should be updated by default, preserving comments.
    it('updates an existing variable value', function () {
        $this->editor->setLines([
            ['type' => 'variable', 'key' => 'APP_NAME', 'value' => 'Old Name', 'comment_inline' => 'Old inline', 'comment_above' => ['# Old above'], 'export' => false],
        ]);
        $this->editor->set('APP_NAME', 'New Name');
        $lines = $this->editor->getLines();
        $foundLine = collect($lines)->firstWhere('key', 'APP_NAME');

        expect($foundLine['value'])->toBe('New Name');
        expect($foundLine['comment_inline'])->toBe('Old inline');
        expect($foundLine['comment_above'])->toBe(['# Old above']);
    });

    it('updates an existing variable to set export flag', function () {
        $this->editor->setLines([
            ['type' => 'variable', 'key' => 'APP_NAME', 'value' => 'My App', 'comment_inline' => null, 'comment_above' => [], 'export' => false],
        ]);
        // Update APP_NAME to be exported, value and comments should remain if not specified
        $this->editor->set('APP_NAME', 'My App', null, [], true);
        $lines = $this->editor->getLines();
        $foundLine = collect($lines)->firstWhere('key', 'APP_NAME');

        expect($foundLine['value'])->toBe('My App');
        expect($foundLine['export'])->toBeTrue();
    });


    // Tests updating only the inline comment of an existing variable.
    it('updates existing variable inline comment', function () {
        $this->editor->setLines([
            ['type' => 'variable', 'key' => 'APP_NAME', 'value' => 'Name', 'comment_inline' => 'Old inline', 'comment_above' => [], 'export' => false],
        ]);
        $this->editor->set('APP_NAME', 'Name', 'New inline');
        $foundLine = $this->editor->getLines()[0];
        expect($foundLine['comment_inline'])->toBe('New inline');
        
        // Setting an empty string for inline comment should result in null.
        $this->editor->set('APP_NAME', 'Name', '');
        $foundLine = $this->editor->getLines()[0];
        expect($foundLine['comment_inline'])->toBeNull();
    });

    // Tests updating only the comments above an existing variable.
    it('updates existing variable comments above', function () {
        $this->editor->setLines([
            ['type' => 'variable', 'key' => 'APP_NAME', 'value' => 'Name', 'comment_inline' => null, 'comment_above' => ['# Old above'], 'export' => false],
        ]);
        $newComments = ['# New Comment 1'];
        $this->editor->set('APP_NAME', 'Name', null, $newComments);
        $foundLine = $this->editor->getLines()[0];
        expect($foundLine['comment_above'])->toBe($newComments);
        
        // Setting an empty array for comments above should result in an empty array.
        $this->editor->set('APP_NAME', 'Name', null, []);
        $foundLine = $this->editor->getLines()[0];
        expect($foundLine['comment_above'])->toBe([]);
    });
});

describe('Variable Removal', function () {
    it('deletes an existing key', function () {
        // Removing a key also removes its entire line from the internal array.
        $this->editor->setLines([
            ['type' => 'variable', 'key' => 'KEY_TO_DELETE', 'value' => 'delete_me', 'comment_inline' => null, 'comment_above' => [], 'export' => false],
            ['type' => 'variable', 'key' => 'OTHER_KEY', 'value' => 'keep_me', 'comment_inline' => null, 'comment_above' => [], 'export' => false],
        ]);
        $this->editor->remove('KEY_TO_DELETE');
        $lines = $this->editor->getLines();

        expect($this->editor->has('KEY_TO_DELETE'))->toBeFalse();
        expect(count($lines))->toBe(1);
        expect($lines[0]['key'])->toBe('OTHER_KEY');
    });

    // Attempting to remove a non-existent key should not alter the lines.
    it('does nothing for non-existing key', function () {
        $initialLines = [
            ['type' => 'variable', 'key' => 'EXISTING_KEY', 'value' => 'value', 'comment_inline' => null, 'comment_above' => [], 'export' => false],
        ];
        $this->editor->setLines($initialLines);
        $this->editor->remove('NON_EXISTING_KEY_TO_DELETE');

        expect($this->editor->getLines())->toBe($initialLines);
    });

    // After removing a variable, the editor should clean up any
    // resulting consecutive empty lines, reducing them to a single empty line.
    it('cleans up consecutive empty lines', function () {
        $this->editor->setLines([
            ['type' => 'variable', 'key' => 'KEY1', 'value' => 'v1', 'comment_inline' => null, 'comment_above' => [], 'export' => false],
            ['type' => 'empty'],
            ['type' => 'empty'],
            ['type' => 'variable', 'key' => 'KEY_TO_REMOVE', 'value' => 'remove', 'comment_inline' => null, 'comment_above' => [], 'export' => false],
            ['type' => 'empty'],
            ['type' => 'empty'],
            ['type' => 'variable', 'key' => 'KEY3', 'value' => 'v3', 'comment_inline' => null, 'comment_above' => [], 'export' => false],
        ]);
        $this->editor->remove('KEY_TO_REMOVE');
        $lines = $this->editor->getLines();

        $expectedLines = [
            ['type' => 'variable', 'key' => 'KEY1', 'value' => 'v1', 'comment_inline' => null, 'comment_above' => [], 'export' => false],
            ['type' => 'empty'],
            ['type' => 'variable', 'key' => 'KEY3', 'value' => 'v3', 'comment_inline' => null, 'comment_above' => [], 'export' => false],
        ];
        expect($lines)->toBe($expectedLines);
    });
});

// Tests for the private method `cleanupEmptyLines`.
// Reflection is used here to test this critical internal utility method directly.
describe('Empty Line Handling', function () {
    // Ensures that sequences of multiple empty lines are condensed into one.
    it('reduces multiple empty lines to one', function () {
        $lines = [
            ['type' => 'variable', 'key' => 'K1', 'value' => 'V1'],
            ['type' => 'empty'],
            ['type' => 'empty'],
            ['type' => 'empty'],
            ['type' => 'variable', 'key' => 'K2', 'value' => 'V2'],
            ['type' => 'empty'],
            ['type' => 'variable', 'key' => 'K3', 'value' => 'V3'],
        ];
        
        $reflection = new ReflectionClass(EnvEditor::class);
        $method = $reflection->getMethod('cleanupEmptyLines');
        $method->setAccessible(true);
        $cleanedLines = $method->invokeArgs($this->editor, [$lines]);

        $expected = [
            ['type' => 'variable', 'key' => 'K1', 'value' => 'V1'],
            ['type' => 'empty'],
            ['type' => 'variable', 'key' => 'K2', 'value' => 'V2'],
            ['type' => 'empty'],
            ['type' => 'variable', 'key' => 'K3', 'value' => 'V3'],
        ];
        expect($cleanedLines)->toBe($expected);
    });

    // Checks behavior with empty lines at the beginning and end of the content.
    it('handles leading and trailing empty lines', function () {
        $lines = [
            ['type' => 'empty'],
            ['type' => 'empty'],
            ['type' => 'variable', 'key' => 'K1', 'value' => 'V1'],
            ['type' => 'empty'],
            ['type' => 'empty'],
        ];
        $reflection = new ReflectionClass(EnvEditor::class);
        $method = $reflection->getMethod('cleanupEmptyLines');
        $method->setAccessible(true);
        $cleanedLines = $method->invokeArgs($this->editor, [$lines]);

        $expected = [
            ['type' => 'empty'],
            ['type' => 'variable', 'key' => 'K1', 'value' => 'V1'],
            ['type' => 'empty'],
        ];
        expect($cleanedLines)->toBe($expected);
    });

    // Edge case: an array consisting only of empty lines.
    it('handles array with only empty lines', function () {
        $lines = [['type' => 'empty'], ['type' => 'empty'], ['type' => 'empty']];
        $reflection = new ReflectionClass(EnvEditor::class);
        $method = $reflection->getMethod('cleanupEmptyLines');
        $method->setAccessible(true);
        $cleanedLines = $method->invokeArgs($this->editor, [$lines]);
        expect($cleanedLines)->toBe([['type' => 'empty']]);
    });

    // Edge case: an empty input array.
    it('handles empty input array', function () {
        $lines = [];
        $reflection = new ReflectionClass(EnvEditor::class);
        $method = $reflection->getMethod('cleanupEmptyLines');
        $method->setAccessible(true);
        $cleanedLines = $method->invokeArgs($this->editor, [$lines]);
        expect($cleanedLines)->toBeEmpty();
    });
});