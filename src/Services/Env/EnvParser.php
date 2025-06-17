<?php

declare(strict_types=1);

namespace Daguilar\BelichEnvManager\Services\Env;

use Illuminate\Support\Str;

class EnvParser
{
    /**
     * Parses the raw string content of a .env file into a structured array.
     * Each element in the array represents a line from the .env file,
     * categorized by type (variable, comment, empty) and including
     * relevant details like key, value, and associated comments.
     */
    public function parse(string $content): array
    {
        if ($content === '') {
            return [];
        }

        // Handle single newline case specifically
        // \R matches any Unicode newline sequence.
        if (preg_match('/^\R$/D', $content)) {
            return [['type' => 'empty']];
        }

        // Split content by any newline sequence.
        $rawLines = preg_split('/\R/', $content) ?: [];
        $initialState = ['lines' => [], 'pendingComments' => []];

        $state = collect($rawLines)
            ->reduce(function (array $state, string $rawLine): array {
                $trimmedLine = trim($rawLine);

                // Determine line type and delegate to the appropriate handler.
                // The order of checks (empty, comment, variable) is important.
                return match (true) {
                    $this->isEmptyLine($trimmedLine) => $this->handleEmptyLine($state),
                    $this->isCommentLine($trimmedLine) => $this->handleCommentLine($state, $rawLine),
                    $this->isVariableLine($trimmedLine) => $this->handleVariableLine($state, $trimmedLine, $rawLine),
                    default => $this->handleUnknownLine($state, $rawLine)
                };
            }, $initialState);

        return $this->finalizeState($state);
    }

    /**
     * Checks if a line is effectively empty (contains only whitespace).
     */
    private function isEmptyLine(string $line): bool
    {
        return $line === '';
    }

    /**
     * Checks if a line is a comment (starts with '#').
     */
    private function isCommentLine(string $line): bool
    {
        return Str::startsWith($line, '#');
    }

    /**
     * Checks if a line appears to be a variable assignment (KEY=VALUE).
     * This is a preliminary check; more detailed parsing happens in `parseVariable`.
     */
    private function isVariableLine(string $line): bool
    {
        // Regex checks for optional 'export', a valid key, an equals sign, and then anything.
        return (bool) preg_match(
            '/^\s*(export\s+)?[A-Za-z_][A-Za-z0-9_]*\s*=.*$/',
            $line
        );
    }

    /**
     * Parses a line identified as a variable into its components:
     * key, value, export flag, and inline comment.
     */
    private function parseVariable(string $trimmedLine): ?array
    {
        // Regex to capture: export (optional), key, value (quoted or unquoted), and inline comment (optional).
        // Quoted values can contain '#' and other special characters.
        $pattern = '/^\s*(export\s+)?(?<key>[A-Za-z_][A-Za-z0-9_]*)\s*=\s*'
            .'(?:(?<q>["\'])(?<value_quoted>(?:\\\\.|(?!\k<q>).)*)\k<q>'
            .'|(?<value_unquoted>[^#]*?))(?:\s*#\s*(?<comment>.*))?\s*$/';

        if (! preg_match($pattern, $trimmedLine, $matches)) {
            return null;
        }

        $value = $this->extractValue($matches);
        $inlineComment = isset($matches['comment']) ? trim($matches['comment']) : null;

        return [
            'key' => $matches['key'],
            'value' => $value,
            'export' => ! empty(trim($matches[1] ?? '')),
            'comment_inline' => $inlineComment,
        ];
    }

    /**
     * Extracts and unescapes the value from regex matches.
     */
    private function extractValue(array $matches): string
    {
        if (isset($matches['value_quoted']) && $matches['value_quoted'] !== '') {
            // Unescape common sequences like \\, \", \' if they were literally in the .env
            return str_replace(['\\\\', '\\"', "\\'"], ['\\', '"', "'"], $matches['value_quoted']);
        }

        return array_key_exists('value_unquoted', $matches)
            ? trim($matches['value_unquoted'])
            : '';
    }

    /**
     * Handles an empty line during parsing.
     * Flushes any pending comments as standalone comments before adding the empty line.
     */
    private function handleEmptyLine(array $state): array
    {
        $newLines = $state['lines'];
        $pending = $state['pendingComments'];

        // Flush pending comments as standalone comments
        if (! empty($pending)) {
            $commentLines = array_map(
                fn ($comment) => ['type' => 'comment', 'content' => $comment],
                $pending
            );
            $newLines = [...$newLines, ...$commentLines];
            $pending = [];
        }

        $newLines[] = ['type' => 'empty'];

        return ['lines' => $newLines, 'pendingComments' => $pending];
    }

    /**
     * Handles a comment line during parsing.
     * Adds the raw comment line to the list of pending comments.
     */
    private function handleCommentLine(array $state, string $rawLine): array
    {
        $state['pendingComments'][] = $rawLine;

        return $state;
    }

    /**
     * Handles a variable line during parsing.
     * Parses the variable and associates any pending comments as 'comment_above'.
     * If parsing fails, treats the line as an unknown/comment line.
     */
    private function handleVariableLine(array $state, string $trimmedLine, string $rawLine): array
    {
        $variable = $this->parseVariable($trimmedLine);

        if ($variable === null) {
            return $this->handleUnknownLine($state, $rawLine);
        }

        $newLines = $state['lines'];
        $pending = $state['pendingComments'];

        $newLines[] = [
            'type' => 'variable',
            'key' => $variable['key'],
            'value' => $variable['value'],
            'comment_inline' => $variable['comment_inline'],
            'comment_above' => $pending,
            'export' => $variable['export'],
        ];

        return ['lines' => $newLines, 'pendingComments' => []];
    }

    /**
     * Handles lines that do not match any other recognized type (empty, comment, variable).
     * These are typically treated as standalone comments to preserve their content.
     * Flushes any pending comments before adding this unknown line as a comment.
     */
    private function handleUnknownLine(array $state, string $rawLine): array
    {
        $newLines = $state['lines'];
        $pending = $state['pendingComments'];

        // Flush pending comments first
        if (! empty($pending)) {
            $commentLines = array_map(
                fn ($comment) => ['type' => 'comment', 'content' => $comment],
                $pending
            );
            $newLines = [...$newLines, ...$commentLines];
            $pending = [];
        }

        $newLines[] = ['type' => 'comment', 'content' => $rawLine];

        return ['lines' => $newLines, 'pendingComments' => $pending];
    }

    /**
     * Finalizes the parsing state by adding any remaining pending comments
     * as standalone comments at the end of the parsed lines.
     */
    private function finalizeState(array $state): array
    {
        $newLines = $state['lines'];
        $pending = $state['pendingComments'];

        // Add any remaining comments as standalone
        if (! empty($pending)) {
            $commentLines = array_map(
                fn ($comment) => ['type' => 'comment', 'content' => $comment],
                $pending
            );
            $newLines = [...$newLines, ...$commentLines];
        }

        return $newLines;
    }
}
