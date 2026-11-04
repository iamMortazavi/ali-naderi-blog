<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Telegram\TelegramPublisher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * First install and every update on a host without SSH. Run it once from cPanel → Cron Jobs:
 *   cd /home/USER/blog && php artisan blog:install >> storage/logs/install.log 2>&1
 * then delete that cron job. Safe to run again after uploading a new release.
 */
#[Signature('blog:install')]
#[Description('Check the server, create the app key, update the database and caches')]
class Install extends Command
{
    /** composer.lock needs PHP 8.4.1 or newer. */
    public const MIN_PHP = '8.4.1';

    public const EXTENSIONS = [
        'intl', 'gd', 'pdo_mysql', 'mbstring', 'fileinfo', 'zip', 'openssl', 'ctype', 'dom',
        'tokenizer', 'xmlreader', 'iconv', 'session', 'filter', 'curl',
    ];

    public function handle(): int
    {
        // Before anything reads config: an old cached config (from the previous release) would hide
        // changes made to .env since then.
        $this->clearStaleCaches();

        $this->line('== blog:install '.now()->toDateTimeString());
        $this->line('PHP binary: '.PHP_BINARY);

        $ok = version_compare($this->phpVersion(), self::MIN_PHP, '>=');
        $this->line(($ok ? 'OK ' : 'MISSING ').'PHP '.$this->phpVersion().' ('.self::MIN_PHP.' or newer needed)');
        $missing = $this->missingExtensions();
        foreach (self::EXTENSIONS as $extension) {
            $this->line((in_array($extension, $missing, true) ? 'MISSING ' : 'OK ')."php extension {$extension}");
        }
        if (! $ok || $missing) {
            $this->error('Fix the items marked MISSING in cPanel → Select PHP Version, then run again.');

            return self::FAILURE;
        }

        if (! config('app.key')) {
            Artisan::call('key:generate', ['--force' => true]);
            $this->line('OK app key created in .env');
        }

        try {
            DB::connection()->getPdo();
        } catch (Throwable $e) {
            $this->error('Cannot reach the database: '.$e->getMessage());
            $this->error('Check DB_DATABASE, DB_USERNAME and DB_PASSWORD in .env (cPanel → MySQL Databases).');

            return self::FAILURE;
        }

        $firstInstall = ! Schema::hasTable('users') || ! User::query()->exists();

        // Visitors get a "back soon" page while the tables change; the site always comes back up.
        $this->maintenance(down: true);
        try {
            Artisan::call('migrate', ['--force' => true]);
            $this->line(trim(Artisan::output()) ?: 'OK database up to date');

            // HTML made by an older release (sanitizing, footnotes, image sizes) is made again.
            if (array_key_exists('blog:render', Artisan::all())) {
                Artisan::call('blog:render');
                $this->line(trim(Artisan::output()) ?: 'OK posts and pages rendered');
            }

            if ($firstInstall) {
                Artisan::call('db:seed', ['--force' => true]);
                $this->line('OK admin account and starting content created (ADMIN_EMAIL in .env)');
                // Whatever is already live (an imported archive) is never sent to the channel.
                $skipped = app(TelegramPublisher::class)->baseline();
                $this->line("OK {$skipped} existing post(s) marked as not to be sent to Telegram");
            }
        } finally {
            $this->maintenance(down: false);
        }

        $this->rebuildCaches();
        $this->line('OK caches rebuilt');
        $this->info('Done. Remove this cron job; keep the "schedule:run" one.');

        return self::SUCCESS;
    }

    protected function phpVersion(): string
    {
        return PHP_VERSION;
    }

    /** @return list<string> */
    protected function missingExtensions(): array
    {
        return array_values(array_filter(self::EXTENSIONS, fn ($e) => ! extension_loaded($e)));
    }

    /** Drop cached config/routes/views of the previous release and read .env again. */
    protected function clearStaleCaches(): void
    {
        $wasCached = $this->laravel->configurationIsCached();
        Artisan::call('config:clear');
        if ($wasCached) {
            // This process booted from the old cached config: load .env and config/*.php afresh.
            (new LoadEnvironmentVariables)->bootstrap($this->laravel);
            (new LoadConfiguration)->bootstrap($this->laravel);
            Facade::clearResolvedInstance('config');
        }
        try {
            Artisan::call('optimize:clear');
        } catch (Throwable $e) {
            // e.g. the cache table does not exist yet on a first install; caches are rebuilt at the end.
            $this->line('NOTE caches not fully cleared yet: '.$e->getMessage());
        }
    }

    /** Maintenance mode without a secret; browsers retry after a minute. */
    protected function maintenance(bool $down): void
    {
        Artisan::call($down ? 'down' : 'up', $down ? ['--retry' => 60] : []);
    }

    protected function rebuildCaches(): void
    {
        Artisan::call('optimize:clear');
        Artisan::call('optimize');
        Artisan::call('filament:optimize');
    }
}
