<?php

namespace Tests\Feature\Admin;

use App\Enums\SyncStatus;
use App\Enums\TelegramStatus;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Widgets\BlogStatsOverview;
use App\Filament\Widgets\LatestPostsTable;
use App\Models\SyncRun;
use Livewire\Livewire;

class PanelAccessTest extends AdminTestCase
{
    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/login')->assertOk()->assertSee('dir="rtl"', false);
    }

    public function test_editors_reach_content_but_not_admin_areas(): void
    {
        $this->actingAs($this->editor());

        foreach (['/admin', '/admin/posts', '/admin/posts/create', '/admin/pages', '/admin/tags', '/admin/series'] as $url) {
            $this->get($url)->assertOk();
        }
        foreach (['/admin/settings', '/admin/users', '/admin/redirects', '/admin/sync-runs'] as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    public function test_admins_reach_everything(): void
    {
        $this->actingAs($this->admin());

        foreach (['/admin', '/admin/posts', '/admin/pages', '/admin/tags', '/admin/series', '/admin/settings',
            '/admin/users', '/admin/redirects', '/admin/sync-runs'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_lists_and_dashboard_render_with_content(): void
    {
        $this->actingAs($this->admin());
        $post = $this->makePost(['title' => 'نوشته‌ی نمونه']);
        $post->telegramMessage()->create(['chat_id' => '@c', 'status' => TelegramStatus::Sent, 'message_id' => 1, 'sent_at' => now()]);
        $this->obsidianPost(['title' => 'از ابسیدین']);
        SyncRun::create(['source' => 'local', 'status' => SyncStatus::Failed, 'started_at' => now(), 'messages' => [['level' => 'error', 'file' => 'a.md', 'text' => 'x']]]);

        $this->get('/admin')->assertOk();
        Livewire::test(BlogStatsOverview::class)->assertSee('نوشته‌های منتشرشده');
        Livewire::test(LatestPostsTable::class)->assertSee('نوشته‌ی نمونه')->assertCanSeeTableRecords([$post]);
        $this->get('/admin/posts')->assertOk()->assertSee('نوشته‌ی نمونه')->assertSee(TelegramStatus::Sent->getLabel());
        $this->get(PostResource::getUrl('edit', ['record' => $post]))->assertOk()->assertSee(TelegramStatus::Sent->getLabel());
        $this->get('/admin/sync-runs')->assertOk()->assertSee(SyncStatus::Failed->getLabel());
    }
}
