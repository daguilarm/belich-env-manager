<?php

namespace Daguilar\BelichEnvManager\Backup;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class BackupManager
{
    public function __construct(
        protected readonly Filesystem $files,
        protected readonly ConfigRepository $config,
        protected readonly string $backupPath,
        protected readonly ?int $backupRetentionDays
    ) {
    }

    public static function fromConfig(Filesystem $files, ConfigRepository $config): self
    {
        $backupPath = $config->get('belich-env-manager.backup.path', storage_path('app/env_backups'));
        $backupRetentionDays = $config->get('belich-env-manager.backup.retention_days', 7);

        return new self($files, $config, $backupPath, $backupRetentionDays);
    }

    /**
     * Crea una copia de seguridad del archivo especificado.
     *
     * @param  string  $filePathToBackup  La ruta al archivo que se va a respaldar.
     * @return string|false La ruta al archivo de backup o false si falla.
     */
    public function create(string $filePathToBackup): string|false
    {
        if (! $this->files->exists($filePathToBackup)) {
            return false; // No hay nada que respaldar
        }

        if (! $this->files->isDirectory($this->backupPath)) {
            $this->files->makeDirectory($this->backupPath, 0755, true, true);
        }

        // Usar el nombre base del archivo original para el backup
        $originalFileName = basename($filePathToBackup);
        $backupName = $originalFileName.'.backup_'.date('Ymd_His').'_'.uniqid();
        $backupFilePath = $this->backupPath.DIRECTORY_SEPARATOR.$backupName;

        if ($this->files->copy($filePathToBackup, $backupFilePath)) {
            $this->prune($originalFileName); // Pasar el nombre base para filtrar backups relevantes

            return $backupFilePath;
        }

        return false;
    }

    /**
     * Elimina copias de seguridad antiguas según la política de retención.
     *
     * @param  string  $originalFileName  El nombre base del archivo original (ej: '.env')
     *                                    para asegurar que solo se podan backups de ese archivo.
     */
    public function prune(string $originalFileName): void
    {
        if (! $this->files->isDirectory($this->backupPath) || empty($this->backupRetentionDays) || $this->backupRetentionDays <= 0) {
            return;
        }

        $allFiles = $this->files->files($this->backupPath);
        $cutoffTime = Carbon::now()->subDays($this->backupRetentionDays)->getTimestamp();

        foreach ($allFiles as $file) {
            // Asegurarse de que el archivo es un backup del archivo original especificado
            if (Str::startsWith($file->getFilename(), $originalFileName.'.backup_') && $file->getMTime() < $cutoffTime) {
                $this->files->delete($file->getPathname());
            }
        }
    }
}
