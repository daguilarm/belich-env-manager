<?php

declare(strict_types=1);

namespace Daguilar\BelichEnvManager\Services\Env;

use Illuminate\Support\Str;

class EnvParser
{
    /**
     * Parses .env content into a structured array.
     */
    public function parse(string $content): array
    {
        if ($content === '') {
            return [];
        }

        // Handle single newline case specifically
        if (preg_match('/^\R$/D', $content)) {
            return [['type' => 'empty']];
        }

        $rawLines = preg_split('/\R/', $content) ?: [];
        $initialState = ['lines' => [], 'pendingComments' => []];

        $state = collect($rawLines)
            ->reduce(function (array $state, string $rawLine): array {
                $trimmedLine = trim($rawLine);

                return match (true) {
                    $this->isEmptyLine($trimmedLine) => $this->handleEmptyLine($state),
                    $this->isCommentLine($trimmedLine) => $this->handleCommentLine($state, $rawLine),
                    $this->isVariableLine($trimmedLine) => $this->handleVariableLine($state, $trimmedLine, $rawLine),
                    default => $this->handleUnknownLine($state, $rawLine)
                };
            }, $initialState);

        return $this->finalizeState($state);
    }

    private function isEmptyLine(string $line): bool
    {
        return $line === '';
    }

    private function isCommentLine(string $line): bool
    {
        return Str::startsWith($line, '#');
    }

    private function isVariableLine(string $line): bool
    {
        return (bool) preg_match(
            '/^\s*(export\s+)?[A-Za-z_][A-Za-z0-9_]*\s*=.*$/',
            $line
        );
    }

    private function parseVariable(string $trimmedLine): ?array
    {
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

    private function extractValue(array $matches): string
    {
        if (isset($matches['value_quoted']) && $matches['value_quoted'] !== '') {
            return str_replace(['\\\\', '\\"', "\\'"], ['\\', '"', "'"], $matches['value_quoted']);
        }

        return array_key_exists('value_unquoted', $matches)
            ? trim($matches['value_unquoted'])
            : '';
    }

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

    private function handleCommentLine(array $state, string $rawLine): array
    {
        $state['pendingComments'][] = $rawLine;

        return $state;
    }

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
