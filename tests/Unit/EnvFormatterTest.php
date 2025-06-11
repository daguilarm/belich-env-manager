<?php

use Daguilar\BelichEnvManager\Services\Env\EnvFormatter;

beforeEach(function () {
    $this->formatter = new EnvFormatter();
});

test('it formats an empty array of lines as an empty string', function () {
    $lines = [];
    $result = $this->formatter->format($lines);
    expect($result)->toBe('');
});

test('it formats a simple variable line', function () {
    $lines = [
        ['type' => 'variable', 'key' => 'APP_NAME', 'value' => 'Laravel', 'comment_inline' => null, 'comment_above' => [], 'export' => false],
    ];
    $result = $this->formatter->format($lines);
    expect($result)->toBe('APP_NAME=Laravel'.PHP_EOL);
});

test('it formats a variable line with inline comment', function () {
    $lines = [
        ['type' => 'variable', 'key' => 'APP_DEBUG', 'value' => 'true', 'comment_inline' => 'Debug mode', 'comment_above' => [], 'export' => false],
    ];
    $result = $this->formatter->format($lines);
    expect($result)->toBe('APP_DEBUG="true" # Debug mode'.PHP_EOL);
});

test('it formats a variable line with comments above', function () {
    $lines = [
        ['type' => 'variable', 'key' => 'DB_HOST', 'value' => 'localhost', 'comment_inline' => null, 'comment_above' => ['# Database host'], 'export' => false],
    ];
    $result = $this->formatter->format($lines);
    expect($result)->toBe('# Database host'.PHP_EOL.'DB_HOST=localhost'.PHP_EOL);
});

test('it formats a variable line with both inline and above comments', function () {
    $lines = [
        ['type' => 'variable', 'key' => 'MAIL_PORT', 'value' => '587', 'comment_inline' => 'TLS', 'comment_above' => ['# Mailer port'], 'export' => false],
    ];
    $result = $this->formatter->format($lines);
    expect($result)->toBe('# Mailer port'.PHP_EOL.'MAIL_PORT=587 # TLS'.PHP_EOL);
});

test('it formats an empty line correctly', function () {
    $lines = [
        ['type' => 'empty'],
    ];
    $result = $this->formatter->format($lines);
    expect($result)->toBe(PHP_EOL);
});

test('it formats a comment line correctly', function () {
    $lines = [
        ['type' => 'comment', 'content' => '# This is a standalone comment'],
    ];
    $result = $this->formatter->format($lines);
    expect($result)->toBe('# This is a standalone comment'.PHP_EOL);
});

test('it formats a variable with export prefix', function () {
    $lines = [
        ['type' => 'variable', 'key' => 'MY_EXPORTED_VAR', 'value' => 'secret', 'comment_inline' => null, 'comment_above' => [], 'export' => true],
    ];
    $result = $this->formatter->format($lines);
    expect($result)->toBe('export MY_EXPORTED_VAR=secret'.PHP_EOL);
});

test('it quotes values when necessary', function (string $value, string $expectedFormattedValue) {
    $lines = [
        ['type' => 'variable', 'key' => 'TEST_KEY', 'value' => $value, 'comment_inline' => null, 'comment_above' => [], 'export' => false],
    ];
    $result = $this->formatter->format($lines);
    expect($result)->toBe('TEST_KEY='.$expectedFormattedValue.PHP_EOL);
})->with([
    'empty string' => ['', '""'],
    'string with spaces' => ['value with spaces', '"value with spaces"'],
    'string with #' => ['value#hash', '"value#hash"'],
    'string with =' => ['value=equals', '"value=equals"'],
    'string with single quote' => ["value's", '"value\'s"'],
    'string with double quote' => ['value "quote"', '"value \\"quote\\""'],
    'boolean true string' => ['true', '"true"'],
    'boolean false string' => ['false', '"false"'],
    'null string' => ['null', '"null"'],
    'simple numeric string' => ['12345', '12345'],
    'simple string' => ['simple', 'simple'],
]);

test('it formats multiple lines correctly', function () {
    $lines = [
        ['type' => 'comment', 'content' => '# General Settings'],
        ['type' => 'variable', 'key' => 'APP_NAME', 'value' => 'My App', 'comment_inline' => 'The application name', 'comment_above' => [], 'export' => false],
        ['type' => 'empty'],
        ['type' => 'variable', 'key' => 'APP_DEBUG', 'value' => 'false', 'comment_inline' => null, 'comment_above' => ['# Debug should be false in production'], 'export' => false],
    ];
    $expected =
        '# General Settings'.PHP_EOL.
        'APP_NAME="My App" # The application name'.PHP_EOL.
        PHP_EOL.
        '# Debug should be false in production'.PHP_EOL.
        'APP_DEBUG="false"'.PHP_EOL;

    $result = $this->formatter->format($lines);
    expect($result)->toBe($expected);
});

test('it ensures a single trailing newline if content exists', function () {
    $lines = [
        ['type' => 'variable', 'key' => 'A', 'value' => 'B', 'comment_inline' => null, 'comment_above' => [], 'export' => false],
    ];
    $result = $this->formatter->format($lines);
    // Ends with one PHP_EOL, not two or zero.
    expect(substr_count($result, PHP_EOL))->toBe(1);
    expect(str_ends_with($result, PHP_EOL))->toBeTrue();

    $linesMultiple = [
        ['type' => 'variable', 'key' => 'A', 'value' => 'B'],
        ['type' => 'variable', 'key' => 'C', 'value' => 'D'],
    ];
    $resultMultiple = $this->formatter->format($linesMultiple);
    // Each variable line adds a PHP_EOL, so total should be number of lines.
    // The rtrim in format() then ensures only one final one if the last was an EOL from a variable.
    // The final PHP_EOL is added if content is not empty.
    expect(substr_count($resultMultiple, PHP_EOL))->toBe(2);
    expect(str_ends_with($resultMultiple, PHP_EOL))->toBeTrue();
});