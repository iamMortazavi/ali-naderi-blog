<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\Settings;
use App\Support\SiteSettings;
use Livewire\Livewire;

class SettingsTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        SiteSettings::put('site', ['url' => 'https://blog.test', 'title_fa' => 'وبلاگ', 'title_en' => 'Blog']);
        SiteSettings::put('author', ['name_fa' => 'علی', 'name_en' => 'Ali']);
    }

    public function test_settings_are_saved(): void
    {
        $this->actingAs($this->admin());

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

    public function test_saving_keeps_hidden_sync_fields(): void
    {
        SiteSettings::put('sync', [
            'driver' => 'github', 'github_repo' => 'owner/vault', 'github_branch' => 'notes',
            'local_path' => '', 'posts_folder' => 'blog',
        ]);
        $this->actingAs($this->admin());

        // Switch the import off: the GitHub fields are hidden and not sent, but must not be wiped.
        Livewire::test(Settings::class)
            ->set('data.sync.driver', 'none')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('none', SiteSettings::get('sync.driver'));
        $this->assertSame('owner/vault', SiteSettings::get('sync.github_repo'));
        $this->assertSame('notes', SiteSettings::get('sync.github_branch'));
        $this->assertSame('blog', SiteSettings::get('sync.posts_folder'));
    }

    public function test_local_vault_folder_must_be_inside_the_vault_root(): void
    {
        config(['blog.vault_root' => base_path('example-vault')]);
        $this->actingAs($this->admin());

        foreach (['/', base_path('app'), 'example-vault/../app', 'does-not-exist'] as $path) {
            Livewire::test(Settings::class)
                ->set('data.sync.driver', 'local')
                ->set('data.sync.local_path', $path)
                ->call('save')
                ->assertHasErrors(['data.sync.local_path']);
        }
        $this->assertSame('none', SiteSettings::get('sync.driver'));

        Livewire::test(Settings::class)
            ->set('data.sync.driver', 'local')
            ->set('data.sync.local_path', 'example-vault')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame('example-vault', SiteSettings::get('sync.local_path'));
    }

    public function test_connection_test_reports_the_vault_message(): void
    {
        config(['blog.vault_root' => base_path('example-vault')]);
        $this->actingAs($this->admin());

        Livewire::test(Settings::class)
            ->set('data.sync.driver', 'local')
            ->set('data.sync.local_path', 'example-vault')
            ->call('testSync')
            ->assertNotified('اتصال برقرار است');
    }
}
