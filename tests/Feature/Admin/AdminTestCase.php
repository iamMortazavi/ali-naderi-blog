<?php

namespace Tests\Feature\Admin;

use App\Enums\PostSource;
use App\Enums\PostStatus;
use App\Enums\UserRole;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class AdminTestCase extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }

    protected function editor(): User
    {
        return User::factory()->create(['role' => UserRole::Editor]);
    }

    protected function makePost(array $attributes = []): Post
    {
        static $n = 0;
        $n++;

        return Post::create($attributes + [
            'locale' => 'fa', 'slug' => "post-{$n}", 'title' => "نوشته {$n}", 'body' => 'متن',
            'status' => PostStatus::Published, 'published_at' => now()->subHour(), 'source' => PostSource::Panel,
        ]);
    }

    protected function obsidianPost(array $attributes = []): Post
    {
        return $this->makePost($attributes + ['source' => PostSource::Obsidian, 'source_path' => 'posts/note.md']);
    }
}
