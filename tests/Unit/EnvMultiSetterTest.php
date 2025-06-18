<?php

// Unit tests for the EnvMultiSetter class.
// This class provides a fluent interface for setting multiple environment variables,
// along with their respective comments, in a batch. Changes are applied
// to the EnvEditor upon calling save(), which then delegates to EnvManager.
use Daguilar\EnvManager\Services\Env\EnvEditor;
use Daguilar\EnvManager\Services\Env\EnvMultiSetter;
use Daguilar\EnvManager\Services\EnvManager;

beforeEach(function () {
    $this->editorMock = Mockery::mock(EnvEditor::class);
    $this->managerMock = Mockery::mock(EnvManager::class);

    $this->multiSetter = new EnvMultiSetter($this->editorMock, $this->managerMock);
});

afterEach(function () {
    Mockery::close();
});

describe('EnvMultiSetter Core Functionality', function () {
    // Verifies that multiple items, each with their own comments, can be set
    // and that EnvEditor->set() is called for each item with the correct parameters.
    it('can set multiple items with individual comments and save them', function () {
        $this->editorMock->shouldReceive('set')
            ->with('KEY1', 'value1', 'inline1', ['above1'])
            ->once();
        $this->editorMock->shouldReceive('set')
            ->with('KEY2', 'value2', 'inline2', ['above2'])
            ->once();

        $this->managerMock->shouldReceive('save')->once()->andReturn(true);

        $result = $this->multiSetter
            ->setItem('KEY1', 'value1')
            ->commentLine('inline1')
            ->commentsAbove(['above1'])
            ->setItem('KEY2', 'value2')
            ->commentLine('inline2')
            ->commentsAbove(['above2'])
            ->save();

        expect($result)->toBeTrue();
    });

    // Ensures that if save() is called after setting an item and its comments,
    // the last item's state is correctly processed and passed to EnvEditor->set().
    it('save finalizes the last item before processing operations', function () {
        $this->editorMock->shouldReceive('set')
            ->with('LAST_KEY', 'last_value', 'last_inline', null)
            ->once();

        $this->managerMock->shouldReceive('save')->once()->andReturn(true);

        $result = $this->multiSetter
            ->setItem('LAST_KEY', 'last_value')
            ->commentLine('last_inline')
            ->save();

        expect($result)->toBeTrue();
    });

    // Tests that save() can be called even if no items were set,
    // in which case EnvEditor->set() should not be called.
    it('save works correctly with no items set', function () {
        $this->editorMock->shouldNotReceive('set');
        $this->managerMock->shouldReceive('save')->once()->andReturn(true);

        $result = $this->multiSetter->save();

        expect($result)->toBeTrue();
    });

    // Verifies that the internal state (operations queue, active key) is reset after save(),
    // preventing previously set items from being re-processed on a subsequent save.
    it('state is reset after save', function () {
        $this->editorMock->shouldReceive('set')->with('KEY1', 'value1', null, null)->once();
        $this->managerMock->shouldReceive('save')->once()->andReturn(true);
        $this->multiSetter->setItem('KEY1', 'value1')->save();

        $reflection = new ReflectionClass(EnvMultiSetter::class);
        $operationsProp = $reflection->getProperty('operations');
        $operationsProp->setAccessible(true);
        expect($operationsProp->getValue($this->multiSetter))->toBeEmpty();

        $activeKeyProp = $reflection->getProperty('activeKey');
        $activeKeyProp->setAccessible(true);
        expect($activeKeyProp->getValue($this->multiSetter))->toBeNull();

        // Second save should only process new items.
        $this->editorMock->shouldReceive('set')->with('KEY2', 'value2', null, null)->once();
        $this->managerMock->shouldReceive('save')->once()->andReturn(true);
        $this->multiSetter->setItem('KEY2', 'value2')->save();
    });

    // Verifies that if setItem is called multiple times, comments apply to the last set item.
    it('comments apply to the correct item after multiple setItem calls', function () {
        // KEY1 is set, then KEY2 is set. Comments should apply to KEY2.
        // finalizeCurrentItem for KEY1 will be called when setItem for KEY2 is called.
        // KEY1 should be added to operations with null comments.
        $this->editorMock->shouldReceive('set')
            ->with('KEY1', 'value1', null, null) // This is from finalizeCurrentItem for KEY1
            ->once();
        // Then KEY2 is set with its comments.
        $this->editorMock->shouldReceive('set')
            ->with('KEY2', 'value2', 'inline_for_key2', ['above_for_key2'])
            ->once();

        $this->managerMock->shouldReceive('save')->once()->andReturn(true);

        $result = $this->multiSetter
            ->setItem('KEY1', 'value1')
            ->setItem('KEY2', 'value2') // This finalizes KEY1
            ->commentLine('inline_for_key2')
            ->commentsAbove(['above_for_key2'])
            ->save(); // This finalizes KEY2 and saves all operations

        expect($result)->toBeTrue();

        // Second save should only process new items.
        $this->editorMock->shouldReceive('set')->with('KEY2', 'value2', null, null)->once();
        $this->managerMock->shouldReceive('save')->once()->andReturn(true);
        $this->multiSetter->setItem('KEY2', 'value2')->save();
    });
});

describe('EnvMultiSetter Item Configuration Scenarios', function () {
    // Tests setting an item without any associated comments.
    it('can set an item without any comments', function () {
        $this->editorMock->shouldReceive('set')
            ->with('SIMPLE_KEY', 'simple_value', null, null)
            ->once();
        $this->managerMock->shouldReceive('save')->once()->andReturn(true);

        $result = $this->multiSetter
            ->setItem('SIMPLE_KEY', 'simple_value')
            ->save();

        expect($result)->toBeTrue();
    });

    // Tests setting an item with only an inline comment.
    it('can set an item with only inline comment', function () {
        $this->editorMock->shouldReceive('set')
            ->with('INLINE_ONLY_KEY', 'inline_value', 'just inline', null)
            ->once();
        $this->managerMock->shouldReceive('save')->once()->andReturn(true);

        $result = $this->multiSetter
            ->setItem('INLINE_ONLY_KEY', 'inline_value')
            ->commentLine('just inline')
            ->save();

        expect($result)->toBeTrue();
    });

    // Tests setting an item with only comments above it.
    it('can set an item with only comments above', function () {
        $this->editorMock->shouldReceive('set')
            ->with('ABOVE_ONLY_KEY', 'above_value', null, ['# just above'])
            ->once();
        $this->managerMock->shouldReceive('save')->once()->andReturn(true);

        $result = $this->multiSetter
            ->setItem('ABOVE_ONLY_KEY', 'above_value')
            ->commentsAbove(['# just above'])
            ->save();

        expect($result)->toBeTrue();
    });

    // Tests setting an item and then immediately saving, without explicit comment calls.
    it('can set an item and save without explicit comment calls', function () {
        $this->editorMock->shouldReceive('set')
            ->with('IMMEDIATE_SAVE_KEY', 'immediate_value', null, null) // Expects null for comments
            ->once();
        $this->managerMock->shouldReceive('save')->once()->andReturn(true);

        $result = $this->multiSetter
            ->setItem('IMMEDIATE_SAVE_KEY', 'immediate_value')
            ->save(); // finalizeCurrentItem will add this to operations

        expect($result)->toBeTrue();
    });

});

describe('EnvMultiSetter Guard Clauses', function () {
    // Ensures that attempting to set an inline comment before an item (key/value)
    // has been defined throws a LogicException.
    it('calling commentLine before setItem throws LogicException', function () {
        $this->multiSetter->commentLine('test');
    })->throws(LogicException::class, 'setItem() must be called before adding an inline comment.');

    // Ensures that attempting to set comments above before an item (key/value)
    // has been defined throws a LogicException.
    it('calling commentsAbove before setItem throws LogicException', function () {
        $this->multiSetter->commentsAbove(['test']);
    })->throws(LogicException::class, 'setItem() must be called before adding comments above.');
});
