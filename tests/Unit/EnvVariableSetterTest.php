<?php

use Daguilar\BelichEnvManager\Services\Env\EnvEditor;
use Daguilar\BelichEnvManager\Services\Env\EnvVariableSetter;
use Daguilar\BelichEnvManager\Services\EnvManager;

beforeEach(function () {
    // Mock dependencies
    $this->editorMock = Mockery::mock(EnvEditor::class);
    $this->managerMock = Mockery::mock(EnvManager::class);

    // The constructor of EnvVariableSetter calls editor->set immediately
    // with the key/value and null for comments. We need to expect this.
    $this->editorMock
        ->shouldReceive('set')
        ->with('TEST_KEY', 'test_value', null, null)
        ->once();

    // Create the setter instance
    $this->setter = new EnvVariableSetter($this->editorMock, $this->managerMock, 'TEST_KEY', 'test_value');
});

afterEach(function () {
    Mockery::close();
});

test('it calls editor set with initial value on construction', function () {
    // This is covered by the expectation in beforeEach
    expect(true)->toBeTrue();
});

test('commentLine calls editor set with inline comment', function () {
    $inlineComment = 'This is an inline comment';

    $this->editorMock
        ->shouldReceive('set')
        ->with('TEST_KEY', 'test_value', $inlineComment, null)
        ->once();

    $result = $this->setter->commentLine($inlineComment);

    expect($result)->toBeInstanceOf(EnvVariableSetter::class); // Test fluent interface
});

test('commentsAbove calls editor set with block comments', function () {
    $commentsAbove = ['# Comment 1', '# Comment 2'];

    $this->editorMock
        ->shouldReceive('set')
        ->with('TEST_KEY', 'test_value', null, $commentsAbove)
        ->once();

    $result = $this->setter->commentsAbove($commentsAbove);

    expect($result)->toBeInstanceOf(EnvVariableSetter::class); // Test fluent interface
});

test('can chain commentLine and commentsAbove', function () {
    $inlineComment = 'Inline comment';
    $commentsAbove = ['# Above comment'];

    // Expect two calls to editor->set:
    // 1. For commentLine (updates inline, preserves above as null)
    $this->editorMock
        ->shouldReceive('set')
        ->with('TEST_KEY', 'test_value', $inlineComment, null)
        ->once();

    // 2. For commentsAbove (updates above, preserves inline as null)
    $this->editorMock
        ->shouldReceive('set')
        ->with('TEST_KEY', 'test_value', null, $commentsAbove)
        ->once();

    $result = $this->setter->commentLine($inlineComment)->commentsAbove($commentsAbove);

    expect($result)->toBeInstanceOf(EnvVariableSetter::class); // Test fluent interface
});

test('save calls env manager save', function () {
    $this->managerMock->shouldReceive('save')->once()->andReturn(true);

    $result = $this->setter->save();

    expect($result)->toBeTrue();
});
