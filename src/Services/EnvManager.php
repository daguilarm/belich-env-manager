<?php

namespace Daguilar\BelichEnvManager\Services;

use Daguilar\BelichEnvManager\Services\Env\EnvEditor;
use Daguilar\BelichEnvManager\Services\Env\EnvFormatter;
use Daguilar\BelichEnvManager\Services\Env\EnvParser;
use Daguilar\BelichEnvManager\Services\Env\EnvStorage;
use Daguilar\BelichEnvManager\Services\Env\EnvMultiSetter;
use Daguilar\BelichEnvManager\Services\Env\EnvVariableSetter;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Filesystem\Filesystem;
use Exception;

class EnvManager
{
    protected string $envPath; 
    protected bool $backupsEnabled; // Backup creation can be disabled

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
        $this->backupsEnabled = $this->config->get('belich-env-manager.backup.enabled', true);
        $this->load();
    }

    /**
     * Returns the current .env content as a string.
     */
    public function getEnvContent(): string
    {
        return $this->formatter->format($this->editor->getLines());
    }

    /**
     * Writes content to the .env file.
     */
    public function setEnvContent(string $content): bool
    {
        if ($this->backupsEnabled) {
            $this->backupManager->create($this->envPath);
        }
        $result = $this->storage->write($this->envPath, $content);

        return $result;
    }

    /**
     * Loads and parses the .env file into memory.
     */
    public function load(): self
    {
        $content = $this->storage->read($this->envPath);
        $parsedLines = $this->parser->parse($content);
        $this->editor->setLines($parsedLines);

        return $this;
    }

    /**
     * Checks if a key exists.
     *
     * @param string $key
     * 
     * @return bool
     */
    public function has(string $key): bool
    {
        return $this->editor->has($key);
    }

    /**
     * Gets the value of a key.
     *
     * @param string $key
     * @param mixed $default
     * 
     * @return string|null
     */
    public function get(string $key, $default = null): ?string
    {
        return $this->editor->get($key, $default);
    }

    /**
     * Begins the process of setting or updating a key's value.
     * Returns a fluent setter object to optionally add comments.
     *
     * @param string $key
     * @param string $value
     * @return EnvVariableSetter
     */
    public function set(string $key, string $value): EnvVariableSetter
    {
        return new EnvVariableSetter($this->editor, $this, $key, $value);
    }

    /**
     * Begins a batch operation for setting multiple environment variables.
     *
     * @return EnvMultiSetter
     */
    public function multipleSet(): EnvMultiSetter
    {
        return new EnvMultiSetter($this->editor, $this);
    }

    /**
     * Saves the current in-memory state to the .env file.
     */
    public function save(): bool
    {
        $newContent = $this->formatter->format($this->editor->getLines());

        return $this->setEnvContent($newContent);
    }

    /**
     * Removes a key from memory.
     */
    public function remove(string $key): self
    {
        $this->editor->remove($key);

        return $this;
    }
}