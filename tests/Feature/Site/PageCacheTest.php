<?php

namespace Tests\Feature\Site;

use App\Models\Post;

class PageCacheTest extends SiteTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        app()->detectEnvironment(fn () => 'production');
    }

    public function test_second_visit_is_served_from_the_cache_until_an_edit(): void
    {
        $this->get('/')->assertOk()
            ->assertHeader('X-Page-Cache', 'miss')
            ->assertHeader('Cache-Control', 'max-age=300, public');
        $this->get('/')->assertOk()
            ->assertHeader('X-Page-Cache', 'hit')
            ->assertHeader('Cache-Control', 'max-age=300, public')
            ->assertSee('سلام');

        Post::query()->where('slug', 'hello')->first()->update(['title' => 'درود']);

        $this->get('/')->assertOk()->assertHeader('X-Page-Cache', 'miss')->assertSee('درود');
    }

    public function test_the_cache_key_ignores_the_host_header(): void
    {
        $this->get('/tags')->assertHeader('X-Page-Cache', 'miss');
        $this->get('http://evil.test/tags')->assertHeader('X-Page-Cache', 'hit')
            ->assertSee('https://blog.test/tags', false)->assertDontSee('evil.test');
    }

    public function test_only_valid_page_numbers_and_successful_pages_are_cached(): void
    {
        $this->get('/posts?page=abc')->assertNotFound()->assertHeaderMissing('X-Page-Cache');
        $this->get('/posts?page=abc')->assertNotFound()->assertHeaderMissing('X-Page-Cache');
        $this->get('/posts?utm=x')->assertOk()->assertHeaderMissing('X-Page-Cache');
        $this->get('/no-such-page')->assertNotFound()->assertHeaderMissing('X-Page-Cache');
        $this->get('/no-such-page')->assertNotFound()->assertHeaderMissing('X-Page-Cache');
        $this->get('/?page=2')->assertStatus(301)->assertHeaderMissing('X-Page-Cache');

        $this->get('/posts?page=1')->assertOk()->assertHeader('X-Page-Cache', 'miss');
        $this->get('/posts?page=1')->assertOk()->assertHeader('X-Page-Cache', 'hit');
    }
}
