<?php

namespace Daguilar\BelichEnvManager;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Exception;

class EnvManager
{
    protected Filesystem $files;
    protected ConfigRepository $config;
    protected string $envPath;
    protected string $backupPath;
    protected int $backupRetentionDays;
    protected bool $backupsEnabled;

    /**
     * Almacena las líneas parseadas del archivo .env.
     * Cada elemento puede ser ['type' => 'variable', 'key' => ..., 'value' => ..., 'comment' => ...]
     * o ['type' => 'comment', 'content' => ...] o ['type' => 'empty']
     */
    protected array $lines = [];
    public function __construct(Filesystem $files, ConfigRepository $config)
    {
        $this->files = $files;
        $this->config = $config;

        // Asume que el .env está en la raíz del proyecto Laravel que usa el paquete
        $this->envPath = app()->environmentFilePath(); // O base_path('.env')

        $this->backupsEnabled = $this->config->get('belich-env-manager.backup.enabled', true);
        $this->backupPath = $this->config->get('belich-env-manager.backup.path', storage_path('app/env_backups'));
        $this->backupRetentionDays = $this->config->get('belich-env-manager.backup.retention_days', 7);

        // Cargar y parsear el .env al instanciar
        $this->load();
    }

    /**
     * Lee el contenido del archivo .env.
     *
     * @return string
     * @throws Exception
     */
    public function getEnvContent(): string
    {
        return $this->buildEnvContent();
    }

    /**
     * Escribe contenido al archivo .env.
     *
     * @param string $content
     * @return bool
     */
    public function setEnvContent(string $content): bool
    {
        // Este método ahora se usará internamente por un método save() más abstracto
        if ($this->backupsEnabled) {
            $this->createBackup();
        }
        return $this->files->put($this->envPath, $content) !== false;
    }    

    /**
     * Crea una copia de seguridad del archivo .env actual.
     *
     * @return string|false La ruta al archivo de backup o false si falla.
     */
    public function createBackup(): string|false
    {
        if (!$this->files->exists($this->envPath)) {
            return false; // No hay nada que respaldar
        }

        if (!$this->files->isDirectory($this->backupPath)) {
            $this->files->makeDirectory($this->backupPath, 0755, true, true);
        }

        $backupName = '.env.backup_' . date('Ymd_His') . '_' . uniqid();
        $backupFilePath = $this->backupPath . DIRECTORY_SEPARATOR . $backupName;

        if ($this->files->copy($this->envPath, $backupFilePath)) {
            $this->pruneOldBackups();
            return $backupFilePath;
        }

        return false;
    }

    /**
     * Elimina copias de seguridad antiguas según la política de retención.
     */
    public function pruneOldBackups(): void
    {
        if (!$this->files->isDirectory($this->backupPath) || $this->backupRetentionDays <= 0) {
            return;
        }

        $files = $this->files->files($this->backupPath);
        $cutoffTime = time() - ($this->backupRetentionDays * 24 * 60 * 60);

        foreach ($files as $file) {
            if (str_starts_with($file->getFilename(), '.env.backup_') && $file->getMTime() < $cutoffTime) {
                $this->files->delete($file->getPathname());
            }
        }
    }

    /**
     * Carga y parsea el archivo .env.
     *
     * @return $this
     * @throws Exception
     */
    public function load(): self
    {
        if (!$this->files->exists($this->envPath)) {
            // Si el archivo no existe, podemos tratarlo como vacío o lanzar error.
            // Por ahora, lo trataremos como vacío.
            $this->lines = [];
            return $this;
            // throw new Exception("El archivo .env no existe en: {$this->envPath}");
        }

        $content = $this->files->get($this->envPath);
        $this->lines = $this->parseEnvContent($content);
        return $this;
    }

    /**
     * Parsea el contenido de un string .env a una estructura de array.
     *
     * @param string $content
     * @return array
     */
    protected function parseEnvContent(string $content): array
    {
        $lines = [];
        $rawLines = preg_split("/(\r\n|\n|\r)/", $content);

        foreach ($rawLines as $rawLine) {
            $trimmedLine = trim($rawLine);

            if (empty($trimmedLine)) {
                $lines[] = ['type' => 'empty'];
                continue;
            }

            if (str_starts_with($trimmedLine, '#')) {
                $lines[] = ['type' => 'comment', 'content' => $rawLine]; // Guardar línea original con su indentación
                continue;
            }

            if (preg_match('/^(export\s+)?(?<key>[A-Za-z_0-9]+)\s*=\s*(?<value>.*)?$/', $trimmedLine, $matches)) {
                $key = $matches['key'];
                $value = $matches['value'] ?? '';

                // Eliminar comentarios al final de la línea de la variable
                $comment = null;
                if (str_contains($value, '#')) {
                    $parts = explode('#', $value, 2);
                    $value = trim($parts[0]);
                    $comment = trim($parts[1]);
                }

                // Quitar comillas si existen (manejo básico)
                if (preg_match('/^"(.*)"$/s', $value, $q_matches) || preg_match("/^'(.*)'$/s", $value, $q_matches)) {
                    $value = $q_matches[1];
                } elseif ($value === 'null' || $value === 'true' || $value === 'false' || $value === '') {
                    // Mantener estos valores como están, no son strings entrecomillados per se
                }

                $lines[] = [
                    'type' => 'variable',
                    'key' => $key,
                    'value' => $value,
                    'comment_inline' => $comment, // Comentario en la misma línea
                    'export' => str_starts_with($trimmedLine, 'export')
                ];
            } else {
                // Líneas que no coinciden con los patrones anteriores (podrían ser inválidas o comentarios mal formateados)
                // Por ahora, las tratamos como comentarios para preservarlas.
                $lines[] = ['type' => 'comment', 'content' => $rawLine];
            }
        }
        return $lines;
    }

    /**
     * Reconstruye el contenido del archivo .env desde el array $this->lines.
     *
     * @return string
     */
    protected function buildEnvContent(): string
    {
        $content = "";
        foreach ($this->lines as $line) {
            if ($line['type'] === 'empty') {
                $content .= PHP_EOL;
            } elseif ($line['type'] === 'comment') {
                $content .= $line['content'] . PHP_EOL;
            } elseif ($line['type'] === 'variable') {
                $value = $line['value'];
                // Aquí necesitarás una lógica más robusta para entrecomillar valores si es necesario
                if (str_contains($value, ' ') || str_contains($value, '#') || str_contains($value, '=') || $value === '' || in_array(strtolower($value), ['true', 'false', 'null'])) {
                    $value = '"' . str_replace('"', '\\"', $value) . '"';
                }
                $lineStr = ($line['export'] ? 'export ' : '') . $line['key'] . '=' . $value;
                if (!empty($line['comment_inline'])) {
                    $lineStr .= ' # ' . $line['comment_inline'];
                }
                $content .= $lineStr . PHP_EOL;
            }
        }
        return rtrim($content, PHP_EOL) . PHP_EOL; // Asegurar una nueva línea al final si hay contenido
    }

    /**
     * Comprueba si una clave existe en el archivo .env.
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        foreach ($this->lines as $line) {
            if ($line['type'] === 'variable' && $line['key'] === $key) {
                return true;
            }
        }
        return false;
    }

    /**
     * Obtiene el valor de una clave del archivo .env.
     *
     * @param string $key
     * @param mixed $default
     * @return string|null
     */
    public function get(string $key, $default = null): ?string
    {
        foreach ($this->lines as $line) {
            if ($line['type'] === 'variable' && $line['key'] === $key) {
                return $line['value'];
            }
        }
        return $default;
    }

    /**
     * Establece o actualiza el valor de una clave en el archivo .env.
     * Si la clave no existe, se añade al final.
     *
     * @param string $key
     * @param string $value
     * @param string|null $inlineComment Comentario para la misma línea de la variable.
     * @return $this
     */
    public function set(string $key, string $value, ?string $inlineComment = null): self
    {
        $keyFound = false;
        foreach ($this->lines as &$lineRef) { // Usar referencia para modificar en el lugar
            if ($lineRef['type'] === 'variable' && $lineRef['key'] === $key) {
                $lineRef['value'] = $value;
                if ($inlineComment !== null) {
                    $lineRef['comment_inline'] = $inlineComment;
                } elseif (array_key_exists('comment_inline', $lineRef) && $inlineComment === null) {
                    // Si se pasa null explícitamente y existía un comentario, se podría eliminar
                    // o mantenerlo. Por ahora, lo mantenemos si no se especifica uno nuevo.
                    // Para eliminarlo, se pasaría un string vacío.
                }
                $keyFound = true;
                break;
            }
        }
        unset($lineRef); // Romper la referencia

        if (!$keyFound) {
            // Si la clave no se encontró, la añadimos al final.
            // Podríamos añadir una línea vacía antes si la última no lo es.
            if (!empty($this->lines) && end($this->lines)['type'] !== 'empty') {
                $this->lines[] = ['type' => 'empty'];
            }
            $this->lines[] = [
                'type' => 'variable',
                'key' => $key,
                'value' => $value,
                'comment_inline' => $inlineComment,
                'export' => false // Por defecto, no exportar nuevas variables
            ];
        }

        return $this;
    }

    /**
     * Guarda los cambios actuales en el archivo .env.
     *
     * @return bool
     */
    public function save(): bool
    {
        $newContent = $this->buildEnvContent();
        // setEnvContent ya maneja el backup
        return $this->setEnvContent($newContent);
    }

    // TODO: Implementar remove(string $key): self
    // TODO: Mejorar el manejo de comentarios (comentarios de bloque encima de la variable)
    //       Esto requeriría que parseEnvContent intente asociar comentarios de línea completa
    //       con la variable que les sigue, y que `set` pueda manejar/actualizar esos comentarios.
    //       Por ejemplo, un nuevo campo 'comment_above' en la estructura de la línea.
    //
    // Ejemplo de cómo se podría usar:
    // $envManager = app(EnvManager::class);
    // $envManager->set('APP_NAME', 'Mi Nueva App Belich', 'Este es el nombre de la app');
    // $envManager->set('NEW_VAR', 'new_value');
    // $envManager->save();
    // echo $envManager->get('APP_NAME');
}