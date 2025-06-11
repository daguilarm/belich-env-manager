<?php

namespace Daguilar\BelichEnvManager\Services;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Symfony\Component\Finder\SplFileInfo;

class BackupManager
{
    /**
     * BackupManager constructor.
     */
    public function __construct(
        protected readonly Filesystem $files,
        protected readonly ConfigRepository $config,
        protected readonly string $backupPath,
        protected readonly ?int $backupRetentionDays
    ) {}

    /**
     * Create a new BackupManager instance from configuration.
     *
     * @param  Filesystem  $files  The filesystem instance.
     * @param  ConfigRepository  $config  The configuration repository instance.
     */
    public static function fromConfig(Filesystem $files, ConfigRepository $config): self
    {
        $backupPath = $config->get('belich-env-manager.backup.path', storage_path('app/env_backups'));
        $backupRetentionDays = $config->get('belich-env-manager.backup.retention_days', 7);

        return new self($files, $config, $backupPath, $backupRetentionDays);
    }

    /**
     * Creates a backup of the specified file.
     */
    public function create(string $filePathToBackup): string|false
    {
        if (! $this->files->exists($filePathToBackup)) {
            return false; // Nothing to back up
        }

        $this->ensureBackupDirectoryExists();

        $originalFileName = basename($filePathToBackup);
        $backupName = sprintf(
            '%s.backup_%s_%s',
            $originalFileName,
            Carbon::now()->format('Ymd_His'),
            Str::random(8)
        );
        $backupFilePath = $this->backupPath.DIRECTORY_SEPARATOR.$backupName;

        if ($this->files->copy($filePathToBackup, $backupFilePath)) {
            $this->prune($originalFileName);

            return $backupFilePath;
        }

        return false;
    }

    /**
     * Prunes old backups according to the retention policy.
     */
    public function prune(string $originalFileName): void
    {
        if ($this->shouldSkipPruning()) {
            return;
        }

        $cutoffTime = Carbon::now()->subDays($this->backupRetentionDays)->getTimestamp();
        $originalFileNamePrefix = $originalFileName.'.backup_';

        collect($this->files->files($this->backupPath))
            ->filter(fn (SplFileInfo $file) => $this->isEligibleForPruning($file, $originalFileNamePrefix, $cutoffTime))
            ->each(fn (SplFileInfo $file) => $this->files->delete($file->getPathname()));
    }

    /**
     * Ensures the backup directory exists.
     */
    private function ensureBackupDirectoryExists(): void
    {
        if (! $this->files->isDirectory($this->backupPath)) {
            $this->files->makeDirectory($this->backupPath, 0755, true, true);
        }
    }

    /**
     * Determines if the pruning process should be skipped.
     */
    private function shouldSkipPruning(): bool
    {
        return ! $this->files->isDirectory($this->backupPath) ||
               empty($this->backupRetentionDays) || // Covers null, 0, empty string
               $this->backupRetentionDays <= 0;
    }

    /**
     * Checks if a file is eligible for pruning based on its name and modification time.
     */
    private function isEligibleForPruning(SplFileInfo $file, string $originalFileNamePrefix, int $cutoffTime): bool
    {
        return Str::startsWith($file->getFilename(), $originalFileNamePrefix) &&
               $file->getMTime() < $cutoffTime;
    }
}
