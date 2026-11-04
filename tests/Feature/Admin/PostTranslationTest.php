<?php

namespace Tests\Feature\Admin;

use App\Enums\PostSource;
use App\Enums\PostStatus;
use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Models\Post;
use Livewire\Livewire;

class PostTranslationTest extends AdminTestCase
{
    public function test_create_a_post_from_the_panel(): void
    {
        $admin = $this->admin();
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

    public function test_a_new_post_is_not_linked_to_another_language_post_with_the_same_slug(): void
    {
        $this->actingAs($this->admin());
        $english = $this->makePost(['locale' => 'en', 'slug' => 'hello', 'translation_key' => 'hello']);

        Livewire::test(CreatePost::class)
            ->fillForm(['title' => 'سلام', 'slug' => 'hello', 'locale' => 'fa', 'status' => 'draft', 'body' => 'x'])
            ->call('create')
            ->assertHasNoFormErrors();

        $persian = Post::query()->where('locale', 'fa')->where('slug', 'hello')->firstOrFail();
        $this->assertNotSame($english->translation_key, $persian->translation_key);
    }

    public function test_slug_rules(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreatePost::class)
            ->fillForm(['title' => 'x', 'slug' => 'Not Latin!', 'locale' => 'fa', 'status' => 'draft', 'body' => 'x'])
            ->call('create')
            ->assertHasFormErrors(['slug']);
    }

    public function test_editing_an_unlinked_post_saves_and_keeps_its_key(): void
    {
        $this->actingAs($this->admin());
        $post = $this->makePost(['slug' => 'alone', 'translation_key' => 'my-own-key']);

        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])
            ->assertFormSet(['translation_key' => null])
            ->fillForm(['title' => 'عنوان تازه'])
            ->call('save')
            ->assertHasNoFormErrors();

        $post->refresh();
        $this->assertSame('عنوان تازه', $post->title);
        $this->assertSame('my-own-key', $post->translation_key);
    }

    public function test_link_and_unlink_a_translation(): void
    {
        $this->actingAs($this->admin());
        $english = $this->makePost(['locale' => 'en', 'slug' => 'hello', 'translation_key' => 'hello', 'title' => 'Hello']);
        $persian = $this->makePost(['slug' => 'salam', 'translation_key' => 'salam']);

        Livewire::test(EditPost::class, ['record' => $persian->getRouteKey()])
            ->assertFormFieldExists('translation_key')
            ->fillForm(['translation_key' => 'hello'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('hello', $persian->refresh()->translation_key);
        $this->assertSame(['en'], $persian->translations()->pluck('locale')->all());

        // The linked post shows its translation; clearing the field unlinks it.
        Livewire::test(EditPost::class, ['record' => $persian->getRouteKey()])
            ->assertFormSet(['translation_key' => 'hello'])
            ->fillForm(['translation_key' => null])
            ->call('save')
            ->assertHasNoFormErrors();

        $persian->refresh();
        $this->assertNotSame('hello', $persian->translation_key);
        $this->assertCount(0, $persian->translations());
        $this->assertSame('hello', $english->refresh()->translation_key);
    }

    public function test_unlinking_a_post_whose_slug_equals_the_shared_key_still_unlinks_it(): void
    {
        $this->actingAs($this->admin());
        $this->makePost(['locale' => 'en', 'slug' => 'hello', 'translation_key' => 'hello']);
        $persian = $this->makePost(['slug' => 'hello', 'translation_key' => 'hello']);

        Livewire::test(EditPost::class, ['record' => $persian->getRouteKey()])
            ->fillForm(['translation_key' => null])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertCount(0, $persian->refresh()->translations());
    }

    public function test_a_translation_already_linked_in_this_language_is_a_validation_error(): void
    {
        $this->actingAs($this->admin());
        $this->makePost(['locale' => 'en', 'slug' => 'hello', 'translation_key' => 'hello']);
        $this->makePost(['slug' => 'salam', 'translation_key' => 'hello']); // already its Persian version
        $other = $this->makePost(['slug' => 'dorood', 'translation_key' => 'dorood']);

        Livewire::test(EditPost::class, ['record' => $other->getRouteKey()])
            ->fillForm(['translation_key' => 'hello'])
            ->call('save')
            ->assertHasFormErrors(['translation_key']);

        $this->assertSame('dorood', $other->refresh()->translation_key);
    }

    public function test_changing_the_language_of_a_linked_post_into_a_clash_is_a_validation_error(): void
    {
        $this->actingAs($this->admin());
        $this->makePost(['locale' => 'en', 'slug' => 'hello', 'translation_key' => 'hello']);
        $persian = $this->makePost(['slug' => 'salam', 'translation_key' => 'hello']);

        // Switching the language clears the field; forcing the old key back must fail validation, not the database.
        Livewire::test(EditPost::class, ['record' => $persian->getRouteKey()])
            ->fillForm(['locale' => 'en'])
            ->set('data.translation_key', 'hello')
            ->call('save')
            ->assertHasFormErrors(['translation_key']);
    }
}
