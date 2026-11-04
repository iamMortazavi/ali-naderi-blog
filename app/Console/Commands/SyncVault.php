<?php

namespace App\Console\Commands;

use App\Services\Obsidian\LocalVault;
use App\Services\Obsidian\VaultSync;
use App\Services\Telegram\TelegramPublisher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('blog:sync {--path= : Import from this vault folder instead of the one in Settings} {--quiet-if-idle : Print nothing when sync is turned off}')]
#[Description('Import published notes from the Obsidian vault')]
class SyncVault extends Command
{
    public function handle(VaultSync $sync, TelegramPublisher $telegram): int
    {
        $vault = $this->option('path') ? new LocalVault($this->option('path')) : VaultSync::fromSettings();
        if (! $vault) {
            if (! $this->option('quiet-if-idle')) {
                $this->warn('Obsidian sync is off (Settings → Obsidian sync).');
            }

            return self::SUCCESS;
        }

        $run = $sync->run($vault);
        foreach ($run->messages ?? [] as $m) {
            $line = trim(($m['file'] ? $m['file'].': ' : '').$m['text']);
            match ($m['level']) {
                'error' => $this->error($line),
                'warning' => $this->warn($line),
                default => $this->line($line),
            };
        }
        $this->info("created {$run->created_count}, updated {$run->updated_count}, unchanged {$run->unchanged_count}, unpublished {$run->unpublished_count}, private {$run->skipped_count}");

        if ($run->status === 'failed') {
            $telegram->notify("❌ همگام‌سازی Obsidian با خطا روبه‌رو شد ({$run->errorCount()} خطا). جزئیات در پنل: همگام‌سازی‌ها.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
