<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Site\Concerns\ListsPosts;
use App\Models\Post;
use App\Support\Locales;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The list of all posts and the single post page. */
class PostController extends Controller
{
    use ListsPosts;

    public function index(Request $request): View
    {
        $locale = app()->getLocale();
        $posts = $this->paginate($this->livePosts($locale)->newestFirst(), $request, $locale, '/posts');

        return view('site.list', [
            'posts' => $posts,
            'heading' => __('site.posts'),
            'count' => $posts->total(),
            'seo' => Seo::list($locale, __('site.posts'), Locales::path($locale, '/posts'))
                ->inEveryLanguage('/posts')
                ->forPage($posts->currentPage()),
        ]);
    }

    public function show(string $slug): View
    {
        $locale = app()->getLocale();
        $post = Post::query()->live()->locale($locale)->where('slug', $slug)
            ->with(['tags', 'series', 'author:id,name'])->firstOrFail();

        [$newer, $older] = $post->neighbours();
        $translations = $post->translations();
        $related = $post->tags->isEmpty() ? collect() : $this->livePosts($locale)->whereKeyNot($post->id)
            ->whereHas('tags', fn ($q) => $q->whereIn('tags.id', $post->tags->modelKeys()))
            ->newestFirst()->limit(3)->get();

        return view('site.post', [
            'post' => $post,
            'translations' => $translations,
            'newer' => $newer,
            'older' => $older,
            'related' => $related,
            'cover' => $post->coverImage(),
            'video' => $post->videoEmbedUrl(),
            'seo' => Seo::post($post, $translations),
        ]);
    }
}
