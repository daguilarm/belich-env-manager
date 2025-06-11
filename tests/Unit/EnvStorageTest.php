<?php

use Daguilar\BelichEnvManager\Services\Env\EnvStorage;
use Illuminate\Filesystem\Filesystem;
use Mockery\MockInterface;

beforeEach(function () {
    $this->filesystemMock = Mockery::mock(Filesystem::class);
    $this->envStorage = new EnvStorage($this->filesystemMock);
    $this->testFilePath = '/fake/path/.env';
});

afterEach(function () {
    Mockery::close();
});

test('read returns content for existing file', function () {
    $expectedContent = 'APP_NAME=Laravel';

    $this->filesystemMock
        ->shouldReceive('exists')
        ->with($this->testFilePath)
        ->once()
        ->andReturn(true);

    $this->filesystemMock
        ->shouldReceive('get')
        ->with($this->testFilePath)
        ->once()
        ->andReturn($expectedContent);

    $content = $this->envStorage->read($this->testFilePath);

    expect($content)->toBe($expectedContent);
});

test('read returns empty string for non-existing file', function () {
    $this->filesystemMock
        ->shouldReceive('exists')
        ->with($this->testFilePath)
        ->once()
        ->andReturn(false);

    $this->filesystemMock->shouldNotReceive('get'); // Should not attempt to get content

    $content = $this->envStorage->read($this->testFilePath);

    expect($content)->toBe('');
});

test('write successfully puts content to file', function () {
    $contentToWrite = 'NEW_VAR=new_value';

    $this->filesystemMock
        ->shouldReceive('put')
        ->with($this->testFilePath, $contentToWrite)
        ->once()
        ->andReturn(true); // Or number of bytes, Filesystem::put can return int or bool

    $result = $this->envStorage->write($this->testFilePath, $contentToWrite);
    expect($result)->toBeTrue();
});

test('write throws exception on failure', function () {
    $contentToWrite = 'FAIL_VAR=fail_value';

    $this->filesystemMock
        ->shouldReceive('put')
        ->with($this->testFilePath, $contentToWrite)
        ->once()
        ->andReturn(false);

    $expectedMessage = "Could not write to .env file: {$this->testFilePath}";
    $this->envStorage->write($this->testFilePath, $contentToWrite);
})->throws(Exception::class, $expectedMessage);