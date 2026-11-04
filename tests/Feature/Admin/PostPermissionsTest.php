<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Posts\Pages\EditPost;
use App\Filament\Resources\Posts\Pages\ListPosts;
use App\Filament\Resources\Posts\Pages\ViewPost;
use App\Filament\Resources\Posts\PostResource;
use App\Models\Post;
use Livewire\Livewire;

class PostPermissionsTest extends AdminTestCase
{
    public function test_editor_edits_and_deletes_only_own_panel_posts(): void
    {
        $editor = $this->editor();
        $own = $this->makePost(['author_id' => $editor->id]);
        $others = $this->makePost(['author_id' => $this->admin()->id]);
        $this->actingAs($editor);

        $this->get(PostResource::getUrl('edit', ['record' => $own]))->assertOk();
        $this->get(PostResource::getUrl('edit', ['record' => $others]))->assertForbidden();
        $this->get(PostResource::getUrl('view', ['record' => $others]))->assertOk();

        Livewire::test(ListPosts::class)
            ->assertTableActionVisible('edit', $own)
            ->assertTableActionVisible('delete', $own)
            ->assertTableActionHidden('edit', $others)
            ->assertTableActionHidden('delete', $others)
            ->assertTableActionVisible('view', $others)
            ->callTableAction('delete', $own);

        $this->assertSoftDeleted($own);
        $this->assertNotSoftDeleted($others);
    }

    public function test_nobody_edits_obsidian_posts_and_only_admins_delete_them(): void
    {
        $editor = $this->editor();
        $post = $this->obsidianPost(['author_id' => $editor->id]);

        $this->actingAs($editor);
        $this->get(PostResource::getUrl('edit', ['record' => $post]))->assertForbidden();
        Livewire::test(ListPosts::class)
            ->assertTableActionHidden('edit', $post)
            ->assertTableActionHidden('delete', $post);

        $this->actingAs($this->admin());
        $this->get(PostResource::getUrl('edit', ['record' => $post]))->assertForbidden();
        Livewire::test(ListPosts::class)
            ->assertTableActionHidden('edit', $post)
            ->assertTableActionVisible('view', $post)
            ->callTableAction('delete', $post);
        $this->assertSoftDeleted($post);
    }

    public function test_obsidian_post_opens_the_view_page(): void
    {
        $this->actingAs($this->admin());
        $post = $this->obsidianPost();

        $this->assertSame(PostResource::getUrl('view', ['record' => $post]), PostResource::recordUrl($post));
        $this->get(PostResource::getUrl('view', ['record' => $post]))
            ->assertOk()
            ->assertSee('این نوشته از Obsidian می‌آید');

        Livewire::test(ViewPost::class, ['record' => $post->getRouteKey()])
            ->assertActionHidden('edit');
    }

    public function test_admin_edits_any_panel_post(): void
    {
        $this->actingAs($this->admin());
        $post = $this->makePost(['author_id' => $this->editor()->id]);

        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])
            ->fillForm(['title' => 'ویرایش مدیر'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('ویرایش مدیر', $post->refresh()->title);
    }

    public function test_bulk_delete_skips_posts_the_user_may_not_delete(): void
    {
        $editor = $this->editor();
        $own = $this->makePost(['author_id' => $editor->id]);
        $others = $this->makePost(['author_id' => $this->admin()->id]);
        $obsidian = $this->obsidianPost(['author_id' => $editor->id]);
        $this->actingAs($editor);

        Livewire::test(ListPosts::class)
            ->callTableBulkAction('delete', [$own, $others, $obsidian]);

        $this->assertSoftDeleted($own);
        $this->assertNotSoftDeleted($others);
        $this->assertNotSoftDeleted($obsidian);
    }

    public function test_only_admins_force_delete(): void
    {
        $editor = $this->editor();
        $own = $this->makePost(['author_id' => $editor->id]);
        $own->delete();
        $this->actingAs($editor);

        Livewire::test(ListPosts::class)
            ->filterTable('trashed', true)
            ->assertTableActionVisible('restore', $own)
            ->assertTableActionHidden('forceDelete', $own);

        $this->actingAs($this->admin());
        Livewire::test(ListPosts::class)
            ->filterTable('trashed', true)
            ->callTableAction('forceDelete', $own);

        $this->assertNull(Post::withTrashed()->find($own->id));
    }
}
