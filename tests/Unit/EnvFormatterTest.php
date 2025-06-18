<?php

// Unit tests for the EnvFormatter class.
// This class is responsible for converting a structured array of "lines"
// (representing .env content) back into a string suitable for writing to a .env file.
// It handles correct quoting, comment placement, and line endings.
use Daguilar\EnvManager\Services\Env\EnvFormatter;

beforeEach(function () {
    $this->formatter = new EnvFormatter;
});

describe('Basic Line Formatting', function () {
    // Verifies that an empty input array results in an empty output string.
    it('formats an empty array of lines as an empty string', function () {
        $lines = [];
        $result = $this->formatter->format($lines);
        expect($result)->toBe('');
    });

    // Tests formatting of a standard KEY=VALUE variable.
    it('formats a simple variable line', function () {
        $lines = [
            ['type' => 'variable', 'key' => 'APP_NAME', 'value' => 'Laravel', 'comment_inline' => null, 'comment_above' => [], 'export' => false],
        ];
        $result = $this->formatter->format($lines);
        expect($result)->toBe('APP_NAME=Laravel'.PHP_EOL);
    });

    // Ensures empty lines are preserved and correctly formatted.
    it('formats an empty line correctly', function () {
        $lines = [
            ['type' => 'empty'],
        ];
        $result = $this->formatter->format($lines);
        expect($result)->toBe(PHP_EOL);
    });

    // Checks formatting for standalone comment lines.
    it('formats a comment line correctly', function () {
        $lines = [
            ['type' => 'comment', 'content' => '# This is a standalone comment'],
        ];
        $result = $this->formatter->format($lines);
        expect($result)->toBe('# This is a standalone comment'.PHP_EOL);
    });
});

describe('Comment and Export Formatting', function () {
    // Tests variables with only an inline comment.
    it('formats a variable line with an inline comment', function () {
        $lines = [
            ['type' => 'variable', 'key' => 'APP_DEBUG', 'value' => 'true', 'comment_inline' => 'Debug mode', 'comment_above' => [], 'export' => false],
        ];
        $result = $this->formatter->format($lines);
        expect($result)->toBe('APP_DEBUG="true" # Debug mode'.PHP_EOL);
    });

    // Tests variables with only comments above.
    it('formats a variable line with comments above', function () {
        $lines = [
            ['type' => 'variable', 'key' => 'DB_HOST', 'value' => 'localhost', 'comment_inline' => null, 'comment_above' => ['# Database host'], 'export' => false],
        ];
        $result = $this->formatter->format($lines);
        expect($result)->toBe('# Database host'.PHP_EOL.'DB_HOST=localhost'.PHP_EOL);
    });

    // Tests variables with both inline and above comments.
    it('formats a variable line with both inline and above comments', function () {
        $lines = [
            ['type' => 'variable', 'key' => 'MAIL_PORT', 'value' => '587', 'comment_inline' => 'TLS', 'comment_above' => ['# Mailer port'], 'export' => false],
        ];
        $result = $this->formatter->format($lines);
        expect($result)->toBe('# Mailer port'.PHP_EOL.'MAIL_PORT=587 # TLS'.PHP_EOL);
    });

    // Verifies that the 'export' prefix is correctly added if the line is marked for export.
    it('formats a variable with export prefix', function () {
        $lines = [
            ['type' => 'variable', 'key' => 'MY_EXPORTED_VAR', 'value' => 'secret', 'comment_inline' => null, 'comment_above' => [], 'export' => true],
        ];
        $result = $this->formatter->format($lines);
        expect($result)->toBe('export MY_EXPORTED_VAR=secret'.PHP_EOL);
    });

    // Tests a variable with export, inline comment, and comments above.
    it('formats a variable with export prefix and all comments', function () {
        $lines = [
            ['type' => 'variable', 'key' => 'EXPORT_ALL', 'value' => 'all_value', 'comment_inline' => 'Inline for export', 'comment_above' => ['# Above for export'], 'export' => true],
        ];
        $result = $this->formatter->format($lines);
        $expected = '# Above for export'.PHP_EOL.'export EXPORT_ALL=all_value # Inline for export'.PHP_EOL;
        expect($result)->toBe($expected);
    });
});

describe('Value Quoting and Complex Scenarios', function () {
    // Uses a dataset to test various value types and their expected quoting behavior.
    it('quotes values when necessary', function (string $value, string $expectedFormattedValue) {
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
        'string with single quote' => ["value's", '"value\'s"'], // Single quotes in value are fine, outer quotes are double
        'string with double quote' => ['value "quote"', '"value \\"quote\\""'], // Inner double quotes must be escaped
        'boolean true string' => ['true', '"true"'], // "true" is a string that needs quoting
        'boolean false string' => ['false', '"false"'], // "false" is a string that needs quoting
        'null string' => ['null', '"null"'], // "null" is a string that needs quoting
        'simple numeric string' => ['12345', '12345'], // Simple numerics are not quoted
        'simple string' => ['simple', 'simple'], // Simple strings without special chars/spaces are not quoted
        'string that is just a quote' => ['"', '"\\""'],
        'string that is just a hash' => ['#', '"#"'],
    ]);

    // Tests formatting of a sequence of different line types.
    it('formats multiple lines correctly', function () {
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

    // Ensures that the final output always ends with a single newline character if there's content.
    it('ensures a single trailing newline if content exists', function () {
        $lines = [
            ['type' => 'variable', 'key' => 'A', 'value' => 'B', 'comment_inline' => null, 'comment_above' => [], 'export' => false],
        ];
        $result = $this->formatter->format($lines);
        expect(substr_count($result, PHP_EOL))->toBe(1);
        expect(str_ends_with($result, PHP_EOL))->toBeTrue();

        $linesMultiple = [
            ['type' => 'variable', 'key' => 'A', 'value' => 'B'],
            ['type' => 'variable', 'key' => 'C', 'value' => 'D'],
        ];
        $resultMultiple = $this->formatter->format($linesMultiple);
        // Each variable line contributes one PHP_EOL.
        expect(substr_count($resultMultiple, PHP_EOL))->toBe(2);
        expect(str_ends_with($resultMultiple, PHP_EOL))->toBeTrue();
    });

    // Tests formatting of multiple lines in 'comment_above'.
    it('formats a variable line with multiple lines in comments_above', function () {
        $lines = [
            ['type' => 'variable', 'key' => 'MULTI_ABOVE', 'value' => 'multi_val', 'comment_inline' => null, 'comment_above' => ['# Line 1', '# Line 2', '# Line 3'], 'export' => false],
        ];
        $result = $this->formatter->format($lines);
        $expected = '# Line 1'.PHP_EOL.'# Line 2'.PHP_EOL.'# Line 3'.PHP_EOL.'MULTI_ABOVE=multi_val'.PHP_EOL;
        expect($result)->toBe($expected);
    });

});
