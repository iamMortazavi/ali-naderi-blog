<?php

namespace Tests\Feature\Site;

use App\Models\Post;
use App\Models\Redirect;
use Illuminate\Support\Facades\DB;

class RedirectTest extends SiteTestCase
{
    public function test_slug_change_redirects_the_old_address(): void
    {
        Post::query()->where('slug', 'hello')->first()->update(['slug' => 'hello-world']);

        $this->get('/posts/hello')->assertRedirect('/posts/hello-world')->assertStatus(301);
    }

    public function test_persian_paths_are_matched_decoded(): void
    {
        // Inserted directly: the decoded Persian path is what the redirects table should hold.
        DB::table('redirects')->insert(['from_path' => '/tags/قدیمی', 'to_path' => '/tags/فلسفه', 'status_code' => 301, 'hits' => 0]);

        $response = $this->get('/tags/'.rawurlencode('قدیمی'))->assertStatus(301);
        $this->assertStringEndsWith('/tags/'.rawurlencode('فلسفه'), $response->headers->get('Location'));
        $this->assertSame(1, Redirect::query()->value('hits'));

        Redirect::create(['from_path' => '/'.rawurlencode('قدیم'), 'to_path' => '/about']);
        $this->get('/'.rawurlencode('قدیم'))->assertRedirect('/about');
    }

    public function test_a_redirect_to_the_same_address_never_loops(): void
    {
        Redirect::create(['from_path' => '/gone', 'to_path' => '/gone/']);
        Redirect::create(['from_path' => '/self', 'to_path' => 'https://blog.test/self']);
        DB::table('redirects')->insert(['from_path' => '/خود', 'to_path' => '/خود/', 'status_code' => 301, 'hits' => 0]);

        $this->get('/gone')->assertNotFound();
        $this->get('/self')->assertNotFound();
        $this->get('/'.rawurlencode('خود'))->assertNotFound();
    }
}
