<?php

namespace App\Services\Obsidian;

use App\Enums\SyncDriver;
use App\Support\SiteSettings;
use RuntimeException;

/** Builds the vault chosen in Settings → Obsidian sync. */
final class VaultFactory
{
    /**
     * The configured vault, or null when sync is off.
     *
     * @throws RuntimeException when the settings are incomplete or the local folder is not allowed
     */
    public static function fromSettings(): ?Vault
    {
        $sync = SiteSettings::group('sync');
        $driver = SyncDriver::tryFrom((string) ($sync['driver'] ?? 'none')) ?? SyncDriver::None;

        return match ($driver) {
            SyncDriver::Local => self::local((string) ($sync['local_path'] ?? '')),
            SyncDriver::GitHub => self::github((string) ($sync['github_repo'] ?? ''), (string) ($sync['github_branch'] ?? '')),
            SyncDriver::None => null,
        };
    }

    /** A folder on this server; relative paths are relative to the app folder. It must be inside config('blog.vault_root'). */
    public static function local(string $path): LocalVault
    {
        $path = trim($path);
        if ($path === '') {
            throw new RuntimeException('Set the vault folder in Settings → Obsidian sync.');
        }
        $full = str_starts_with($path, '/') ? $path : base_path($path);

        return new LocalVault($full, (string) config('blog.vault_root'));
    }

    public static function github(string $repo, string $branch = ''): GitHubVault
    {
        if (trim($repo) === '') {
            throw new RuntimeException('Set the GitHub repository (owner/name) in Settings → Obsidian sync.');
        }

        return new GitHubVault(trim($repo), trim($branch) ?: 'main', config('services.vault.github_token') ?: null);
    }
}
