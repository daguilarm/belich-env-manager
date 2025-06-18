<?php

declare(strict_types=1);

namespace Daguilar\EnvManager\Services;

use Daguilar\EnvManager\Services\Env\EnvParser;
use Daguilar\EnvManager\Services\Env\EnvStorage;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Collection;

/**
 * Manages environment variables as a collection.
 */
class EnvCollectionManager
{
    protected string $envPath;

    protected bool $backupsEnabled;

    protected Collection $collection;

    /**
     * Initializes environment manager.
     */
    public function __construct(
        protected readonly ConfigRepository $config,
        protected readonly BackupManager $backupManager,
        protected readonly EnvParser $parser,
        protected readonly EnvStorage $storage
    ) {
        $this->envPath = app()->environmentFilePath();
        $this->backupsEnabled = (bool) $config->get('env-manager.backup.enabled', true);
        $this->collection = new Collection;
    }

    /**
     * Loads environment file into collection.
     */
    public function load(): self
    {
        $content = $this->storage->read($this->envPath);
        $this->collection = $this->transformToCollection(
            $this->parser->parse($content)
        );

        return $this;
    }

    /**
     * Converts parsed lines to structured collection.
     */
    protected function transformToCollection(array $parsedLines): Collection
    {
        return Collection::make($parsedLines)
            ->map(fn (array $line) => match ($line['type']) {
                'empty' => $this->createEmptyItem(),
                'comment' => $this->createCommentItem($line),
                'variable' => $this->createVariableItem($line),
                default => $this->createEmptyItem()
            });
    }

    /**
     * Creates empty line representation.
     */
    private function createEmptyItem(): array
    {
        return [
            'key' => null,
            'value' => null,
            'is_comment' => false,
            'comment_above' => null,
            'comment_inline' => null,
        ];
    }

    /**
     * Creates comment line representation.
     */
    private function createCommentItem(array $line): array
    {
        return [
            'key' => null,
            'value' => null,
            'is_comment' => true,
            'comment_above' => null,
            'comment_inline' => $line['content'],
        ];
    }

    /**
     * Creates variable line representation.
     */
    private function createVariableItem(array $line): array
    {
        return [
            'key' => $line['key'],
            'value' => $line['value'],
            'is_comment' => false,
            'comment_above' => ! empty($line['comment_above'])
                ? implode("\n", $line['comment_above'])
                : null,
            'comment_inline' => $line['comment_inline'],
        ];
    }

    /**
     * Updates existing collection item.
     */
    public function updateItem(array $item): void
    {
        $index = $this->collection->search(
            fn (array $i) => $i['key'] === $item['key']
                && $i['is_comment'] === $item['is_comment']
        );

        if ($index !== false) {
            $this->collection->put($index, $item);
        }
    }

    /**
     * Returns the entire collection.
     */
    public function getEnvContent(): Collection
    {
        return $this->collection;
    }

    /**
     * Retrieves item by key.
     */
    public function get(string $key): ?array
    {
        return $this->collection->firstWhere('key', $key);
    }

    /**
     * Sets or updates environment variable.
     */
    public function set(string $key, string $value): FluentEnvItem
    {
        $item = $this->get($key);
        $newItem = $this->prepareItem($item, $key, $value);

        if ($index = $this->collection->search(fn ($i) => $i['key'] === $key)) {
            $this->collection->put($index, $newItem);
        } else {
            $this->collection->push($newItem);
        }

        return new FluentEnvItem($this, $newItem);
    }

    /**
     * Prepares item for insertion.
     */
    private function prepareItem(?array $item, string $key, string $value): array
    {
        return [
            'key' => $key,
            'value' => $value,
            'is_comment' => false,
            'comment_above' => $item['comment_above'] ?? null,
            'comment_inline' => $item['comment_inline'] ?? null,
        ];
    }

    /**
     * Removes item by key.
     */
    public function remove(string $key): self
    {
        $this->collection = $this->collection->reject(
            fn (array $item) => $item['key'] === $key
        );

        return $this;
    }

    /**
     * Updates file from external collection.
     */
    public function updateFileFromCollection(Collection $newCollection): bool
    {
        $this->collection = $newCollection;

        return $this->save();
    }

    /**
     * Saves changes to environment file.
     */
    public function save(): bool
    {
        $this->createBackupIfEnabled();

        return $this->storage->write(
            $this->envPath,
            $this->formatCollectionToEnv()
        );
    }

    /**
     * Converts collection to .env format.
     */
    protected function formatCollectionToEnv(): string
    {
        $content = $this->collection
            ->map(fn (array $item) => match (true) {
                $item['is_comment'] => $this->formatComment($item),
                $item['key'] === null && $item['value'] === null => '',
                default => $this->formatVariable($item)
            })
            ->filter()
            ->implode("\n");

        // Normalizar final del archivo: exactamente 1 salto de línea
        return rtrim($content, "\n")."\n";
    }

    /**
     * Formats comment line.
     */
    protected function formatComment(array $item): string
    {
        if (empty($item['comment_inline'])) {
            return '';
        }

        return Collection::wrap(explode("\n", $item['comment_inline']))
            ->map(fn (string $line) => "# {$line}")
            ->implode("\n");
    }

    /**
     * Formats variable line.
     */
    protected function formatVariable(array $item): string
    {
        return Collection::make([
            $this->formatAboveComment($item),
            $this->formatKeyValue($item),
            $this->formatInlineComment($item),
        ])
            ->filter()
            ->implode("\n");
    }

    /**
     * Formats comment above variable.
     */
    private function formatAboveComment(array $item): ?string
    {
        if (empty($item['comment_above'])) {
            return null;
        }

        return Collection::wrap(explode("\n", $item['comment_above']))
            ->map(fn (string $line) => "# {$line}")
            ->implode("\n");
    }

    /**
     * Formats key-value pair.
     */
    private function formatKeyValue(array $item): string
    {
        $escapedValue = $this->escapeValue($item['value'] ?? '');

        return "{$item['key']}={$escapedValue}";
    }

    /**
     * Formats inline comment.
     */
    private function formatInlineComment(array $item): ?string
    {
        return ! empty($item['comment_inline'])
            ? "# {$item['comment_inline']}"
            : null;
    }

    /**
     * Escapes special characters in values.
     */
    protected function escapeValue(string $value): string
    {
        return preg_match('/[#\s"\'\\\\]/', $value)
            ? '"'.addcslashes($value, '"\\').'"'
            : $value;
    }

    /**
     * Creates backup if enabled.
     */
    protected function createBackupIfEnabled(): void
    {
        if ($this->backupsEnabled) {
            $this->backupManager->create($this->envPath);
        }
    }
}

/**
 * Provides fluent interface for environment items.
 */
class FluentEnvItem
{
    public function __construct(
        protected EnvCollectionManager $manager,
        protected array $item
    ) {}

    /**
     * Sets comment status.
     */
    public function isCommented(bool $isComment): self
    {
        $this->item['is_comment'] = $isComment;
        $this->manager->updateItem($this->item);

        return $this;
    }

    /**
     * Sets comment above variable.
     */
    public function commentsAbove(?string $comment): self
    {
        $this->item['comment_above'] = $comment;
        $this->manager->updateItem($this->item);

        return $this;
    }

    /**
     * Sets inline comment.
     */
    public function commentLine(?string $comment): self
    {
        $this->item['comment_inline'] = $comment;
        $this->manager->updateItem($this->item);

        return $this;
    }

    /**
     * Saves changes to environment file.
     */
    public function save(): bool
    {
        return $this->manager->save();
    }
}
