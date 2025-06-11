<?php

use Daguilar\BelichEnvManager\Services\Env\EnvParser;

beforeEach(function () {
    $this->parser = new EnvParser;
});

test('it parses an empty string as an empty array', function () {
    $result = $this->parser->parse('');
    expect($result)->toBeEmpty();
});

test('it parses a simple variable assignment', function () {
    $content = 'APP_NAME=Laravel';
    $result = $this->parser->parse($content);

    expect($result)->toBe([
        [
            'type' => 'variable',
            'key' => 'APP_NAME',
            'value' => 'Laravel',
            'comment_inline' => null,
            'comment_above' => [],
            'export' => false,
        ],
    ]);
});

test('it parses a variable with an inline comment', function () {
    $content = 'APP_DEBUG=true # This is a comment';
    $result = $this->parser->parse($content);

    expect($result)->toBe([
        [
            'type' => 'variable',
            'key' => 'APP_DEBUG',
            'value' => 'true',
            'comment_inline' => 'This is a comment',
            'comment_above' => [],
            'export' => false,
        ],
    ]);
});

test('it parses a variable with a block comment above', function () {
    $content = "# Important setting\nAPP_KEY=base64:somekey";
    $result = $this->parser->parse($content);

    expect($result)->toBe([
        [
            'type' => 'variable',
            'key' => 'APP_KEY',
            'value' => 'base64:somekey',
            'comment_inline' => null,
            'comment_above' => ['# Important setting'],
            'export' => false,
        ],
    ]);
});

test('it parses an empty line', function () {
    $content = "\n"; // A single empty line
    $result = $this->parser->parse($content);
    expect($result)->toBe([
        ['type' => 'empty'],
    ]);
});

test('it parses a comment line', function () {
    $content = '# This is just a comment';
    $result = $this->parser->parse($content);
    expect($result)->toBe([
        ['type' => 'comment', 'content' => '# This is just a comment'],
    ]);
});

test('it parses a variable with export prefix', function () {
    $content = 'export MY_VAR=exported_value';
    $result = $this->parser->parse($content);

    expect($result)->toBe([
        [
            'type' => 'variable',
            'key' => 'MY_VAR',
            'value' => 'exported_value',
            'comment_inline' => null,
            'comment_above' => [],
            'export' => true,
        ],
    ]);
});

test('it parses quoted values correctly', function () {
    $content = 'APP_NAME="My Application"';
    $result = $this->parser->parse($content);
    expect($result[0]['value'])->toBe('My Application');

    $contentSingle = "APP_ENV='staging'";
    $resultSingle = $this->parser->parse($contentSingle);
    expect($resultSingle[0]['value'])->toBe('staging');
});
