<?php

namespace App\Services\Obsidian;

/** Read access to an Obsidian vault, wherever it lives. Paths use "/" and are relative to the vault root. */
interface Vault
{
    /** @return array<string,string> every visible file → a fingerprint that changes when the file changes */
    public function files(): array;

    public function read(string $path): string;

    /** Absolute path of a local copy of the file, for copying attachments. */
    public function localCopy(string $path): string;

    /** Commit or snapshot id, for the sync history. */
    public function revision(): ?string;

    public function name(): string;
}
