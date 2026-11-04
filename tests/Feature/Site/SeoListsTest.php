<?php

namespace Tests\Feature\Site;

class SeoListsTest extends SiteTestCase
{
    public function test_lists_in_both_languages_link_to_each_other(): void
    {
        $this->get('/tags')->assertOk()
            ->assertSee('<link rel="alternate" hreflang="fa" href="https://blog.test/tags">', false)
            ->assertSee('<link rel="alternate" hreflang="en" href="https://blog.test/en/tags">', false)
            ->assertSee('<link rel="alternate" hreflang="x-default" href="https://blog.test/tags">', false)
            ->assertSee('class="tool lang-switch" href="https://blog.test/en/tags"', false);
        $this->get('/en/tags')->assertOk()
            ->assertSee('<link rel="canonical" href="https://blog.test/en/tags">', false)
            ->assertSee('<link rel="alternate" hreflang="fa" href="https://blog.test/tags">', false)
            ->assertSee('class="tool lang-switch" href="https://blog.test/tags"', false);
        $this->get('/en/archives')->assertSee('hreflang="fa" href="https://blog.test/archives"', false);
        $this->get('/en/search')->assertSee('hreflang="fa" href="https://blog.test/search"', false);
    }

    public function test_tag_pages_have_only_their_language_and_a_feed(): void
    {
        $this->get('/tags/'.rawurlencode('فلسفه'))->assertOk()
            ->assertDontSee('rel="alternate" hreflang', false)
            ->assertSee('<link rel="alternate" type="application/rss+xml" href="https://blog.test/tags/فلسفه/index.xml"', false)
            ->assertSee('<p class="list-feed"><a href="/tags/فلسفه/index.xml">', false)
            ->assertSee('class="tool lang-switch" href="https://blog.test/en"', false);
    }
}
