<?php

namespace Tests\Feature\Console;

use App\Console\Commands\Install;
use App\Enums\PostStatus;
use App\Enums\TelegramStatus;
use App\Enums\UserRole;
use App\Models\Post;
use App\Models\TelegramMessage;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class InstallTest extends TestCase
{
    use RefreshDatabase;

    /** blog:install with the server checks, maintenance mode and cache files replaced by recorders. */
    private function command(array $missing = [], string $php = '8.5.0'): Install
    {
        $command = new class extends Install
        {
            protected $signature = 'blog:install';

            public array $calls = [];

            public array $missing = [];

            public string $php = '8.5.0';

            protected function phpVersion(): string
            {
                return $this->php;
            }

            protected function missingExtensions(): array
            {
                return $this->missing;
            }

            protected function clearStaleCaches(): void
            {
                $this->calls[] = 'clear';
            }

            protected function maintenance(bool $down): void
            {
                $this->calls[] = $down ? 'down' : 'up';
            }

            protected function rebuildCaches(): void
            {
                $this->calls[] = 'rebuild';
            }
        };
        $command->missing = $missing;
        $command->php = $php;
        $this->app->make(Kernel::class)->registerCommand($command);

        return $command;
    }

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('k', 32)),
            'blog.admin' => ['name' => 'Ali', 'email' => 'admin@blog.test', 'password' => 'a-long-password'],
            'services.telegram.bot_token' => '1:T', 'services.telegram.chat_id' => '@c',
        ]);
    }

    public function test_first_install_seeds_the_admin_from_config_and_marks_live_posts_as_not_to_send(): void
    {
        $post = Post::create(['locale' => 'fa', 'slug' => 'imported', 'title' => 'قدیمی', 'body' => 'x',
            'status' => PostStatus::Published, 'published_at' => now()->subHour()]);
        $command = $this->command();

        $this->artisan('blog:install')
            ->expectsOutputToContain('PHP binary: '.PHP_BINARY)
            ->expectsOutputToContain('OK php extension curl')
            ->expectsOutputToContain('1 existing post(s)')
            ->assertSuccessful();

        $admin = User::query()->sole();
        $this->assertSame('admin@blog.test', $admin->email);
        $this->assertSame('Ali', $admin->name);
        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertTrue(Hash::check('a-long-password', $admin->password));
        $this->assertSame(TelegramStatus::Skipped, TelegramMessage::query()->where('post_id', $post->id)->value('status'));
        $this->assertSame(['clear', 'down', 'up', 'rebuild'], $command->calls);
    }

    public function test_an_update_does_not_seed_or_baseline(): void
    {
        User::query()->create(['name' => 'x', 'email' => 'x@blog.test', 'password' => 'secret-secret', 'role' => UserRole::Admin]);
        Post::create(['locale' => 'fa', 'slug' => 'new', 'title' => 'تازه', 'body' => 'x',
            'status' => PostStatus::Published, 'published_at' => now()->subHour()]);
        $this->command();

        $this->artisan('blog:install')->assertSuccessful();

        $this->assertSame(1, User::query()->count());
        $this->assertSame(0, TelegramMessage::query()->count());
    }

    public function test_a_missing_extension_stops_before_touching_anything(): void
    {
        $command = $this->command(missing: ['curl']);

        $this->artisan('blog:install')
            ->expectsOutputToContain('MISSING php extension curl')
            ->expectsOutputToContain('OK php extension intl')
            ->assertFailed();

        $this->assertSame(['clear'], $command->calls);
        $this->assertSame(0, User::query()->count());
    }

    public function test_an_old_php_is_refused(): void
    {
        $command = $this->command(php: '8.4.0');

        $this->artisan('blog:install')->expectsOutputToContain('MISSING PHP 8.4.0 (8.4.1 or newer needed)')->assertFailed();
        $this->assertSame(['clear'], $command->calls);
    }

    public function test_the_site_comes_back_up_when_the_install_fails(): void
    {
        config(['blog.admin.email' => null]);
        $command = $this->command();

        try {
            $this->artisan('blog:install')->run();
            $this->fail('no exception');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('ADMIN_EMAIL', $e->getMessage());
        }
        $this->assertSame(['clear', 'down', 'up'], $command->calls);
    }

    public function test_the_extension_list(): void
    {
        foreach (['tokenizer', 'xmlreader', 'iconv', 'session', 'filter', 'curl', 'pdo_mysql', 'intl'] as $extension) {
            $this->assertContains($extension, Install::EXTENSIONS);
        }
    }
}
