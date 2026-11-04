<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Site\Concerns\EncodesUrls;
use App\Models\Post;
use App\Models\Tag;
use App\Support\Locales;
use App\Support\SiteSettings;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

/** Full-text RSS: one feed per language and one per tag. */
class FeedController extends Controller
{
    use EncodesUrls;

    public function home(): Response
    {
        $locale = app()->getLocale();
        $posts = Post::query()->live()->locale($locale)->newestFirst()->with('tags')
            ->limit((int) config('blog.feed_items', 30))->get();

        return $this->rss($locale, SiteSettings::title($locale), Locales::url($locale), Locales::url($locale, '/index.xml'), $posts);
    }

    public function tag(string $slug): Response
    {
        $locale = app()->getLocale();
        $tag = Tag::query()->where('locale', $locale)->where('slug', $slug)->firstOrFail();
        $posts = $tag->posts()->live()->newestFirst()->with('tags')
            ->limit((int) config('blog.feed_items', 30))->get();

        return $this->rss(
            $locale,
            '#'.$tag->name.' | '.SiteSettings::title($locale),
            $this->encodeUrl(Locales::url($locale, '/tags/'.$tag->slug)),
            $this->encodeUrl(Locales::url($locale, '/tags/'.$tag->slug.'/index.xml')),
            $posts,
        );
    }

    /** @param  Collection<int,Post>  $posts */
    private function rss(string $locale, string $title, string $link, string $self, Collection $posts): Response
    {
        $updated = $posts->map(fn (Post $post) => $post->lastModified())->filter()->max();

        return response()->view('site.feed', [
            'locale' => $locale,
            'title' => $title,
            'link' => $link,
            'self' => $self,
            'posts' => $posts,
            'updated' => $updated,
            'description' => (string) SiteSettings::get('author.bio_'.$locale),
            'author' => SiteSettings::authorName($locale),
        ])->header('Content-Type', 'application/rss+xml; charset=utf-8');
    }
}
