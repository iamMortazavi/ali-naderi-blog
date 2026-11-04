<?php

namespace Tests\Feature\Console;

use App\Enums\SyncStatus;
use App\Models\SyncRun;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

class SyncVaultTest extends TestCase
{
    use RefreshDatabase;

    private string $vault;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->vault = sys_get_temp_dir().'/vault-'.uniqid();
        File::copyDirectory(base_path('example-vault'), $this->vault);
        config(['services.telegram.bot_token' => '1:T', 'services.telegram.notify_chat_id' => '42']);
        Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->vault);
        parent::tearDown();
    }

    private function sync(): PendingCommand
    {
        return $this->artisan('blog:sync', ['--path' => $this->vault]);
    }

    private function notices(): int
    {
        return Http::recorded(fn (Request $r) => $r['chat_id'] === '42')->count();
    }

    public function test_sync_off(): void
    {
        SiteSettings::put('sync', ['driver' => 'none']);

        $this->artisan('blog:sync')->expectsOutputToContain('sync is off')->assertSuccessful();
        $this->artisan('blog:sync --quiet-if-idle')->doesntExpectOutputToContain('sync is off')->assertSuccessful();
    }

    public function test_the_maintainer_hears_only_when_sync_starts_failing(): void
    {
        $this->sync()->expectsOutputToContain('created 3')->assertSuccessful();
        $this->assertSame(0, $this->notices());

        File::put($this->vault.'/posts/bad.md', "---\nslug: Bad Slug\ndate: 2026-11-01\npublish: true\n---\n");
        $this->sync()->assertFailed();
        $this->sync()->assertFailed();
        $this->assertSame(1, $this->notices());

        File::delete($this->vault.'/posts/bad.md');
        $this->sync()->assertSuccessful();
        File::put($this->vault.'/posts/bad.md', "---\nslug: Bad Slug\ndate: 2026-11-01\npublish: true\n---\n");
        $this->sync()->assertFailed();
        $this->assertSame(2, $this->notices());

        $this->assertSame(5, SyncRun::query()->count(), 'a folder without a revision is always imported');
        $this->assertSame(SyncStatus::Failed, SyncRun::query()->latest('id')->first()->status);
    }

    public function test_a_local_folder_outside_the_vault_root_is_reported(): void
    {
        config(['blog.vault_root' => base_path('example-vault')]);
        SiteSettings::put('sync', ['driver' => 'local', 'local_path' => $this->vault]);

        $this->artisan('blog:sync')->expectsOutputToContain('must be inside')->assertFailed();
    }
}
