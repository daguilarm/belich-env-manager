<?php

namespace Daguilar\BelichEnvManager\Services\Env;

/**
 * Manages the in-memory representation of .env file lines.
 * It allows getting, setting, and removing environment variables,
 * and handles the underlying array structure of parsed lines.
 */
class EnvEditor
{
    /**
     * @var array<int, array<string, mixed>> The lines parsed from the .env file.
     * Each line is an associative array, e.g.,
     * ['type' => 'variable', 'key' => 'APP_NAME', 'value' => 'Laravel', ...],
     * ['type' => 'comment', 'content' => '# Some comment'],
     * ['type' => 'empty']
     */
    protected array $lines = [];

    /**
     * Gets all parsed lines.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getLines(): array
    {
        return $this->lines;
    }

    /**
     * Sets all parsed lines, replacing any existing ones.
     *
     * @param array<int, array<string, mixed>> $lines
     */
    public function setLines(array $lines): void
    {
        $this->lines = $lines;
    }

    /**
     * Checks if a variable key exists.
     *
     * @param string $key
     * 
     * @return bool
     */
    public function has(string $key): bool
    {
        return collect($this->lines)
            ->contains(fn ($line) => $line['type'] === 'variable' && $line['key'] === $key);
    }

    /**
     * Gets the value of a variable key.
     *
     * @param string $key
     * @param string|null $default
     * 
     * @return string|null 
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
     *
     * @param string $key
     * @param string $value 
     * @param string|null $inlineComment
     * @param array<string>|null $commentsAbove
     */
    public function set(string $key, string $value, ?string $inlineComment = null, array $commentsAbove = null): void
    {
        $index = $this->findLineIndexByKey($key);

        if ($index !== null) {
            $this->updateLineAtIndex($index, $value, $inlineComment, $commentsAbove);
        } else {
            $this->appendNewLine($key, $value, $inlineComment, $commentsAbove);
        }
    }

    /**
     * Removes a variable key and its associated line.
     * Also cleans up any resulting consecutive empty lines.
     *
     * @param string $key
     */
    public function remove(string $key): void
    {
        $initialCount = count($this->lines);
        $this->lines = collect($this->lines)
            ->reject(fn ($line) => $line['type'] === 'variable' && $line['key'] === $key)
            ->values()
            ->all();

        if (count($this->lines) < $initialCount) {
            $this->lines = $this->cleanupEmptyLines($this->lines);
        }
    }

    /**
     * Finds the array index of a line by its variable key.
     *
     * @param string $key
     * 
     * @return int|null
     */
    private function findLineIndexByKey(string $key): ?int
    {
        $index = collect($this->lines)
            ->search(fn ($line) => $line['type'] === 'variable' && $line['key'] === $key);

        return $index === false ? null : $index;
    }

    /**
     * Updates an existing line at a specific index.
     *
     * @param int $index
     * @param string $value
     * @param string|null $inlineComment
     * @param array<string>|null $commentsAbove
     */
    private function updateLineAtIndex(int $index, string $value, ?string $inlineComment, ?array $commentsAbove): void
    {
        $this->lines[$index]['value'] = $value;

        if ($inlineComment !== null) {
            $this->lines[$index]['comment_inline'] = $inlineComment === '' ? null : $inlineComment;
        }

        if ($commentsAbove !== null) {
            $this->lines[$index]['comment_above'] = $commentsAbove;
        }
        
        // Ensure 'comment_above' key exists if it was not there and not explicitly set
        if (! array_key_exists('comment_above', $this->lines[$index])) {
            $this->lines[$index]['comment_above'] = [];
        }
    }

    /**
     * Appends a new variable line to the lines array.
     *
     * @param string $key 
     * @param string $value 
     * @param string|null $inlineComment
     * @param array<string>|null $commentsAbove
     */
    private function appendNewLine(string $key, string $value, ?string $inlineComment, ?array $commentsAbove): void
    {
        // Add an empty line before if the last line isn't empty and no comments_above are provided.
        if (! empty($this->lines) && end($this->lines)['type'] !== 'empty' && empty($commentsAbove)) {
            $this->lines[] = ['type' => 'empty'];
        }

        $this->lines[] = [
            'type' => 'variable',
            'key' => $key,
            'value' => $value,
            'comment_inline' => ($inlineComment === '') ? null : $inlineComment,
            'comment_above' => $commentsAbove ?? [],
            'export' => false, // Default: do not export new variables
        ];
    }

    /**
     * Collapses multiple consecutive empty lines into a single one.
     *
     * @param array<int, array<string, mixed>> $lines
     * 
     * @return array<int, array<string, mixed>>
     */
    private function cleanupEmptyLines(array $lines): array
    {
        if (empty($lines)) {
            return [];
        }

        return collect($lines)
            ->reduce(function ($carry, $currentItem) {
                $isCurrentEmpty = $currentItem['type'] === 'empty';
                $lastItemWasEmpty = !empty($carry) && end($carry)['type'] === 'empty';

                // Add the current item unless it's an empty line and the previous line was also empty.
                if (!($isCurrentEmpty && $lastItemWasEmpty)) {
                    $carry[] = $currentItem;
                }
                return $carry;
            }, []);
    }
}