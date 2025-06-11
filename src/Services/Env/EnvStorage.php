<?php

namespace Daguilar\BelichEnvManager\Services\Env;

use Illuminate\Filesystem\Filesystem;
use Exception; 

class EnvStorage
{
    public function __construct(protected readonly Filesystem $files) {}

    /**
     * Reads the content from the specified file path.
     * Returns empty string if file does not exist.
     */
    public function read(string $filePath): string
    {
        if (! $this->files->exists($filePath)) {
            return "";
        }
        return $this->files->get($filePath);
    }

    /**
     * Writes content to the specified file path.
     */
    public function write(string $filePath, string $content): bool
    {
        if ($this->files->put($filePath, $content) === false) {
            throw new Exception("Could not write to .env file: {$filePath}");
        }
        return true;
    }
}