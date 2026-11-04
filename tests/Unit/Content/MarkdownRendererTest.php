<?php

namespace Tests\Unit\Content;

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
        $html = $this->html("مساحت \$A = \\pi r^2 + 1\$ و ==مهم==\n\n\$\$\n\\frac{a_1}{b_2}\n\$\$\n\n```python\ndef a():\n    return \"\$x\$ ==y==\"\n```\n");
        $this->assertStringContainsString('<span class="math math-inline" dir="ltr">A = \pi r^2 + 1</span>', $html);
        $this->assertStringContainsString('<div class="math math-block" dir="ltr">\frac{a_1}{b_2}</div>', $html);
        $this->assertStringContainsString('<mark>مهم</mark>', $html);
        $this->assertStringContainsString('<pre data-lang="python" class="notranslate">', $html);
        $this->assertStringContainsString("\n    <span class=\"hl-keyword\">return</span>", $html, 'code keeps its indentation');
        $this->assertStringContainsString('$x$ ==y==', html_entity_decode(strip_tags($html)), 'code is not treated as math or highlight');
    }

    public function test_headings_get_persian_ids_and_toc(): void
    {
        $rendered = app(MarkdownRenderer::class)->render("## چرا می‌نویسم\n\n### Part One!\n\n## چرا می‌نویسم");
        $this->assertSame(['چرا-مینویسم', 'part-one', 'چرا-مینویسم-1'], array_column($rendered->headings, 'id'));
        $this->assertStringContainsString('<h2 id="چرا-مینویسم">', $rendered->html);
        $this->assertStringContainsString('class="heading-anchor"', $rendered->html);
    }

    public function test_media_tables_and_links(): void
    {
        $html = $this->html("![](episode.mp3)\n\n| a | b |\n| - | - |\n| 1 | 2 |\n\n[site](https://example.com)");
        $this->assertStringContainsString('<audio class="inline-audio" controls="controls" preload="none" src="/uploads/posts/fa/x/episode.mp3">', $html);
        $this->assertStringContainsString('<div class="table-wrap" tabindex="0" role="region" aria-label="جدول"><table>', $html);
        $this->assertStringContainsString('rel="noopener"', $html);

        $this->assertStringContainsString('aria-label="Table"', $this->html("| a |\n| - |\n| 1 |", 'en'));
    }

    public function test_footnote_numbers_follow_the_language(): void
    {
        $markdown = "Text[^1] and more[^2].\n\n[^1]: First.\n[^2]: Second.";
        $fa = $this->html($markdown);
        $this->assertStringContainsString('role="doc-noteref">۱</a>', $fa);
        $this->assertStringContainsString('role="doc-noteref">۲</a>', $fa);
        $this->assertStringContainsString('id="fn:1"', $fa);
        $this->assertStringContainsString('class="footnote-backref"', $fa);

        $this->assertStringContainsString('role="doc-noteref">1</a>', $this->html($markdown, 'en'));
    }

    public function test_sanitizer_removes_scripts_handlers_and_dangerous_links(): void
    {
        $html = $this->html(implode("\n\n", [
            '<script>alert(1)</script>',
            '<img src="a.png" onerror="alert(2)">',
            '<p onclick="alert(3)" style="color:red">click</p>',
            '[bad](javascript:alert(4)) <a href="JaVaScRiPt:alert(5)">bad2</a> <a href="data:text/html,x">bad3</a>',
            '<iframe src="https://evil.example"></iframe><object data="x.swf"></object>',
            '<svg><script>alert(6)</script></svg><style>body{display:none}</style>',
            '<input type="text" value="x"> <form action="/x"><button>go</button></form>',
            '<a href="mailto:me@example.com">mail</a> <a href="/posts/x">local</a> <a href="#fn">frag</a>',
        ]));

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringNotContainsString('<object', $html);
        $this->assertStringNotContainsString('<style', $html);
        $this->assertStringNotContainsString('<svg', $html);
        $this->assertStringNotContainsString('<form', $html);
        $this->assertStringNotContainsString('type="text"', $html);
        $this->assertDoesNotMatchRegularExpression('/\son[a-z]+=/i', $html);
        $this->assertStringNotContainsString('style=', $html);
        $this->assertStringNotContainsStringIgnoringCase('javascript:', $html);
        $this->assertStringNotContainsString('data:text', $html);
        $this->assertStringContainsString('href="mailto:me@example.com"', $html);
        $this->assertStringContainsString('href="/posts/x"', $html);
        $this->assertStringContainsString('href="#fn"', $html);
        $this->assertStringContainsString('<p>click</p>', $html);
    }

    public function test_sanitizer_keeps_everything_the_renderer_makes(): void
    {
        $html = $this->html(implode("\n\n", [
            '## عنوان',
            "> [!warning]+ باز\n> متن",
            'فرمول $a+b$ و پانویس[^1]',
            "```php\n\$a = 1;\n```",
            "| a | b |\n|:-|-:|\n| 1 | 2 |",
            "- [x] done\n- [ ] todo",
            "3. three\n4. four",
            '<details><summary>بیشتر</summary>متن <mark>m</mark> <sup>2</sup> <del>d</del></details>',
            '<figure><img src="https://example.com/a.png" alt="a" width="10" height="10"><figcaption>c</figcaption></figure>',
            '<video controls src="https://example.com/v.mp4"></video>',
            '[^1]: یادداشت.',
        ]));

        $this->assertStringContainsString('<h2 id="عنوان">', $html);
        $this->assertStringContainsString('aria-hidden="true" tabindex="-1"', $html);
        $this->assertStringContainsString('<details class="callout callout-warning" open="open"><summary class="callout-title">باز</summary>', $html);
        $this->assertStringContainsString('<span class="math math-inline" dir="ltr">a+b</span>', $html);
        $this->assertStringContainsString('<pre data-lang="php" class="notranslate"><span class="hl-variable">', $html);
        $this->assertStringContainsString('<th align="left">a</th>', $html);
        $this->assertStringContainsString('<input checked="" disabled="" type="checkbox">', $html);
        $this->assertStringContainsString('<ol start="3">', $html);
        $this->assertStringContainsString('<details><summary>بیشتر</summary>متن <mark>m</mark> <sup>2</sup> <del>d</del></details>', $html);
        $this->assertStringContainsString('<figure><img src="https://example.com/a.png" alt="a" width="10" height="10" loading="lazy" decoding="async"><figcaption>c</figcaption></figure>', $html);
        $this->assertStringContainsString('<video controls="" src="https://example.com/v.mp4"></video>', $html);
        $this->assertStringContainsString('<div class="footnotes" role="doc-endnotes">', $html);
        $this->assertStringContainsString('<sup id="fnref:1"><a class="footnote-ref" href="#fn:1" role="doc-noteref">۱</a></sup>', $html);
    }
}
