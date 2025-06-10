<?php

namespace Daguilar\BelichEnvManager\Env;

use Daguilar\BelichEnvManager\Backup\BackupManager;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Exception;

class EnvManager
{
    // Properties promoted in constructor, no need to redeclare here
    // protected readonly Filesystem $files;
    // protected readonly ConfigRepository $config;
    // protected readonly BackupManager $backupManager;
    protected string $envPath; // This one is not promoted, so it stays
    protected bool $backupsEnabled; // Backup creation can be disabled
    /**
     * Almacena las líneas parseadas del archivo .env.
     * Cada elemento puede ser ['type' => 'variable', 'key' => ..., 'value' => ..., 'comment' => ...]
     * o ['type' => 'comment', 'content' => ...] o ['type' => 'empty']
     */
    protected array $lines = [];

    public function __construct(
        protected readonly Filesystem $files,
        protected readonly ConfigRepository $config,
        protected readonly BackupManager $backupManager
    ) {
        $this->envPath = app()->environmentFilePath();
        $this->backupsEnabled = $this->config->get('belich-env-manager.backup.enabled', true);

        // Cargar y parsear el .env al instanciar
        $this->load();
    }

    /**
     * Lee el contenido del archivo .env.
     *
     * @throws Exception
     */
    public function getEnvContent(): string
    {
        return $this->buildEnvContent();
    }

    /**
     * Escribe contenido al archivo .env.
     *
     */
    public function setEnvContent(string $content): bool
    {
        if ($this->backupsEnabled) {
            $this->backupManager->create($this->envPath);
        }

        if ($this->files->put($this->envPath, $content) === false) {
            throw new Exception("No se pudo escribir en el archivo .env: {$this->envPath}");
        }
        return true;
    }

    /**
     * Carga y parsea el archivo .env.
     *
     * @throws Exception
     */
    public function load(): self
    {
        if (! $this->files->exists($this->envPath)) {
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
     */
    protected function parseEnvContent(string $content): array
    {
        $lines = [];
        $pendingCommentsAbove = [];

        $rawLinesArray = preg_split("/(\r\n|\n|\r)/", $content);

        foreach ($rawLinesArray as $rawLine) {
            $trimmedLine = trim($rawLine);

            if ($this->isEmptyLine($trimmedLine, $rawLine, $lines, $pendingCommentsAbove)) {
                continue;
            }

            if ($this->isCommentLine($trimmedLine, $rawLine, $pendingCommentsAbove)) {
                continue;
            }

            if ($this->isVariableLine($trimmedLine, $rawLine, $lines, $pendingCommentsAbove)) {
                continue;
            }

            $this->handleFallbackLine($rawLine, $lines, $pendingCommentsAbove);
        }

        // Add any trailing comments
        foreach ($pendingCommentsAbove as $pc) {
            $lines[] = ['type' => 'comment', 'content' => $pc];
        }

        return $lines;
    }

    private function isEmptyLine(string $trimmedLine, string $rawLine, array &$lines, array &$pendingCommentsAbove): bool
    {
        if (!empty($trimmedLine)) {
            return false;
        }

        if (!empty($pendingCommentsAbove)) {
            foreach ($pendingCommentsAbove as $pc) {
                $lines[] = ['type' => 'comment', 'content' => $pc];
            }
        }
        $lines[] = ['type' => 'empty'];
        $pendingCommentsAbove = []; // Reset after handling the empty line and its preceding comments
        return true;
    }

    private function isCommentLine(string $trimmedLine, string $rawLine, array &$pendingCommentsAbove): bool
    {
        if (!Str::startsWith($trimmedLine, '#')) {
            return false;
        }

        $pendingCommentsAbove[] = $rawLine; // Use rawLine to preserve original comment formatting
        return true;
    }

    private function isVariableLine(string $trimmedLine, string $rawLine, array &$lines, array &$pendingCommentsAbove): bool
    {
        if (!preg_match('/^(export\s+)?(?<key>[A-Za-z_0-9]+)\s*=\s*(?<value>.*)?$/', $trimmedLine, $matches)) {
            return false;
        }

        $key = $matches['key'];
        $value = $matches['value'] ?? '';

        $comment = null;
        if (Str::contains($value, '#')) {
            $parts = Str::of($value)->explode('#', 2);
            $value = trim($parts[0]);
            $comment = trim($parts[1]);
        }

        // Unquote value if quoted
        if (preg_match('/^"(.*)"$/s', $value, $q_matches) || preg_match("/^'(.*)'$/s", $value, $q_matches)) {
            $value = $q_matches[1];
        }

        $lines[] = [
            'type' => 'variable',
            'key' => $key,
            'value' => $value,
            'comment_inline' => $comment,
            'comment_above' => $pendingCommentsAbove,
            'export' => Str::startsWith($trimmedLine, 'export'),
        ];
        $pendingCommentsAbove = []; // Reset after associating with a variable
        return true;
    }

    private function handleFallbackLine(string $rawLine, array &$lines, array &$pendingCommentsAbove): void
    {
        // Fallback for lines that are not empty, not comments, and not valid variables
        // Any pending comments are considered to be above this "unknown" line.
        if (!empty($pendingCommentsAbove)) {
            foreach ($pendingCommentsAbove as $pc) {
                $lines[] = ['type' => 'comment', 'content' => $pc];
            }
        }
        // Treat the current rawLine as a comment to preserve it.
        $lines[] = ['type' => 'comment', 'content' => $rawLine];
        $pendingCommentsAbove = []; // Reset after handling the fallback line and its preceding comments
    }

    /**
     * Reconstruye el contenido del archivo .env desde el array $this->lines.
     *
     * @return string
     */
    protected function buildEnvContent(): string
    {
        $content = "";
        collect($this->lines)->each(function ($line) use (&$content) {
            match ($line['type']) {
                'empty' => $content .= PHP_EOL,
                'comment' => $content .= $line['content'].PHP_EOL,
                'variable' => (function () use ($line, &$content) {
                    if (! empty($line['comment_above'])) {
                        foreach ($line['comment_above'] as $commentAbove) {
                            $content .= $commentAbove.PHP_EOL;
                        }
                    }

                    $value = $line['value'];
                    // Quote value if it contains spaces, #, =, quotes, or is one of (true, false, null, empty string)
                    // to ensure consistent string representation.
                    if (Str::contains($value, [' ', '#', '=', '"', "'"]) || $value === '' ||
                        in_array(strtolower((string) $value), ['true', 'false', 'null'], true)
                    ) {
                        $value = '"'.str_replace('"', '\\"', $value).'"';
                    }

                    $lineStr = ($line['export'] ? 'export ' : '').$line['key'].'='.$value;
                    if (! empty($line['comment_inline'])) {
                        $lineStr .= ' # '.$line['comment_inline'];
                    }
                    $content .= $lineStr.PHP_EOL;
                })(),
                default => null, // Or throw an exception for unknown type
            };
        });

        return $content ? rtrim($content, PHP_EOL).PHP_EOL : ""; // Ensure a single newline at the end if content exists
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
     * @param string|null $inlineComment Comentario para la misma línea. '' para eliminar, null para no cambiar.
     * @param array $commentsAbove Array de strings para comentarios de bloque encima. [] para no cambiar.
     */
    public function set(string $key, string $value, ?string $inlineComment = null, array $commentsAbove = null): self
    {
        $keyFound = false;
        foreach ($this->lines as &$lineRef) { // Usar referencia para modificar en el lugar
            if ($lineRef['type'] === 'variable' && $lineRef['key'] === $key) {
                $lineRef['value'] = $value;

                if ($inlineComment !== null) {
                    $lineRef['comment_inline'] = $inlineComment === '' ? null : $inlineComment;
                }

                if ($commentsAbove !== null) {
                    $lineRef['comment_above'] = $commentsAbove; // $commentsAbove can be an empty array to clear
                }
                // Ensure 'comment_above' key exists if it was not there and not explicitly set
                if (! array_key_exists('comment_above', $lineRef)) {
                    $lineRef['comment_above'] = [];
                }

                $keyFound = true;
                break;
            }
        }
        unset($lineRef); // Romper la referencia

        if (!$keyFound) {
            // Add new variable. Add an empty line before if the last line isn't empty and no comments_above are provided.
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

        return $this;
    }

    /**
     * Guarda los cambios actuales en el archivo .env.
     *
     */
    public function save(): bool
    {
        $newContent = $this->buildEnvContent();

        return $this->setEnvContent($newContent);
    }

    /**
     * Elimina una clave del archivo .env y sus comentarios asociados.
     *
     */
    public function remove(string $key): self
    {
        $initialCount = count($this->lines);
        $this->lines = array_filter($this->lines, function ($line) use ($key) {
            return !($line['type'] === 'variable' && $line['key'] === $key);
        });

        $this->lines = array_values($this->lines); // Re-index

        // If a variable was removed, clean up potential excessive empty lines
        if (count($this->lines) < $initialCount) {
            $this->lines = $this->cleanupEmptyLines($this->lines);
        }

        return $this;
    }

    /**
     * Helper to collapse multiple consecutive empty lines into a single one.
     */
    protected function cleanupEmptyLines(array $lines): array
    {
        if (empty($lines)) {
            return [];
        }
        $cleanedLines = [];
        $lastLineWasEmpty = false;
        foreach ($lines as $line) {
            if ($line['type'] === 'empty') {
                if (! $lastLineWasEmpty) {
                    $cleanedLines[] = $line;
                }
                $lastLineWasEmpty = true;
            } else {
                $cleanedLines[] = $line;
                $lastLineWasEmpty = false;
            }
        }

        return $cleanedLines;
    }
}