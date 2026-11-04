<?php

namespace Tests\Feature;

use App\Services\Content\MarkdownRenderer;
use Tests\TestCase;

class MarkdownRendererTest extends TestCase
{
    private function html(string $markdown, string $locale = 'fa'): string
    {
        return app(MarkdownRenderer::class)->render($markdown, 'posts/fa/x', $locale)->html;
    }

    public function test_callouts(): void
    {
        $html = $this->html("> [!tip] یک نکته\n> متن نکته\n\n> [!warning]- جزئیات\n> پنهان\n\n> [!note]\n> بدون عنوان");
        $this->assertStringContainsString('<div class="callout callout-tip" role="note"><p class="callout-title">یک نکته</p>', $html);
        $this->assertStringContainsString('متن نکته', $html);
        $this->assertStringContainsString('<details class="callout callout-warning"><summary class="callout-title">جزئیات</summary>', $html);
        $this->assertStringContainsString('<p class="callout-title">یادداشت</p>', $html);
        $this->assertStringNotContainsString('[!', $html);
    }

    public function test_math_highlight_and_code(): void
    {
        $html = $this->html("مساحت \$A = \\pi r^2\$ و ==مهم==\n\n\$\$\n\\frac{a_1}{b_2}\n\$\$\n\n```python\ndef a():\n    return \"\$x\$ ==y==\"\n```\n");
        $this->assertStringContainsString('<span class="math math-inline" dir="ltr">A = \pi r^2</span>', $html);
        $this->assertStringContainsString('<div class="math math-block" dir="ltr">\frac{a_1}{b_2}</div>', $html);
        $this->assertStringContainsString('<mark>مهم</mark>', $html);
        $this->assertStringContainsString('<pre data-lang="python"', $html);
        $this->assertStringContainsString("\n    <span class=\"hl-keyword\">return</span>", $html, 'code keeps its indentation');
        $this->assertStringContainsString('$x$ ==y==', html_entity_decode(strip_tags($html)), 'code is not treated as math or highlight');
    }

    public function test_headings_get_persian_ids_and_toc(): void
    {
        $rendered = app(MarkdownRenderer::class)->render("## چرا می‌نویسم\n\n### Part One!\n\n## چرا می‌نویسم");
        $this->assertSame(['چرا-مینویسم', 'part-one', 'چرا-مینویسم-1'], array_column($rendered->headings, 'id'));
        $this->assertStringContainsString('<h2 id="چرا-مینویسم">', $rendered->html);
    }

    public function test_media_tables_and_links(): void
    {
        $html = $this->html("![](episode.mp3)\n\n| a | b |\n| - | - |\n| 1 | 2 |\n\n[site](https://example.com)");
        $this->assertStringContainsString('<audio class="inline-audio" controls="controls" preload="none" src="/uploads/posts/fa/x/episode.mp3">', $html);
        $this->assertStringContainsString('<div class="table-wrap" tabindex="0"><table>', $html);
        $this->assertStringContainsString('rel="noopener"', $html);
    }
}
