<?php

namespace Tests\Feature\Obsidian;

use App\Enums\PostSource;
use App\Enums\PostStatus;
use App\Enums\SyncStatus;
use App\Enums\TelegramStatus;
use App\Models\Post;
use App\Models\Redirect;
use App\Models\SyncRun;
use App\Services\Obsidian\LocalVault;
use App\Services\Obsidian\Vault;
use App\Services\Obsidian\VaultSync;
use App\Services\Telegram\TelegramPublisher;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
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

    private function sync(?Vault $vault = null, bool $force = false): SyncRun
    {
        return app(VaultSync::class)->run($vault ?? new LocalVault($this->vault), 'posts', $force);
    }

    private function edit(string $file, string $search, string $replace): void
    {
        $path = $this->vault.'/posts/'.$file;
        File::put($path, str_replace($search, $replace, File::get($path)));
    }

    /** The example vault with a fixed revision, like a GitHub vault. */
    private function revisioned(string $revision, ?callable $copy = null): Vault
    {
        return new class(new LocalVault($this->vault), $revision, $copy) implements Vault
        {
            public function __construct(private LocalVault $inner, private string $revision, private $copy) {}

            public function files(): array
            {
                return $this->inner->files();
            }

            public function read(string $path): string
            {
                return $this->inner->read($path);
            }

            public function localCopy(string $path): string
            {
                return $this->copy ? ($this->copy)($path, $this->inner) : $this->inner->localCopy($path);
            }

            public function revision(): ?string
            {
                return $this->revision;
            }

            public function name(): string
            {
                return 'github';
            }
        };
    }

    public function test_imports_published_notes_only(): void
    {
        $run = $this->sync();

        $this->assertSame(SyncStatus::Success, $run->status);
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

        $this->assertSame(SyncStatus::Failed, $run->status);
        $this->assertSame(3, $run->created_count);
        $errors = collect($run->messages)->where('level', 'error');
        $this->assertTrue($errors->contains(fn ($m) => $m['file'] === 'posts/bad.md' && str_contains($m['text'], "'slug'")));
        $this->assertTrue($errors->contains(fn ($m) => $m['file'] === 'posts/bad.md' && str_contains($m['text'], "'aliases'")));
        $this->assertTrue($errors->contains(fn ($m) => $m['file'] === 'posts/embed.md' && str_contains($m['text'], 'embedding a note')));
    }

    public function test_yaml_lists_and_numbers_in_the_wrong_place_do_not_abort_the_run(): void
    {
        File::put($this->vault.'/posts/list.md', "---\nslug: [a, b]\ntitle: {x: 1}\ndate: 2026-11-01\npublish: true\ntags: [[nested]]\n---\nbody\n");
        File::put($this->vault.'/posts/number.md', "---\nslug: number\ndate: 1730000000\npublish: true\n---\nbody\n");
        File::put($this->vault.'/posts/yaml.md', "---\nslug: [unclosed\npublish: true\n---\nbody\n");

        $run = $this->sync();

        $this->assertSame(SyncStatus::Failed, $run->status);
        $this->assertSame(3, $run->created_count);
        $errors = collect($run->messages)->where('level', 'error');
        $this->assertTrue($errors->contains(fn ($m) => $m['file'] === 'posts/list.md' && str_contains($m['text'], "'slug' must be text")));
        $this->assertTrue($errors->contains(fn ($m) => $m['file'] === 'posts/list.md' && str_contains($m['text'], "'title' must be text")));
        $this->assertTrue($errors->contains(fn ($m) => $m['file'] === 'posts/number.md' && str_contains($m['text'], "'date'")));
        $this->assertTrue($errors->contains(fn ($m) => $m['file'] === 'posts/yaml.md' && str_contains($m['text'], 'properties cannot be read')));
    }

    public function test_slug_change_updates_the_same_post_adds_a_redirect_and_is_not_sent_again(): void
    {
        config(['services.telegram.bot_token' => '1:T', 'services.telegram.chat_id' => '@c']);
        SiteSettings::put('telegram', ['languages' => ['fa']]);
        $this->sync();
        $post = Post::query()->where('slug', 'blog-architecture')->first();
        $post->telegramMessage()->create(['chat_id' => '@c', 'status' => TelegramStatus::Sent, 'message_id' => 1, 'sent_at' => now()]);

        $this->edit('ساختار فنی وبلاگ.md', 'slug: blog-architecture', 'slug: how-this-blog-works');
        $run = $this->sync();

        $this->assertSame(SyncStatus::Success, $run->status);
        $this->assertSame(0, $run->created_count);
        $this->assertSame(1, Post::query()->where('locale', 'fa')->where('source_path', 'posts/ساختار فنی وبلاگ.md')->count());
        $fresh = $post->fresh();
        $this->assertSame('how-this-blog-works', $fresh->slug);
        $this->assertSame('blog-architecture', $fresh->translation_key, 'the stored key (and media folder) is kept');
        $this->assertSame('/posts/how-this-blog-works', Redirect::query()->where('from_path', '/posts/blog-architecture')->value('to_path'));
        $this->assertFalse(app(TelegramPublisher::class)->due()->contains('id', $post->id));
        $this->assertStringContainsString('(/posts/how-this-blog-works)', Post::query()->where('locale', 'fa')->where('slug', 'hello-world')->value('body'),
            'links in other notes follow the new slug');
    }

    public function test_rename_keeps_the_same_post_and_removal_unpublishes(): void
    {
        $this->sync();
        $id = Post::query()->where('slug', 'blog-architecture')->value('id');

        File::move($this->vault.'/posts/ساختار فنی وبلاگ.md', $this->vault.'/posts/architecture.md');
        $this->sync();
        $this->assertSame($id, Post::query()->where('slug', 'blog-architecture')->value('id'));
        $this->assertSame('posts/architecture.md', Post::find($id)->source_path);

        File::delete($this->vault.'/posts/architecture.md');
        $run = $this->sync();
        $this->assertSame(1, $run->unpublished_count);
        $this->assertSame(PostStatus::Draft, Post::find($id)->status);
    }

    public function test_a_broken_note_keeps_its_live_post(): void
    {
        $this->sync();
        $architecture = Post::query()->where('slug', 'blog-architecture')->value('id'); // no id: found by file
        $hello = Post::query()->where('locale', 'fa')->where('slug', 'hello-world')->value('id');

        $this->edit('ساختار فنی وبلاگ.md', 'slug: blog-architecture', 'slug: Not Valid');
        $this->edit('سلام دنیا.md', 'date: 2026-11-01T10:00', 'date: [2026]');
        $run = $this->sync();

        $this->assertSame(SyncStatus::Failed, $run->status);
        $this->assertSame(0, $run->unpublished_count);
        $this->assertSame(PostStatus::Published, Post::find($architecture)->status);
        $this->assertSame('blog-architecture', Post::find($architecture)->slug);
        $this->assertSame(PostStatus::Published, Post::find($hello)->status);
    }

    public function test_an_empty_posts_folder_unpublishes_nothing(): void
    {
        $this->sync();
        File::cleanDirectory($this->vault.'/posts');

        $run = $this->sync();

        $this->assertSame(SyncStatus::Failed, $run->status);
        $this->assertSame(0, $run->unpublished_count);
        $this->assertSame(3, Post::query()->where('status', PostStatus::Published)->count());
        $this->assertTrue(collect($run->messages)->contains(fn ($m) => str_contains($m['text'], 'has no notes')));
    }

    public function test_a_post_is_refreshed_when_a_note_it_links_to_becomes_public(): void
    {
        $this->sync();
        $this->assertStringNotContainsString('/posts/raw-ideas', Post::query()->where('locale', 'fa')->where('slug', 'hello-world')->value('body'));

        $this->edit('ایده‌های خام.md', 'publish: false', 'publish: true');
        $run = $this->sync();

        $this->assertSame(1, $run->created_count);
        $this->assertGreaterThanOrEqual(1, $run->updated_count);
        $this->assertStringContainsString('/posts/raw-ideas', Post::query()->where('locale', 'fa')->where('slug', 'hello-world')->value('body'));
    }

    public function test_an_unchanged_revision_is_skipped(): void
    {
        $first = $this->sync($this->revisioned('aaa111'));
        $this->assertSame(SyncStatus::Success, $first->status);

        $sync = app(VaultSync::class);
        $again = $sync->run($this->revisioned('aaa111'), 'posts');
        $this->assertTrue($sync->skipped());
        $this->assertTrue($again->is($first));
        $this->assertSame(1, SyncRun::query()->count());

        $sync->run($this->revisioned('aaa111'), 'posts', force: true);
        $this->assertFalse($sync->skipped());
        $sync->run($this->revisioned('bbb222'), 'posts');
        $this->assertFalse($sync->skipped());
        $this->assertSame(3, SyncRun::query()->count());

        SiteSettings::put('sync', ['driver' => 'github', 'github_repo' => 'me/other']);
        $sync->run($this->revisioned('bbb222'), 'posts');
        $this->assertFalse($sync->skipped(), 'changed settings import again');

        $this->assertSame(4, SyncRun::query()->count());
    }

    public function test_a_failed_attachment_copy_is_retried_on_the_next_run(): void
    {
        $broken = $this->revisioned('ccc333', function (string $path, LocalVault $inner) {
            if (str_ends_with($path, '.png')) {
                throw new RuntimeException('disk full');
            }

            return $inner->localCopy($path);
        });

        $run = $this->sync($broken);
        $this->assertSame(SyncStatus::Failed, $run->status);
        $this->assertTrue(collect($run->messages)->contains(fn ($m) => str_contains($m['text'], 'could not be copied')));
        $this->assertFalse(Post::query()->where('locale', 'fa')->where('slug', 'hello-world')->exists());

        $run = $this->sync($this->revisioned('ccc333'));
        $this->assertSame(SyncStatus::Success, $run->status, 'a failed run is never skipped');
        Storage::disk('public')->assertExists('posts/fa/hello-world/cover-hello.png');
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
