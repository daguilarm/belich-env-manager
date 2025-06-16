<?php

namespace Daguilar\BelichEnvManager\Services;

use Daguilar\BelichEnvManager\Services\Env\EnvEditor;
use Daguilar\BelichEnvManager\Services\Env\EnvFormatter;
use Daguilar\BelichEnvManager\Services\Env\EnvMultiSetter;
use Daguilar\BelichEnvManager\Services\Env\EnvParser;
use Daguilar\BelichEnvManager\Services\Env\EnvStorage;
use Daguilar\BelichEnvManager\Services\Env\EnvVariableSetter;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Filesystem\Filesystem;

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
     * Returns the current .env content as an associative array.
     * Ignores empty lines and comments.
     */
    public function getEnvContentAsArray(): array
    {
        $array = [];
        foreach ($this->editor->getLines() as $line) {
            if ($line->isVariable()) {
                // Assuming getVariable() returns an object or array with 'key' and 'value'
                $variable = $line->getVariable();
                $array[$variable['key']] = $variable['value'];
            }
        }
        return $array;
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
     */
    public function has(string $key): bool
    {
        return $this->editor->has($key);
    }

    /**
     * Gets the value of a key.
     *
     * @param  mixed  $default
     */
    public function get(string $key, $default = null): ?string
    {
        return $this->editor->get($key, $default);
    }

    /**
     * Begins the process of setting or updating a key's value.
     * Returns a fluent setter object to optionally add comments.
     */
    public function set(string $key, string $value): EnvVariableSetter
    {
        return new EnvVariableSetter($this->editor, $this, $key, $value);
    }

    /**
     * Begins a batch operation for setting multiple environment variables.
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
