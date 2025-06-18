<?php

namespace Daguilar\EnvManager\Services\Env;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class EnvFormatter
{
    /**
     * Formats the structured array of lines back into a .env file content string.
     */
    public function format(array $lines): string
    {
        return Collection::make($lines)
            ->map(fn (array $line) => match ($line['type']) {
                'empty' => PHP_EOL,
                'comment' => $line['content'].PHP_EOL,
                'variable' => $this->formatVariableLine($line),
                default => '',
            })
            ->filter()
            ->pipe(fn ($coll) => $coll->isEmpty() ? '' : $coll->implode(''));
    }

    /**
     * Formats a single variable line, including comments above and inline.
     */
    private function formatVariableLine(array $line): string
    {
        $output = $this->formatBlockComments($line['comment_above'] ?? []);
        $output .= $this->buildVariableString($line);

        return $output.PHP_EOL;
    }

    /**
     * Formats block comments (comments above the variable).
     */
    private function formatBlockComments(array $comments): string
    {
        return Collection::make($comments)
            ->map(fn (string $comment) => $comment.PHP_EOL)
            ->implode('');
    }

    /**
     * Builds a string representation of a variable line.
     */
    private function buildVariableString(array $line): string
    {
        $value = $this->quoteValueIfNeeded($line['value']);
        $variable = ($line['export'] ?? false ? 'export ' : '').$line['key'].'='.$value;

        return isset($line['comment_inline'])
            ? $variable.' # '.$line['comment_inline']
            : $variable;
    }

    /**
     * Quotes a value string if it contains spaces, special characters, is empty,
     * or is a boolean/null keyword. Double quotes are used, and existing double
     * quotes within the value are escaped.
     */
    private function quoteValueIfNeeded(string $value): string
    {
        $needsQuoting = Str::contains($value, [' ', '#', '=', '"', "'"])
            || $value === ''
            || in_array(strtolower($value), ['true', 'false', 'null'], true);

        return $needsQuoting
            ? '"'.str_replace('"', '\\"', $value).'"'
            : $value;
    }
}
