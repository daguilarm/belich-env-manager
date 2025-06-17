<?php

// Unit tests for the EnvStorage class.
// This class acts as an abstraction layer for filesystem operations (read/write)
// related to .env files, using Laravel's Filesystem component.
use Daguilar\BelichEnvManager\Services\Env\EnvStorage;
use Illuminate\Filesystem\Filesystem;

beforeEach(function () {
    $this->filesystemMock = Mockery::mock(Filesystem::class);
    $this->envStorage = new EnvStorage($this->filesystemMock);
    $this->testFilePath = '/fake/path/.env';
});

afterEach(function () {
    Mockery::close();
});

describe('File Reading Operations', function () {
    // Verifies that existing file content is correctly returned.
    it('returns content for an existing file', function () {
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

    // Verifies reading an existing but empty file.
    it('returns an empty string for an existing but empty file', function () {
        $this->filesystemMock
            ->shouldReceive('exists')
            ->with($this->testFilePath)
            ->once()
            ->andReturn(true);

        $this->filesystemMock
            ->shouldReceive('get')
            ->with($this->testFilePath)
            ->once()
            ->andReturn(''); // Simulate an empty file content

        $content = $this->envStorage->read($this->testFilePath);

        expect($content)->toBe('');
    });

    // Ensures that reading a non-existent file results in an empty string, not an error.
    it('returns an empty string for a non-existing file', function () {
        $this->filesystemMock
            ->shouldReceive('exists')
            ->with($this->testFilePath)
            ->once()
            ->andReturn(false);

        // Filesystem::get() should not be called if the file doesn't exist.
        $this->filesystemMock->shouldNotReceive('get');

        $content = $this->envStorage->read($this->testFilePath);

        expect($content)->toBe('');
    });
});

describe('File Writing Operations', function () {
    // Tests the successful writing of content to a file.
    it('successfully puts content to a file', function () {
        $contentToWrite = 'NEW_VAR=new_value';

        $this->filesystemMock
            ->shouldReceive('put')
            ->with($this->testFilePath, $contentToWrite)
            ->once()
            ->andReturn(true); // Filesystem::put can return int (bytes) or bool.

        $result = $this->envStorage->write($this->testFilePath, $contentToWrite);
        expect($result)->toBeTrue();
    });

    // Tests successful writing when Filesystem::put returns bytes written (integer).
    it('successfully puts content when filesystem returns bytes written', function () {
        $contentToWrite = 'BYTES_VAR=bytes_value';

        $this->filesystemMock
            ->shouldReceive('put')
            ->with($this->testFilePath, $contentToWrite)
            ->once()
            ->andReturn(strlen($contentToWrite)); // Simulate returning number of bytes

        $result = $this->envStorage->write($this->testFilePath, $contentToWrite);
        expect($result)->toBeTrue();
    });

    // Verifies that an exception is thrown if the filesystem write operation fails.
    it('throws an exception on write failure', function () {
        $contentToWrite = 'FAIL_VAR=fail_value';

        $this->filesystemMock
            ->shouldReceive('put')
            ->with($this->testFilePath, $contentToWrite)
            ->once()
            ->andReturn(false);

        $action = function () use ($contentToWrite) {
            $this->envStorage->write($this->testFilePath, $contentToWrite);
        };
        $expectedMessage = "Could not write to .env file: {$this->testFilePath}";

        expect($action)->toThrow(Exception::class, $expectedMessage);
    });
});
