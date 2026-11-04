<?php

namespace App\Services\Obsidian;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/** A vault folder on this server or computer (for local work, or a vault synced to the host). */
class LocalVault implements Vault
{
    private string $root;

    public function __construct(string $root)
    {
        $real = realpath($root);
        if (! $real || ! is_dir($real)) {
            throw new RuntimeException("Vault folder not found: {$root}");
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

    public function revision(): ?string
    {
        $head = @shell_exec('git -C '.escapeshellarg($this->root).' rev-parse --short HEAD 2>/dev/null');

        return $head ? trim($head) : null;
    }

    public function name(): string
    {
        return 'local';
    }
}
