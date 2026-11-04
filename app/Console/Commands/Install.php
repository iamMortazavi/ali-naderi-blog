<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
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
    private const EXTENSIONS = ['intl', 'gd', 'pdo_mysql', 'mbstring', 'fileinfo', 'zip', 'openssl', 'ctype', 'dom'];

    public function handle(): int
    {
        $this->line('== blog:install '.now()->toDateTimeString());

        $ok = version_compare(PHP_VERSION, '8.3.0', '>=');
        $this->line(($ok ? 'OK ' : 'MISSING ').'PHP '.PHP_VERSION.' (8.3 or newer needed)');
        foreach (self::EXTENSIONS as $extension) {
            $loaded = extension_loaded($extension);
            $ok = $ok && $loaded;
            $this->line(($loaded ? 'OK ' : 'MISSING ')."php extension {$extension}");
        }
        if (! $ok) {
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

        Artisan::call('migrate', ['--force' => true]);
        $this->line(trim(Artisan::output()) ?: 'OK database up to date');

        if (! User::query()->exists()) {
            Artisan::call('db:seed', ['--force' => true]);
            $this->line('OK admin account and starting content created (ADMIN_EMAIL in .env)');
        }

        Artisan::call('optimize:clear');
        Artisan::call('optimize');
        Artisan::call('filament:optimize');
        $this->line('OK caches rebuilt');
        $this->info('Done. Remove this cron job; keep the "schedule:run" one.');

        return self::SUCCESS;
    }
}
