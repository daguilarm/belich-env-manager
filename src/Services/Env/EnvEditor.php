<?php

namespace Daguilarm\EnvManager\Services\Env;

use Illuminate\Support\Collection;

/**
 * Manages the in-memory representation of .env file lines.
 * It allows getting, setting, and removing environment variables,
 * and handles the underlying array structure of parsed lines.
 */
class EnvEditor
{
    /**
     * @var array<int, array<string, mixed>> The lines parsed from the .env file.
     *                                       Each line is an associative array, e.g.,
     *                                       ['type' => 'variable', 'key' => 'APP_NAME', 'value' => 'Laravel', ...],
     *                                       ['type' => 'comment', 'content' => '# Some comment'],
     *                                       ['type' => 'empty']
     */
    protected array $lines = [];

    /**
     * Gets all parsed lines.
     */
    public function getLines(): array
    {
        return $this->lines;
    }

    /**
     * Sets all parsed lines, replacing any existing ones.
     */
    public function setLines(array $lines): void
    {
        $this->lines = $lines;
    }

    /**
     * Checks if a variable key exists.
     */
    public function has(string $key): bool
    {
        return collect($this->lines)
            ->contains(fn ($line) => $line['type'] === 'variable' && $line['key'] === $key);
    }

    /**
     * Gets the value of a variable key.
     */
    public function get(string $key, $default = null): ?string
    {
        $line = collect($this->lines)
            ->firstWhere(fn ($item) => $item['type'] === 'variable' && $item['key'] === $key);

        return $line['value'] ?? $default;
    }

    /**
     * Sets or updates a variable key with a new value and optional comments.
     * If the key exists, it's updated. Otherwise, a new line is appended.
     */
    public function set(string $key, string $value, ?string $inlineComment = null, ?array $commentsAbove = null, bool $export = false): void
    {
        $index = $this->findLineIndexByKey($key);

        $index !== null
            ? $this->updateExistingLine($index, $value, $inlineComment, $commentsAbove, $export)
            : $this->appendNewLine($key, $value, $inlineComment, $commentsAbove, $export);
    }

    /**
     * Removes a variable key and its associated line.
     * Also cleans up any resulting consecutive empty lines.
     */
    public function remove(string $key): void
    {
        $initialCount = count($this->lines);

        $this->lines = Collection::make($this->lines)
            ->reject(fn ($line) => $line['type'] === 'variable' && $line['key'] === $key)
            ->pipe(fn ($coll) => $this->cleanupEmptyLines($coll->all()));
    }

    /**
     * Finds the array index of a line by its variable key.
     */
    private function findLineIndexByKey(string $key): ?int
    {
        $index = collect($this->lines)
            ->search(fn ($line) => $line['type'] === 'variable' && $line['key'] === $key);

        return $index === false ? null : $index;
    }

    /**
     * Updates an existing line at a specific index.
     */
    private function updateExistingLine(int $index, string $value, ?string $inlineComment, ?array $commentsAbove, bool $export): void
    {
        $this->lines[$index]['value'] = $value;
        $this->lines[$index]['export'] = $export;

        if ($inlineComment !== null) {
            $this->lines[$index]['comment_inline'] = $inlineComment ?: null;
        }

        if ($commentsAbove !== null) {
            $this->lines[$index]['comment_above'] = $commentsAbove;
        }

        if (! isset($this->lines[$index]['comment_above'])) {
            $this->lines[$index]['comment_above'] = [];
        }
    }

    /**
     * Appends a new variable line to the lines array.
     */
    private function appendNewLine(string $key, string $value, ?string $inlineComment, ?array $commentsAbove, bool $export): void
    {
        if (! empty($this->lines)) {
            $lastLine = end($this->lines);
            if ($lastLine['type'] !== 'empty' && empty($commentsAbove)) {
                $this->lines[] = ['type' => 'empty'];
            }
        }

        $this->lines[] = array_filter([
            'type' => 'variable',
            'key' => $key,
            'value' => $value,
            'comment_inline' => $inlineComment,
            'comment_above' => $commentsAbove ?? [],
            'export' => $export,
        ], fn ($v) => $v !== null);
    }

    /**
     * Collapses multiple consecutive empty lines into a single one.
     */
    private function cleanupEmptyLines(array $lines): array
    {
        return Collection::make($lines)
            ->reduce(function (array $result, array $line) {
                $last = end($result);
                $isDuplicateEmpty = $line['type'] === 'empty' && $last && $last['type'] === 'empty';

                if (! $isDuplicateEmpty) {
                    $result[] = $line;
                }

                return $result;
            }, []);
    }
}
