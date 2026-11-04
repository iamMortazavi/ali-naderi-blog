<?php

namespace Tests\Feature;

use App\Enums\PostSource;
use App\Enums\PostStatus;
use App\Filament\Pages\Settings;
use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Models\Post;
use App\Models\User;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/login')->assertOk()->assertSee('dir="rtl"', false);
    }

    public function test_only_admins_see_settings_and_users(): void
    {
        $this->actingAs($this->user('editor'));
        $this->get('/admin/posts')->assertOk();
        $this->get('/admin/settings')->assertForbidden();
        $this->get('/admin/users')->assertForbidden();

        $this->actingAs($this->user('admin'));
        $this->get('/admin/settings')->assertOk();
        $this->get('/admin/users')->assertOk();
    }

    public function test_create_a_post_from_the_panel(): void
    {
        $admin = $this->user('admin');
        $this->actingAs($admin);

        Livewire::test(CreatePost::class)
            ->fillForm([
                'title' => 'نوشته‌ی پنل', 'slug' => 'from-panel', 'locale' => 'fa',
                'status' => PostStatus::Published->value, 'published_at' => now()->subMinute()->format('Y-m-d H:i'),
                'body' => "## تیتر\n\n==مهم==",
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $post = Post::query()->where('slug', 'from-panel')->firstOrFail();
        $this->assertSame(PostSource::Panel, $post->source);
        $this->assertSame($admin->id, $post->author_id);
        $this->assertStringContainsString('<mark>مهم</mark>', $post->body_html);
        $this->get('/posts/from-panel')->assertOk();
    }

    public function test_slug_rules(): void
    {
        $this->actingAs($this->user('admin'));
        Livewire::test(CreatePost::class)
            ->fillForm(['title' => 'x', 'slug' => 'Not Latin!', 'locale' => 'fa', 'status' => 'draft', 'body' => 'x'])
            ->call('create')
            ->assertHasFormErrors(['slug']);
    }

    public function test_obsidian_posts_cannot_be_saved_in_the_panel(): void
    {
        $this->actingAs($this->user('admin'));
        $post = Post::create(['locale' => 'fa', 'slug' => 'from-vault', 'title' => 'x', 'body' => 'x',
            'status' => PostStatus::Published, 'published_at' => now(), 'source' => PostSource::Obsidian]);

        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])
            ->assertSee('این نوشته از Obsidian می‌آید');
    }

    public function test_settings_are_saved(): void
    {
        $this->actingAs($this->user('admin'));

        Livewire::test(Settings::class)
            ->set('data.site.url', 'https://alinaderi.ir/')
            ->set('data.look.name', 'ink')
            ->set('data.author.name_fa', 'علی')
            ->set('data.author.name_en', 'Ali')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('https://alinaderi.ir', SiteSettings::url());
        $this->assertSame('ink', SiteSettings::look());
    }
}
