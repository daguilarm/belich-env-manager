<?php

namespace Daguilar\BelichEnvManager\Services\Env;

use Illuminate\Support\Str;

class EnvParser
{
    /**
     * Parses the .env content string into a structured array.
     */
    public function parse(string $content): array
    {
        // Handle empty input string
        if ($content === '') {
            return [];
        }

        // Handle input string consisting of only a single newline character
        if (preg_match('/^(\r\n|\n|\r)$/D', $content)) {
            return [['type' => 'empty']];
        }

        $lines = [];
        $pendingCommentsAbove = [];
        $rawLinesArray = preg_split("/(\r\n|\n|\r)/", $content);

        foreach ($rawLinesArray as $rawLine) {
            $trimmedLine = trim($rawLine);

            if ($this->isEmptyLine($trimmedLine, $rawLine, $lines, $pendingCommentsAbove)) {
                continue;
            }

            if ($this->isCommentLine($trimmedLine, $rawLine, $pendingCommentsAbove)) {
                continue;
            }

            if ($this->isVariableLine($trimmedLine, $rawLine, $lines, $pendingCommentsAbove)) {
                continue;
            }

            $this->handleFallbackLine($rawLine, $lines, $pendingCommentsAbove);
        }

        // Add any trailing comments
        if (! empty($pendingCommentsAbove)) {
            $trailingCommentLines = collect($pendingCommentsAbove)
                ->map(fn ($commentContent) => ['type' => 'comment', 'content' => $commentContent])
                ->all();
            array_push($lines, ...$trailingCommentLines);
        }

        return $lines;
    }

    /**
     * Checks if a line is empty and handles preceding comments if any.
     * Modifies $lines and $pendingCommentsAbove by reference.
     */
    private function isEmptyLine(string $trimmedLine, string $rawLine, array &$lines, array &$pendingCommentsAbove): bool
    {
        if (! empty($trimmedLine)) {
            return false;
        }

        if (! empty($pendingCommentsAbove)) {
            $commentLines = collect($pendingCommentsAbove)
                ->map(fn ($commentContent) => ['type' => 'comment', 'content' => $commentContent])
                ->all();
            array_push($lines, ...$commentLines);
        }
        $lines[] = ['type' => 'empty'];
        $pendingCommentsAbove = []; // Reset after handling the empty line and its preceding comments

        return true;
    }

    /**
     * Checks if a line is a comment and adds it to pending comments.
     * Modifies $pendingCommentsAbove by reference.
     */
    private function isCommentLine(string $trimmedLine, string $rawLine, array &$pendingCommentsAbove): bool
    {
        if (! Str::startsWith($trimmedLine, '#')) {
            return false;
        }
        $pendingCommentsAbove[] = $rawLine; // Use rawLine to preserve original comment formatting

        return true;
    }

    /**
     * Checks if a line is a variable assignment and parses it.
     * Modifies $lines and $pendingCommentsAbove by reference.
     */
    private function isVariableLine(string $trimmedLine, string $rawLine, array &$lines, array &$pendingCommentsAbove): bool
    {
        if (! preg_match('/^(export\s+)?(?<key>[A-Za-z_0-9]+)\s*=\s*(?<value>.*)?$/', $trimmedLine, $matches)) {
            return false;
        }

        $key = $matches['key'];
        $valueString = $matches['value'] ?? ''; // Raw value string from regex

        $inlineComment = $this->extractInlineCommentFromValueString($valueString); // $valueString is modified by reference
        $finalValue = $this->unquoteValueString($valueString);

        $lines[] = [
            'type' => 'variable',
            'key' => $key,
            'value' => $finalValue,
            'comment_inline' => $inlineComment,
            'comment_above' => $pendingCommentsAbove, // Assigns the current pending comments to this variable
            'export' => Str::startsWith($trimmedLine, 'export'),
        ];
        $pendingCommentsAbove = []; // Reset after associating with a variable

        return true;
    }

    /**
     * Extracts an inline comment from a value string and cleans the value string.
     * The value string is passed by reference and will be modified.
     */
    private function extractInlineCommentFromValueString(string &$valueString): ?string
    {
        $comment = null;

        if (Str::contains($valueString, '#')) {
            $parts = Str::of($valueString)->explode('#', 2);
            $valueString = trim($parts[0]); // Modify the original string by removing the comment part
            $comment = trim($parts[1]);
        }

        return $comment;
    }

    /**
     * Removes surrounding quotes from a value string if present.
     */
    private function unquoteValueString(string $valueString): string
    {
        // Check if the value is quoted with double or single quotes
        if (preg_match('/^"(.*)"$/s', $valueString, $double_q_matches)) {
            // Return content within double quotes
            return $double_q_matches[1];
        } elseif (preg_match("/^'(.*)'$/s", $valueString, $single_q_matches)) {
            // Return content within single quotes
            return $single_q_matches[1];
        }

        return $valueString; // Return as is if not quoted
    }

    /**
     * Handles lines that do not match other types (empty, comment, variable).
     * Treats them as comments to preserve their content.
     */
    private function handleFallbackLine(string $rawLine, array &$lines, array &$pendingCommentsAbove): void
    {
        if (! empty($pendingCommentsAbove)) {
            $commentLines = collect($pendingCommentsAbove)
                ->map(fn ($commentContent) => ['type' => 'comment', 'content' => $commentContent])
                ->all();
            array_push($lines, ...$commentLines);
        }
        $lines[] = ['type' => 'comment', 'content' => $rawLine]; // Treat as comment to preserve
        $pendingCommentsAbove = [];
    }
}
