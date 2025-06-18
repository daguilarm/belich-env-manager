<?php

declare(strict_types=1);

namespace Daguilar\EnvManager\Services;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Manages environment file backups with retention policy.
 */
class BackupManager
{
    /**
     * Creates a new BackupManager instance.
     */
    public function __construct(
        protected readonly Filesystem $files,
        protected readonly ConfigRepository $config,
        protected readonly string $backupPath,
        protected readonly ?int $backupRetentionDays
    ) {}

    /**
     * Creates instance from application configuration.
     */
    public static function fromConfig(Filesystem $files, ConfigRepository $config): self
    {
        $backupPath = $config->get('env-manager.backup.path', storage_path('app/env_backups'));
        $backupRetentionDays = $config->get('env-manager.backup.retention_days', 7);

        return new self($files, $config, $backupPath, $backupRetentionDays);
    }

    /**
     * Creates backup of specified file.
     */
    public function create(string $filePathToBackup): ?string
    {
        if (! $this->files->exists($filePathToBackup)) {
            return null;
        }

        $this->ensureBackupDirectoryExists();

        $backupName = $this->generateBackupName($filePathToBackup);
        $backupFilePath = $this->backupPath.DIRECTORY_SEPARATOR.$backupName;

        if ($this->files->copy($filePathToBackup, $backupFilePath)) {
            $this->prune(basename($filePathToBackup));

            return $backupFilePath;
        }

        return null;
    }

    /**
     * Deletes backups older than retention period.
     */
    public function prune(string $originalFileName): void
    {
        if ($this->shouldSkipPruning()) {
            return;
        }

        $cutoffTime = Carbon::now()->subDays($this->backupRetentionDays)->getTimestamp();
        $prefix = $originalFileName.'.backup_';

        Collection::make($this->files->files($this->backupPath))
            ->filter(fn (SplFileInfo $file) => $this->isEligibleForPruning($file, $prefix, $cutoffTime))
            ->each(fn (SplFileInfo $file) => $this->files->delete($file->getPathname()));
    }

    /**
     * Ensures backup directory exists.
     */
    private function ensureBackupDirectoryExists(): void
    {
        if (! $this->files->isDirectory($this->backupPath)) {
            $this->files->makeDirectory($this->backupPath, 0755, true);
        }
    }

    /**
     * Determines if pruning should be skipped.
     */
    private function shouldSkipPruning(): bool
    {
        return ! $this->files->isDirectory($this->backupPath)
            || ! $this->backupRetentionDays
            || $this->backupRetentionDays <= 0;
    }

    /**
     * Checks if file meets pruning criteria.
     */
    private function isEligibleForPruning(SplFileInfo $file, string $prefix, int $cutoffTime): bool
    {
        return str_starts_with($file->getFilename(), $prefix)
            && $file->getMTime() < $cutoffTime;
    }

    /**
     * Generates unique backup filename.
     */
    private function generateBackupName(string $filePath): string
    {
        $baseName = basename($filePath);
        $timestamp = Carbon::now()->format('Ymd_His');
        $random = Str::random(8);

        return "{$baseName}.backup_{$timestamp}_{$random}";
    }
}
