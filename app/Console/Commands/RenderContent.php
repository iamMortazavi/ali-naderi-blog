<?php

namespace App\Console\Commands;

use App\Models\Page;
use App\Models\Post;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Render every post and page again from its Markdown: after an update that changes how HTML is
 * made (sanitizing, footnotes, image sizes, excerpts). Run by blog:install after migrating.
 */
#[Signature('blog:render')]
#[Description('Render all posts and pages again from their Markdown')]
class RenderContent extends Command
{
    public function handle(): int
    {
        $posts = 0;
        Post::withTrashed()->lazyById(50)->each(function (Post $post) use (&$posts) {
            $post->body_html = null; // the saving hook renders again when body_html is empty
            $post->save();
            $posts++;
        });
        $pages = 0;
        Page::query()->lazyById(50)->each(function (Page $page) use (&$pages) {
            $page->body_html = null;
            $page->save();
            $pages++;
        });
        $this->info("Rendered {$posts} post(s) and {$pages} page(s).");

        return self::SUCCESS;
    }
}
