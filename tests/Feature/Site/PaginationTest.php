<?php

namespace Tests\Feature\Site;

class PaginationTest extends SiteTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['blog.per_page' => 2, 'blog.home_posts' => 2]);
    }

    public function test_home_shows_latest_posts_without_pagination(): void
    {
        $this->addPosts(3);
        $this->get('/')->assertOk()
            ->assertSee('سلام')->assertSee('Extra 1')->assertDontSee('Extra 2')
            ->assertDontSee('class="pagination"', false);
    }

    public function test_old_home_pages_redirect_to_the_post_list(): void
    {
        $this->get('/?page=2')->assertStatus(301)->assertRedirect('/posts?page=2');
        $this->get('/?page=1')->assertStatus(301)->assertRedirect('/posts');
        $this->get('/en?page=3')->assertStatus(301)->assertRedirect('/en/posts?page=3');
        $this->get('/?page=abc')->assertNotFound();
    }

    public function test_list_pages_have_canonical_page_links(): void
    {
        $this->addPosts(3); // 4 Persian posts → 2 pages

        $this->get('/posts')->assertOk()
            ->assertSee('<link rel="canonical" href="https://blog.test/posts">', false)
            ->assertSee('href="https://blog.test/posts?page=2" rel="next"', false)
            ->assertSee('<link rel="alternate" hreflang="en" href="https://blog.test/en/posts">', false);

        $this->get('/posts?page=2')->assertOk()
            ->assertSee('<title>نوشته‌ها – صفحه ۲ | وبلاگ علی</title>', false)
            ->assertSee('<link rel="canonical" href="https://blog.test/posts?page=2">', false)
            ->assertSee('href="https://blog.test/posts" rel="prev"', false)
            ->assertDontSee('?page=1', false)
            ->assertDontSee('hreflang="en" href="https://blog.test/en/posts"', false)
            ->assertSee('class="tool lang-switch" href="https://blog.test/en/posts"', false);
    }

    public function test_pages_beyond_the_last_or_invalid_numbers_are_not_found(): void
    {
        $this->addPosts(3);
        $this->get('/posts?page=3')->assertNotFound();
        $this->get('/posts?page=abc')->assertNotFound();
        $this->get('/posts?page=0')->assertNotFound();
        $this->get('/posts?page=-1')->assertNotFound();
        $this->get('/posts?page[]=2')->assertNotFound();
        $this->get('/tags/'.rawurlencode('فلسفه').'?page=2')->assertNotFound();
        $this->get('/en/posts?page=2')->assertNotFound();
    }

    public function test_english_page_titles(): void
    {
        $this->addPosts(3, 'en');
        $this->get('/en/posts?page=2')->assertOk()->assertSee('<title>Posts – Page 2 | Ali</title>', false);
    }
}
