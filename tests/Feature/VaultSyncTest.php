<?php

namespace Tests\Feature;

use App\Enums\PostSource;
use App\Enums\PostStatus;
use App\Models\Post;
use App\Services\Obsidian\LocalVault;
use App\Services\Obsidian\VaultSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VaultSyncTest extends TestCase
{
    use RefreshDatabase;

    private string $vault;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->vault = sys_get_temp_dir().'/vault-'.uniqid();
        File::copyDirectory(base_path('example-vault'), $this->vault);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->vault);
        parent::tearDown();
    }

    private function sync()
    {
        return app(VaultSync::class)->run(new LocalVault($this->vault), 'posts');
    }

    public function test_imports_published_notes_only(): void
    {
        $run = $this->sync();

        $this->assertSame('success', $run->status);
        $this->assertSame(3, $run->created_count);
        $this->assertSame(1, $run->skipped_count, 'the publish: false note stays private');
        $this->assertSame(['blog-architecture', 'hello-world', 'hello-world'], Post::query()->orderBy('slug')->pluck('slug')->all());
        $this->assertFalse(Post::query()->where('slug', 'raw-ideas')->exists());

        $post = Post::query()->where('locale', 'fa')->where('slug', 'hello-world')->first();
        $this->assertSame(PostSource::Obsidian, $post->source);
        $this->assertStringNotContainsString('خصوصی است', $post->body, '%% comments %% are removed');
        $this->assertStringContainsString('[این نوشته](/posts/blog-architecture)', $post->body);
        $this->assertStringContainsString('ایده‌های خام', $post->body, 'link to a private note becomes plain text');
        $this->assertSame(['یادداشت', 'نوشتن'], $post->tags->pluck('name')->all());
        $this->assertSame('2026-11-01 10:00', $post->published_at->format('Y-m-d H:i'));
        Storage::disk('public')->assertExists('posts/fa/hello-world/cover-hello.png');
        $this->assertSame('posts/fa/hello-world/cover-hello.png', $post->cover_path);

        $architecture = Post::query()->where('slug', 'blog-architecture')->first();
        $this->assertMatchesRegularExpression('/!\[نمودار مسیر انتشار\]\(<file-[0-9a-f]{8}\.png>\)/', $architecture->body);
        $this->assertStringContainsString('(/posts/hello-world#چرا-مینویسم)', $architecture->body);
        $this->assertSame('پشت صحنه', $architecture->series->name);
    }

    public function test_second_run_changes_nothing(): void
    {
        $this->sync();
        $run = $this->sync();

        $this->assertSame(0, $run->created_count);
        $this->assertSame(0, $run->updated_count);
        $this->assertSame(3, $run->unchanged_count);
    }

    public function test_a_broken_note_is_reported_and_the_rest_still_import(): void
    {
        File::put($this->vault.'/posts/bad.md', "---\nslug: Bad Slug\ndate: 2026-11-01\npublish: true\naliases: [x]\n---\nbody\n");
        File::put($this->vault.'/posts/embed.md', "---\nslug: embed\ndate: 2026-11-01\npublish: true\n---\n![[سلام دنیا]]\n");

        $run = $this->sync();

        $this->assertSame('failed', $run->status);
        $this->assertSame(3, $run->created_count);
        $errors = collect($run->messages)->where('level', 'error');
        $this->assertTrue($errors->contains(fn ($m) => $m['file'] === 'posts/bad.md' && str_contains($m['text'], "'slug'")));
        $this->assertTrue($errors->contains(fn ($m) => $m['file'] === 'posts/bad.md' && str_contains($m['text'], "'aliases'")));
        $this->assertTrue($errors->contains(fn ($m) => $m['file'] === 'posts/embed.md' && str_contains($m['text'], 'embedding a note')));
    }

    public function test_rename_keeps_the_same_post_and_removal_unpublishes(): void
    {
        $this->sync();
        $id = Post::query()->where('slug', 'blog-architecture')->value('id');

        File::move($this->vault.'/posts/ساختار فنی وبلاگ.md', $this->vault.'/posts/architecture.md');
        $this->sync();
        $this->assertSame($id, Post::query()->where('slug', 'blog-architecture')->value('id'));

        File::delete($this->vault.'/posts/architecture.md');
        $run = $this->sync();
        $this->assertSame(1, $run->unpublished_count);
        $this->assertSame(PostStatus::Draft, Post::find($id)->status);
    }

    public function test_panel_posts_are_never_overwritten(): void
    {
        Post::create(['locale' => 'fa', 'slug' => 'hello-world', 'title' => 'از پنل', 'body' => 'x',
            'status' => PostStatus::Published, 'published_at' => now()]);

        $run = $this->sync();

        $this->assertSame('از پنل', Post::query()->where('locale', 'fa')->where('slug', 'hello-world')->value('title'));
        $this->assertTrue(collect($run->messages)->contains(fn ($m) => str_contains($m['text'], 'written in the panel')));
    }
}
