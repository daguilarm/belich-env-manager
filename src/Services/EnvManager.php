<?php

declare(strict_types=1);

namespace Daguilar\BelichEnvManager\Services;

use Daguilar\BelichEnvManager\Services\Env\EnvEditor;
use Daguilar\BelichEnvManager\Services\Env\EnvFormatter;
use Daguilar\BelichEnvManager\Services\Env\EnvMultiSetter;
use Daguilar\BelichEnvManager\Services\Env\EnvParser;
use Daguilar\BelichEnvManager\Services\Env\EnvStorage;
use Daguilar\BelichEnvManager\Services\Env\EnvVariableSetter;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Filesystem\Filesystem;

/**
 * Manages environment variables.
 */
class EnvManager
{
    protected string $envPath;

    protected bool $backupsEnabled;

    public function __construct(
        protected readonly Filesystem $files,
        protected readonly ConfigRepository $config,
        protected readonly BackupManager $backupManager,
        protected readonly EnvParser $parser,
        protected readonly EnvFormatter $formatter,
        protected readonly EnvStorage $storage,
        protected readonly EnvEditor $editor
    ) {
        $this->envPath = app()->environmentFilePath();
        $this->backupsEnabled = $config->get('belich-env-manager.backup.enabled', true);
        $this->load();
    }

    /**
     * Get formatted .env content as string.
     */
    public function getEnvContent(): string
    {
        return $this->formatter->format($this->editor->getLines());
    }

    /**
     * Write content to .env file.
     */
    public function setEnvContent(string $content): bool
    {
        $this->createBackupIfEnabled();

        return $this->storage->write($this->envPath, $content);
    }

    /**
     * Load and parse .env file into memory.
     */
    public function load(): self
    {
        $content = $this->storage->read($this->envPath);
        $parsedLines = $this->parser->parse($content);
        $this->editor->setLines($parsedLines);

        return $this;
    }

    /**
     * Check if key exists.
     */
    public function has(string $key): bool
    {
        return $this->editor->has($key);
    }

    /**
     * Get value for given key.
     */
    public function get(string $key, mixed $default = null): ?string
    {
        return $this->editor->get($key, $default);
    }

    /**
     * Set key-value pair with fluent interface.
     */
    public function set(string $key, string $value): EnvVariableSetter
    {
        return new EnvVariableSetter($this->editor, $this, $key, $value);
    }

    /**
     * Start batch set operation.
     */
    public function multipleSet(): EnvMultiSetter
    {
        return new EnvMultiSetter($this->editor, $this);
    }

    /**
     * Save current state to .env file.
     */
    public function save(): bool
    {
        return $this->setEnvContent(
            $this->formatter->format($this->editor->getLines())
        );
    }

    /**
     * Remove key from environment.
     */
    public function remove(string $key): self
    {
        $this->editor->remove($key);

        return $this;
    }

    /**
     * Create backup if enabled.
     */
    private function createBackupIfEnabled(): void
    {
        if ($this->backupsEnabled) {
            $this->backupManager->create($this->envPath);
        }
    }
}
