<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Pages\Pages\ManagePages;
use App\Filament\Resources\Redirects\Pages\ManageRedirects;
use App\Filament\Resources\Series\Pages\ManageSeries;
use App\Filament\Resources\Tags\Pages\ManageTags;
use App\Models\Redirect;
use App\Models\Series;
use App\Models\Tag;
use Livewire\Livewire;

class TaxonomyAndRedirectTest extends AdminTestCase
{
    public function test_tag_slug_from_the_name_must_be_unique_per_language(): void
    {
        $this->actingAs($this->admin());
        Tag::create(['locale' => 'en', 'name' => 'php']);

        Livewire::test(ManageTags::class)
            ->callAction('create', data: ['name' => 'PHP', 'locale' => 'en'])
            ->assertHasActionErrors(['slug' => 'unique']);

        // The same name in the other language is fine.
        Livewire::test(ManageTags::class)
            ->callAction('create', data: ['name' => 'PHP', 'locale' => 'fa'])
            ->assertHasNoActionErrors();

        $this->assertSame(['en' => 'php', 'fa' => 'php'], Tag::query()->orderBy('locale')->pluck('slug', 'locale')->all());
    }

    public function test_tag_slug_format(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(ManageTags::class)
            ->callAction('create', data: ['name' => 'x', 'slug' => 'a--b', 'locale' => 'fa'])
            ->assertHasActionErrors(['slug' => 'regex']);

        Livewire::test(ManageTags::class)
            ->callAction('create', data: ['name' => 'هوش مصنوعی', 'locale' => 'fa'])
            ->assertHasNoActionErrors();
        $this->assertTrue(Tag::query()->where('slug', 'هوش-مصنوعی')->exists());
    }

    public function test_series_slug_must_be_unique_per_language(): void
    {
        $this->actingAs($this->admin());
        Series::create(['locale' => 'fa', 'name' => 'Laravel']);

        Livewire::test(ManageSeries::class)
            ->callAction('create', data: ['name' => 'LARAVEL', 'locale' => 'fa'])
            ->assertHasActionErrors(['slug' => 'unique']);
    }

    public function test_editors_cannot_delete_tags(): void
    {
        $tag = Tag::create(['locale' => 'fa', 'name' => 'x']);
        $this->actingAs($this->editor());

        Livewire::test(ManageTags::class)
            ->assertTableActionVisible('edit', $tag)
            ->assertTableActionHidden('delete', $tag);
    }

    public function test_redirect_from_path_is_normalised_before_the_unique_check(): void
    {
        $this->actingAs($this->admin());
        Redirect::create(['from_path' => '/posts/old', 'to_path' => '/posts/new']);

        Livewire::test(ManageRedirects::class)
            ->callAction('create', data: ['from_path' => '/posts/old/', 'to_path' => '/posts/other', 'status_code' => 301])
            ->assertHasActionErrors(['from_path' => 'unique']);

        Livewire::test(ManageRedirects::class)
            ->callAction('create', data: ['from_path' => 'https://example.com/posts/older/', 'to_path' => '/posts/new', 'status_code' => 301])
            ->assertHasNoActionErrors();
        $this->assertTrue(Redirect::query()->where('from_path', '/posts/older')->exists());
    }

    public function test_redirect_target_must_be_a_site_path(): void
    {
        $this->actingAs($this->admin());

        foreach (['//evil.example', '/\\evil.example', 'https://evil.example/x', 'posts/x'] as $target) {
            Livewire::test(ManageRedirects::class)
                ->callAction('create', data: ['from_path' => '/a', 'to_path' => $target, 'status_code' => 301])
                ->assertHasActionErrors(['to_path' => 'regex']);
        }
        $this->assertSame(0, Redirect::query()->count());
    }

    public function test_page_slug_cannot_be_a_reserved_address(): void
    {
        $this->actingAs($this->admin());

        foreach (['posts', 'uploads', 'livewire'] as $slug) {
            Livewire::test(ManagePages::class)
                ->callAction('create', data: ['title' => 'x', 'locale' => 'fa', 'slug' => $slug, 'body' => 'x'])
                ->assertHasActionErrors(['slug' => 'not_in']);
        }

        Livewire::test(ManagePages::class)
            ->callAction('create', data: ['title' => 'درباره', 'locale' => 'fa', 'slug' => 'about', 'body' => 'x'])
            ->assertHasNoActionErrors();
    }
}
