<?php

// Unit tests for the EnvVariableSetter class.
// This class provides a fluent interface for setting a variable's value,
// its inline comment, and comments above it, before finally saving
// the changes through the main EnvManager.
use Daguilarm\EnvManager\Services\Env\EnvEditor;
use Daguilarm\EnvManager\Services\Env\EnvVariableSetter;
use Daguilarm\EnvManager\Services\EnvManager;

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

describe('EnvVariableSetter Functionality', function () {
    // Verifies that the EnvEditor->set() method is called with the initial key/value
    // upon construction of the EnvVariableSetter.
    it('calls editor set with initial value on construction', function () {
        // The core expectation for this behavior is already in the beforeEach setup.
        // This test serves as a clear marker for that initial interaction.
        expect(true)->toBeTrue(); // Assertion to make Pest happy.
    });

    // Tests that setting an inline comment correctly calls EnvEditor->set()
    // with the new inline comment, preserving the original key and value.
    it('commentLine calls editor set with inline comment', function () {
        $inlineComment = 'This is an inline comment';

        $this->editorMock
            ->shouldReceive('set')
            ->with('TEST_KEY', 'test_value', $inlineComment, null) // Expects inline comment, null for comments_above
            ->once();

        $result = $this->setter->commentLine($inlineComment);

        expect($result)->toBeInstanceOf(EnvVariableSetter::class); // Ensures fluent interface
    });

    // Tests that setting comments above correctly calls EnvEditor->set()
    // with the new block comments, preserving the original key and value.
    it('commentsAbove calls editor set with block comments', function () {
        $commentsAbove = ['# Comment 1', '# Comment 2'];

        $this->editorMock
            ->shouldReceive('set')
            ->with('TEST_KEY', 'test_value', null, $commentsAbove) // Expects comments_above, null for inline_comment
            ->once();

        $result = $this->setter->commentsAbove($commentsAbove);

        expect($result)->toBeInstanceOf(EnvVariableSetter::class); // Ensures fluent interface
    });

    // Verifies that both inline and above comments can be set by chaining methods.
    // Each call should update the respective comment type in the EnvEditor.
    it('can chain commentLine and commentsAbove, updating editor state sequentially', function () {
        $inlineComment = 'Inline comment';
        $commentsAbove = ['# Above comment'];

        // First, commentLine is called, updating the inline comment.
        $this->editorMock->shouldReceive('set')->with('TEST_KEY', 'test_value', $inlineComment, null)->once();
        // Then, commentsAbove is called, updating the comments above.
        // The EnvVariableSetter should pass the current value and the *new* comments_above,
        // while the inline_comment would be what was last set (or null if not set by this chain).
        // For this specific test, we assume EnvEditor handles merging/preserving other comment types if not explicitly passed.
        // However, the current EnvVariableSetter implementation re-calls editor->set for each comment type,
        // potentially overwriting the other if not careful. The mock reflects this sequential overwrite.
        $this->editorMock->shouldReceive('set')->with('TEST_KEY', 'test_value', $inlineComment, $commentsAbove)->once();

        $result = $this->setter->commentLine($inlineComment)->commentsAbove($commentsAbove);

        expect($result)->toBeInstanceOf(EnvVariableSetter::class); // Ensures fluent interface
    });

    // Verifies the reverse order of chaining: commentsAbove then commentLine.
    it('can chain commentsAbove and then commentLine, updating editor state sequentially', function () {
        $commentsAbove = ['# Initial Above'];
        $inlineComment = 'Final Inline';

        // 1. commentsAbove is called
        $this->editorMock->shouldReceive('set')
            ->with('TEST_KEY', 'test_value', null, $commentsAbove) // currentInlineComment is null initially
            ->once();

        // 2. commentLine is called
        $this->editorMock->shouldReceive('set')
            ->with('TEST_KEY', 'test_value', $inlineComment, $commentsAbove) // currentCommentsAbove should be preserved
            ->once();

        $result = $this->setter->commentsAbove($commentsAbove)->commentLine($inlineComment);

        expect($result)->toBeInstanceOf(EnvVariableSetter::class);
    });

    // Tests that multiple calls to commentLine result in the last one taking precedence.
    it('multiple commentLine calls use the last value', function () {
        $firstInline = 'First inline';
        $secondInline = 'Second inline takes precedence';

        // First call to commentLine
        $this->editorMock->shouldReceive('set')
            ->with('TEST_KEY', 'test_value', $firstInline, null)
            ->once();
        // Second call to commentLine
        $this->editorMock->shouldReceive('set')
            ->with('TEST_KEY', 'test_value', $secondInline, null) // currentCommentsAbove is still null
            ->once();

        $this->setter->commentLine($firstInline)->commentLine($secondInline);
    });

    // Tests that multiple calls to commentsAbove result in the last one taking precedence.
    it('multiple commentsAbove calls use the last value', function () {
        $firstAbove = ['# First above'];
        $secondAbove = ['# Second above takes precedence'];

        // First call to commentsAbove
        $this->editorMock->shouldReceive('set')
            ->with('TEST_KEY', 'test_value', null, $firstAbove)
            ->once();
        // Second call to commentsAbove
        $this->editorMock->shouldReceive('set')
            ->with('TEST_KEY', 'test_value', null, $secondAbove) // currentInlineComment is still null
            ->once();

        $this->setter->commentsAbove($firstAbove)->commentsAbove($secondAbove);
    });

    // Ensures that calling save() on the setter delegates to EnvManager->save().
    it('save calls env manager save', function () {
        $this->managerMock->shouldReceive('save')->once()->andReturn(true);

        $result = $this->setter->save();

        expect($result)->toBeTrue();
    });
});
