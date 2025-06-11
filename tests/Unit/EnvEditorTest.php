<?php

use Daguilar\BelichEnvManager\Services\Env\EnvEditor;

beforeEach(function () {
    $this->editor = new EnvEditor;
});

test('it can set and get lines', function () {
    $lines = [
        ['type' => 'variable', 'key' => 'APP_NAME', 'value' => 'Laravel', 'comment_inline' => null, 'comment_above' => [], 'export' => false],
        ['type' => 'empty'],
    ];
    $this->editor->setLines($lines);
    expect($this->editor->getLines())->toBe($lines);
});

test('has returns true for existing key', function () {
    $this->editor->setLines([
        ['type' => 'variable', 'key' => 'APP_ENV', 'value' => 'local', 'comment_inline' => null, 'comment_above' => [], 'export' => false],
    ]);
    expect($this->editor->has('APP_ENV'))->toBeTrue();
});

test('has returns false for non-existing key', function () {
    $this->editor->setLines([
        ['type' => 'variable', 'key' => 'APP_ENV', 'value' => 'local', 'comment_inline' => null, 'comment_above' => [], 'export' => false],
    ]);
    expect($this->editor->has('NON_EXISTING_KEY'))->toBeFalse();
});

test('get returns value for existing key', function () {
    $this->editor->setLines([
        ['type' => 'variable', 'key' => 'APP_DEBUG', 'value' => 'true', 'comment_inline' => null, 'comment_above' => [], 'export' => false],
    ]);
    expect($this->editor->get('APP_DEBUG'))->toBe('true');
});

test('get returns default value for non-existing key', function () {
    expect($this->editor->get('NON_EXISTING_KEY', 'default_value'))->toBe('default_value');
});

test('get returns null for non-existing key without default', function () {
    expect($this->editor->get('NON_EXISTING_KEY'))->toBeNull();
});

test('set adds a new variable', function () {
    $this->editor->set('NEW_KEY', 'new_value');
    $lines = $this->editor->getLines();
    $foundLine = collect($lines)->firstWhere('key', 'NEW_KEY');

    expect($foundLine)->not->toBeNull()
        ->and($foundLine['value'])->toBe('new_value')
        ->and($foundLine['comment_inline'])->toBeNull()
        ->and($foundLine['comment_above'])->toBe([]);
});

test('set adds a new variable with inline comment', function () {
    $this->editor->set('KEY_WITH_INLINE', 'value_inline', 'This is inline');
    $lines = $this->editor->getLines();
    $foundLine = collect($lines)->firstWhere('key', 'KEY_WITH_INLINE');

    expect($foundLine['comment_inline'])->toBe('This is inline');
});

test('set adds a new variable with comments above', function () {
    $comments = ['# Comment 1', '# Comment 2'];
    $this->editor->set('KEY_WITH_ABOVE', 'value_above', null, $comments);
    $lines = $this->editor->getLines();
    $foundLine = collect($lines)->firstWhere('key', 'KEY_WITH_ABOVE');

    expect($foundLine['comment_above'])->toBe($comments);
});

test('set adds an empty line before new variable if last line was not empty and no comments above', function () {
    $this->editor->setLines([
        ['type' => 'variable', 'key' => 'EXISTING_KEY', 'value' => 'existing_value', 'comment_inline' => null, 'comment_above' => [], 'export' => false],
    ]);
    $this->editor->set('NEW_KEY_AFTER_VAR', 'new_value');
    $lines = $this->editor->getLines();

    // Expect: EXISTING_KEY, empty line, NEW_KEY_AFTER_VAR
    expect($lines[0]['key'])->toBe('EXISTING_KEY');
    expect($lines[1]['type'])->toBe('empty');
    expect($lines[2]['key'])->toBe('NEW_KEY_AFTER_VAR');
});

test('set does not add empty line if comments above are provided for new variable', function () {
    $this->editor->setLines([
        ['type' => 'variable', 'key' => 'EXISTING_KEY', 'value' => 'existing_value', 'comment_inline' => null, 'comment_above' => [], 'export' => false],
    ]);
    $this->editor->set('NEW_KEY_WITH_ABOVE', 'new_value_comm', null, ['# A comment']);
    $lines = $this->editor->getLines();

    // Expect: EXISTING_KEY, NEW_KEY_WITH_ABOVE (with its comment above)
    expect($lines[0]['key'])->toBe('EXISTING_KEY');
    expect($lines[1]['key'])->toBe('NEW_KEY_WITH_ABOVE');
    expect($lines[1]['comment_above'])->toBe(['# A comment']);
});

test('set updates an existing variable value', function () {
    $this->editor->setLines([
        ['type' => 'variable', 'key' => 'APP_NAME', 'value' => 'Old Name', 'comment_inline' => 'Old inline', 'comment_above' => ['# Old above'], 'export' => false],
    ]);
    $this->editor->set('APP_NAME', 'New Name'); // Only update value
    $lines = $this->editor->getLines();
    $foundLine = collect($lines)->firstWhere('key', 'APP_NAME');

    expect($foundLine['value'])->toBe('New Name');
    expect($foundLine['comment_inline'])->toBe('Old inline'); // Should preserve
    expect($foundLine['comment_above'])->toBe(['# Old above']); // Should preserve
});

test('set updates existing variable inline comment', function () {
    $this->editor->setLines([
        ['type' => 'variable', 'key' => 'APP_NAME', 'value' => 'Name', 'comment_inline' => 'Old inline', 'comment_above' => [], 'export' => false],
    ]);
    $this->editor->set('APP_NAME', 'Name', 'New inline');
    $foundLine = $this->editor->getLines()[0];
    expect($foundLine['comment_inline'])->toBe('New inline');

    // Clear inline comment
    $this->editor->set('APP_NAME', 'Name', '');
    $foundLine = $this->editor->getLines()[0];
    expect($foundLine['comment_inline'])->toBeNull();
});

test('set updates existing variable comments above', function () {
    $this->editor->setLines([
        ['type' => 'variable', 'key' => 'APP_NAME', 'value' => 'Name', 'comment_inline' => null, 'comment_above' => ['# Old above'], 'export' => false],
    ]);
    $newComments = ['# New Comment 1'];
    $this->editor->set('APP_NAME', 'Name', null, $newComments);
    $foundLine = $this->editor->getLines()[0];
    expect($foundLine['comment_above'])->toBe($newComments);

    // Clear comments above
    $this->editor->set('APP_NAME', 'Name', null, []);
    $foundLine = $this->editor->getLines()[0];
    expect($foundLine['comment_above'])->toBe([]);
});

test('remove deletes an existing key', function () {
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

test('remove does nothing for non-existing key', function () {
    $initialLines = [
        ['type' => 'variable', 'key' => 'EXISTING_KEY', 'value' => 'value', 'comment_inline' => null, 'comment_above' => [], 'export' => false],
    ];
    $this->editor->setLines($initialLines);
    $this->editor->remove('NON_EXISTING_KEY_TO_DELETE');

    expect($this->editor->getLines())->toBe($initialLines); // Lines should be unchanged
});

test('remove cleans up consecutive empty lines', function () {
    $this->editor->setLines([
        ['type' => 'variable', 'key' => 'KEY1', 'value' => 'v1', 'comment_inline' => null, 'comment_above' => [], 'export' => false],
        ['type' => 'empty'],
        ['type' => 'empty'], // This should be cleaned up if KEY_TO_REMOVE is between empty lines
        ['type' => 'variable', 'key' => 'KEY_TO_REMOVE', 'value' => 'remove', 'comment_inline' => null, 'comment_above' => [], 'export' => false],
        ['type' => 'empty'],
        ['type' => 'empty'],
        ['type' => 'variable', 'key' => 'KEY3', 'value' => 'v3', 'comment_inline' => null, 'comment_above' => [], 'export' => false],
    ]);
    $this->editor->remove('KEY_TO_REMOVE');
    $lines = $this->editor->getLines();

    $expectedLines = [
        ['type' => 'variable', 'key' => 'KEY1', 'value' => 'v1', 'comment_inline' => null, 'comment_above' => [], 'export' => false],
        ['type' => 'empty'], // Only one empty line should remain between KEY1 and KEY3
        ['type' => 'variable', 'key' => 'KEY3', 'value' => 'v3', 'comment_inline' => null, 'comment_above' => [], 'export' => false],
    ];
    expect($lines)->toBe($expectedLines);
});

test('cleanupEmptyLines reduces multiple empty lines to one', function () {
    $lines = [
        ['type' => 'variable', 'key' => 'K1', 'value' => 'V1'],
        ['type' => 'empty'],
        ['type' => 'empty'],
        ['type' => 'empty'],
        ['type' => 'variable', 'key' => 'K2', 'value' => 'V2'],
        ['type' => 'empty'],
        ['type' => 'variable', 'key' => 'K3', 'value' => 'V3'],
    ];
    // Accessing private method via reflection for testing
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

test('cleanupEmptyLines handles leading and trailing empty lines', function () {
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

test('cleanupEmptyLines handles array with only empty lines', function () {
    $lines = [['type' => 'empty'], ['type' => 'empty'], ['type' => 'empty']];
    $reflection = new ReflectionClass(EnvEditor::class);
    $method = $reflection->getMethod('cleanupEmptyLines');
    $method->setAccessible(true);
    $cleanedLines = $method->invokeArgs($this->editor, [$lines]);
    expect($cleanedLines)->toBe([['type' => 'empty']]);
});

test('cleanupEmptyLines handles empty input array', function () {
    $lines = [];
    $reflection = new ReflectionClass(EnvEditor::class);
    $method = $reflection->getMethod('cleanupEmptyLines');
    $method->setAccessible(true);
    $cleanedLines = $method->invokeArgs($this->editor, [$lines]);
    expect($cleanedLines)->toBeEmpty();
});
