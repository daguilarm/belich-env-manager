<?php

namespace Daguilar\BelichEnvManager\Services\Env;

use Illuminate\Support\Str;

class EnvFormatter
{
    /**
     * Builds the .env content string from a structured array of lines.
     */
    public function format(array $lines): string
    {
        $content = '';
        collect($lines)->each(function ($line) use (&$content) {
            match ($line['type']) {
                'empty' => $content .= PHP_EOL,
                'comment' => $content .= $line['content'].PHP_EOL,
                'variable' => $content .= $this->formatVariableLine($line),
                default => null, // Or throw an exception for unknown type
            };
        });

        return $content ? rtrim($content, PHP_EOL).PHP_EOL : '';
    }

    /**
     * Formats a single variable line, including its comments and value.
     *
     * @param  array  $line  The structured line data for a variable.
     * @return string The formatted variable line string.
     */
    private function formatVariableLine(array $line): string
    {
        $output = $this->formatCommentsAbove($line['comment_above'] ?? []);

        $formattedValue = $this->quoteValueIfNeeded((string) $line['value']);
        $variableString = $this->buildCoreVariableString(
            $line['key'],
            $formattedValue,
            $line['export'] ?? false
        );

        if (! empty($line['comment_inline'])) {
            $variableString .= ' # '.$line['comment_inline'];
        }

        $output .= $variableString.PHP_EOL;

        return $output;
    }

    /**
     * Formats the block comments that appear above a variable.
     */
    private function formatCommentsAbove(array $commentsAbove): string
    {
        if (empty($commentsAbove)) {
            return '';
        }

        return collect($commentsAbove)
            ->map(fn (string $comment) => $comment.PHP_EOL)
            ->implode('');
    }

    /**
     * Quotes a value if it contains special characters or is an empty/boolean/null string.
     */
    private function quoteValueIfNeeded(string $value): string
    {
        $needsQuotes = Str::contains($value, [' ', '#', '=', '"', "'"]) ||
                       $value === '' ||
                       in_array(strtolower($value), ['true', 'false', 'null'], true);

        return $needsQuotes ? '"'.str_replace('"', '\\"', $value).'"' : $value;
    }

    private function buildCoreVariableString(string $key, string $formattedValue, bool $isExported): string
    {
        return ($isExported ? 'export ' : '').$key.'='.$formattedValue;
    }
}
