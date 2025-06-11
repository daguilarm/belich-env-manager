<?php

use Daguilar\BelichEnvManager\Services\Env\EnvEditor;
use Daguilar\BelichEnvManager\Services\Env\EnvMultiSetter;
use Daguilar\BelichEnvManager\Services\EnvManager;
use Mockery\MockInterface;

beforeEach(function () {
    $this->editorMock = Mockery::mock(EnvEditor::class);
    $this->managerMock = Mockery::mock(EnvManager::class);

    $this->multiSetter = new EnvMultiSetter($this->editorMock, $this->managerMock);
});

afterEach(function () {
    Mockery::close();
});

test('it can set multiple items and save them', function () {
    $this->editorMock->shouldReceive('set')
        ->with('KEY1', 'value1', 'inline1', ['above1'])
        ->once();
    $this->editorMock->shouldReceive('set')
        ->with('KEY2', 'value2', 'inline2', ['above2']) // Added comments for the second one
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

test('save finalizes the last item before processing operations', function () {
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

test('save works correctly with no items set', function () {
    $this->editorMock->shouldNotReceive('set'); // No items, so set should not be called
    $this->managerMock->shouldReceive('save')->once()->andReturn(true);

    $result = $this->multiSetter->save();

    expect($result)->toBeTrue();
});

test('calling commentLine before setItem throws LogicException', function () {
    $this->multiSetter->commentLine('test');
})->throws(LogicException::class, 'setItem() must be called before adding an inline comment.');

test('calling commentsAbove before setItem throws LogicException', function () {
    $this->multiSetter->commentsAbove(['test']);
})->throws(LogicException::class, 'setItem() must be called before adding comments above.');

test('state is reset after save', function () {
    // First save
    $this->editorMock->shouldReceive('set')->with('KEY1', 'value1', null, null)->once();
    $this->managerMock->shouldReceive('save')->once()->andReturn(true);
    $this->multiSetter->setItem('KEY1', 'value1')->save();

    // Reflection to check internal state (for testing purposes only)
    $reflection = new ReflectionClass(EnvMultiSetter::class);

    $operationsProp = $reflection->getProperty('operations');
    $operationsProp->setAccessible(true);
    expect($operationsProp->getValue($this->multiSetter))->toBeEmpty();

    $activeKeyProp = $reflection->getProperty('activeKey');
    $activeKeyProp->setAccessible(true);
    expect($activeKeyProp->getValue($this->multiSetter))->toBeNull();

    // Second save, should not re-process old operations
    $this->editorMock->shouldReceive('set')->with('KEY2', 'value2', null, null)->once();
    $this->managerMock->shouldReceive('save')->once()->andReturn(true); // Save is called again
    $this->multiSetter->setItem('KEY2', 'value2')->save();
});

test('it can set an item without any comments', function () {
    $this->editorMock->shouldReceive('set')
        ->with('SIMPLE_KEY', 'simple_value', null, null)
        ->once();
    $this->managerMock->shouldReceive('save')->once()->andReturn(true);

    $result = $this->multiSetter
        ->setItem('SIMPLE_KEY', 'simple_value')
        ->save();

    expect($result)->toBeTrue();
});

test('it can set an item with only inline comment', function () {
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

test('it can set an item with only comments above', function () {
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