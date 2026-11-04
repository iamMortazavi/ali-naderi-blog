<?php

namespace App\Console\Commands;

use App\Enums\SyncStatus;
use App\Models\SyncRun;
use App\Services\Obsidian\LocalVault;
use App\Services\Obsidian\VaultFactory;
use App\Services\Obsidian\VaultSync;
use App\Services\Telegram\TelegramPublisher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('blog:sync
    {--path= : Import from this vault folder instead of the one in Settings}
    {--force : Import even when the vault has not changed since the last successful run}
    {--quiet-if-idle : Print nothing when sync is turned off or nothing changed}')]
#[Description('Import published notes from the Obsidian vault')]
class SyncVault extends Command
{
    public function handle(VaultSync $sync, TelegramPublisher $telegram): int
    {
        $quiet = (bool) $this->option('quiet-if-idle');
        try {
            $vault = $this->option('path') ? new LocalVault($this->option('path')) : VaultFactory::fromSettings();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        if (! $vault) {
            if (! $quiet) {
                $this->warn('Obsidian sync is off (Settings → Obsidian sync).');
            }

            return self::SUCCESS;
        }

        $previous = SyncRun::query()->latest('id')->first();
        $run = $sync->run($vault, force: (bool) $this->option('force'));
        if ($sync->skipped()) {
            if (! $quiet) {
                $this->line("Nothing changed since the last import (revision {$run->commit}). Use --force to import anyway.");
            }

            return self::SUCCESS;
        }

        foreach ($run->messages ?? [] as $m) {
            $line = trim(($m['file'] ? $m['file'].': ' : '').$m['text']);
            match ($m['level']) {
                'error' => $this->error($line),
                'warning' => $this->warn($line),
                default => $this->line($line),
            };
        }
        $this->info("created {$run->created_count}, updated {$run->updated_count}, unchanged {$run->unchanged_count}, unpublished {$run->unpublished_count}, private {$run->skipped_count}");

        if ($run->status !== SyncStatus::Failed) {
            return self::SUCCESS;
        }
        // Tell the maintainer when sync starts failing, not on every run while it keeps failing.
        if ($previous?->status !== SyncStatus::Failed) {
            $telegram->notify("❌ همگام‌سازی Obsidian با خطا روبه‌رو شد ({$run->errorCount()} خطا). جزئیات در پنل: همگام‌سازی‌ها.");
        }

        return self::FAILURE;
    }
}
