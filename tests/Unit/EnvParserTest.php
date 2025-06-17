<?php

// Unit tests for the EnvParser class.
// This class is responsible for taking a raw string (typically the content of a .env file)
// and parsing it into a structured array of "lines". Each line item in the array
// details its type (variable, comment, empty), key, value, and associated comments.
use Daguilar\BelichEnvManager\Services\Env\EnvParser;

beforeEach(function () {
    $this->parser = new EnvParser;
});

describe('Basic Parsing', function () {
    // Ensures that an empty input string results in an empty array, not null or an error.
    it('parses an empty string as an empty array', function () {
        $result = $this->parser->parse('');
        expect($result)->toBeEmpty();
    });

    it('parses a simple variable assignment', function () {
        // Standard KEY=VALUE format.
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

    it('parses an empty line', function () {
        // Empty lines are significant for formatting and should be preserved as 'empty' type.
        $content = "\n";
        $result = $this->parser->parse($content);
        expect($result)->toBe([
            ['type' => 'empty'],
        ]);
    });

    it('parses a comment line', function () {
        // Lines starting with '#' are treated as standalone comments.
        $content = '# This is just a comment';
        $result = $this->parser->parse($content);
        expect($result)->toBe([
            ['type' => 'comment', 'content' => '# This is just a comment'],
        ]);
    });
});

describe('Comment Handling', function () {
    it('parses a variable with an inline comment', function () {
        // Comments appearing after a variable on the same line.
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

    it('parses a variable with a block comment above', function () {
        // Comment lines immediately preceding a variable assignment
        // are associated with that variable as 'comment_above'.
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
});

describe('Special Cases', function () {
    it('parses a variable with export prefix', function () {
        // Handles lines like 'export KEY=VALUE'.
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

    it('parses quoted values correctly', function () {
        // Values enclosed in double or single quotes should have the quotes stripped.
        $content = 'APP_NAME="My Application"';
        $result = $this->parser->parse($content);
        expect($result[0]['value'])->toBe('My Application');

        $contentSingle = "APP_ENV='staging'";
        $resultSingle = $this->parser->parse($contentSingle);
        // Verifies single quotes are also handled.
        expect($resultSingle[0]['value'])->toBe('staging');
    });
});