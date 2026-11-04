<?php

namespace Tests\Feature\Obsidian;

use App\Services\Obsidian\GitHubVault;
use App\Services\Obsidian\LocalVault;
use App\Services\Obsidian\VaultFactory;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

class VaultFactoryTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir().'/vault-root-'.uniqid();
        File::makeDirectory($this->root.'/vault', recursive: true);
        File::makeDirectory($this->root.'-outside', recursive: true);
        config(['blog.vault_root' => $this->root]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        File::deleteDirectory($this->root.'-outside');
        parent::tearDown();
    }

    public function test_sync_off_gives_no_vault(): void
    {
        SiteSettings::put('sync', ['driver' => 'none']);

        $this->assertNull(VaultFactory::fromSettings());
    }

    public function test_a_local_folder_inside_the_vault_root_is_allowed(): void
    {
        SiteSettings::put('sync', ['driver' => 'local', 'local_path' => $this->root.'/vault']);

        $this->assertInstanceOf(LocalVault::class, VaultFactory::fromSettings());
    }

    public function test_a_local_folder_outside_the_vault_root_is_refused(): void
    {
        foreach ([$this->root.'-outside', $this->root.'/vault/../../'.basename($this->root).'-outside', '/etc'] as $path) {
            SiteSettings::put('sync', ['driver' => 'local', 'local_path' => $path]);
            try {
                VaultFactory::fromSettings();
                $this->fail("{$path} was allowed");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('must be inside', $e->getMessage());
            }
        }
    }

    public function test_github_needs_a_repository(): void
    {
        SiteSettings::put('sync', ['driver' => 'github', 'github_repo' => '']);
        $this->expectExceptionMessage('Set the GitHub repository');
        VaultFactory::fromSettings();
    }

    public function test_github_vault_from_settings(): void
    {
        SiteSettings::put('sync', ['driver' => 'github', 'github_repo' => 'me/vault', 'github_branch' => '']);

        $this->assertInstanceOf(GitHubVault::class, VaultFactory::fromSettings());
    }
}
