<?php

namespace App\Services\Obsidian;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/** A vault folder on this server or computer (for local work, or a vault synced to the host). */
class LocalVault implements Vault
{
    private string $root;

    /**
     * @param  string|null  $allowedRoot  when given, the vault folder must be this folder or inside it
     *                                    (the panel's folder setting is limited to VAULT_LOCAL_ROOT)
     */
    public function __construct(string $root, ?string $allowedRoot = null)
    {
        $real = realpath($root);
        if (! $real || ! is_dir($real)) {
            throw new RuntimeException("Vault folder not found: {$root}");
        }
        if ($allowedRoot !== null) {
            $base = realpath($allowedRoot);
            if (! $base || ! is_dir($base)) {
                throw new RuntimeException("The allowed vault root (VAULT_LOCAL_ROOT) does not exist: {$allowedRoot}");
            }
            if ($real !== $base && ! str_starts_with($real, rtrim($base, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
                throw new RuntimeException("The vault folder must be inside {$base} (VAULT_LOCAL_ROOT in .env); got {$root}");
            }
        }
        $this->root = $real;
    }

    public function files(): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($this->root, RecursiveDirectoryIterator::SKIP_DOTS),
                fn ($file) => ! str_starts_with($file->getFilename(), '.'),
            ),
        );
        foreach ($iterator as $file) {
            $rel = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($this->root) + 1));
            $files[$rel] = $file->getMTime().':'.$file->getSize();
        }
        ksort($files);

        return $files;
    }

    public function read(string $path): string
    {
        return file_get_contents($this->localCopy($path));
    }

    public function localCopy(string $path): string
    {
        $full = realpath($this->root.'/'.$path);
        if (! $full || ! str_starts_with($full, $this->root.DIRECTORY_SEPARATOR)) {
            throw new RuntimeException("Not inside the vault: {$path}");
        }

        return $full;
    }

    /**
     * The git commit of the folder, or null when it is not a git checkout or has uncommitted changes
     * (then the files may differ from the commit, so the folder is always imported).
     */
    public function revision(): ?string
    {
        if (! function_exists('shell_exec')) {
            return null;
        }
        $git = 'git -C '.escapeshellarg($this->root);
        $head = @shell_exec($git.' rev-parse --short=12 HEAD 2>/dev/null');
        if (! is_string($head) || ! preg_match('/^[0-9a-f]{7,40}$/', trim($head))) {
            return null;
        }
        $changes = @shell_exec($git.' status --porcelain --untracked-files=normal -- . 2>/dev/null');

        return $changes === null || $changes === false || trim($changes) === '' ? trim($head) : null;
    }

    public function name(): string
    {
        return 'local';
    }
}
