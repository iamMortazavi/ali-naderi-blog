<?php

namespace App\Services\Content;

use App\Support\Persian;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Storage;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\Footnote\FootnoteExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;
use Tempest\Highlight\CommonMark\CodeBlockRenderer;

/**
 * Markdown → HTML for posts and pages, with what Obsidian writers expect:
 * tables, footnotes, task lists, ==highlight==, $math$ / $$math$$, callouts (> [!tip] Title),
 * highlighted code, heading anchors and a table of contents, responsive images,
 * and audio/video players for ![](episode.mp3). The final HTML is sanitised (HtmlPolicy).
 */
class MarkdownRenderer
{
    private const CALLOUT_GROUPS = [
        'note' => 'info', 'info' => 'info', 'todo' => 'info', 'abstract' => 'info', 'summary' => 'info', 'tldr' => 'info',
        'tip' => 'tip', 'hint' => 'tip', 'important' => 'tip', 'success' => 'tip', 'check' => 'tip', 'done' => 'tip',
        'question' => 'question', 'help' => 'question', 'faq' => 'question', 'example' => 'question',
        'quote' => 'quote', 'cite' => 'quote',
        'warning' => 'warning', 'caution' => 'warning', 'attention' => 'warning',
        'failure' => 'danger', 'fail' => 'danger', 'missing' => 'danger', 'danger' => 'danger', 'error' => 'danger', 'bug' => 'danger',
    ];

    private const AUDIO = ['mp3', 'm4a', 'ogg', 'oga', 'wav'];

    private const VIDEO = ['mp4', 'webm'];

    private MarkdownConverter $converter;

    public function __construct(
        private readonly ImageVariants $variants,
        private readonly HtmlPolicy $policy,
    ) {
        $environment = new Environment([
            'html_input' => 'allow', // the authors' own notes; Obsidian users write small bits of HTML
            'allow_unsafe_links' => false,
            'footnote' => ['backref_symbol' => '↩'],
        ]);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);
        $environment->addExtension(new FootnoteExtension);
        $environment->addRenderer(FencedCode::class, new CodeBlockRenderer, 10);
        $this->converter = new MarkdownConverter($environment);
    }

    /** @param string $mediaDirectory folder on the public disk that relative image paths point into */
    public function render(string $markdown, string $mediaDirectory = '', string $locale = 'fa'): Rendered
    {
        // $$block$$ and $inline$ math are kept away from the Markdown parser and rendered in the browser.
        $math = new MathExtractor;
        $markdown = Prose::map($markdown, fn (string $text) => preg_replace(
            '/==(?=\S)(.+?)(?<=\S)==/u', '<mark>$1</mark>', $math->extract($text),
        ));

        $html = $math->restore((string) $this->converter->convert($markdown));

        return $this->enhance($html, $mediaDirectory, $locale);
    }

    private function enhance(string $html, string $mediaDirectory, string $locale): Rendered
    {
        if (trim($html) === '') {
            return new Rendered('', '', []);
        }
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?><div id="__root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);
        $root = $dom->getElementById('__root');

        $headings = $this->headings($dom, $xpath);
        $this->callouts($dom, $xpath, $locale);
        $this->images($dom, $xpath, $mediaDirectory);
        $this->tables($dom, $xpath, $locale);
        $this->links($xpath);
        $this->footnotes($xpath, $locale);
        $this->inputs($xpath);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }
        $out = $this->policy->sanitize($out);
        $plain = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(
            preg_replace('/<(pre|div class="math[^"]*")[^>]*>.*?<\/(pre|div)>/s', ' ', $out)
        ), ENT_QUOTES)));

        return new Rendered($out, $plain, $headings);
    }

    /** Heading ids like GitHub's (Persian letters kept, half-spaces dropped), an anchor link, and the TOC entries. */
    private function headings(DOMDocument $dom, DOMXPath $xpath): array
    {
        $used = [];
        $toc = [];
        foreach ($xpath->query('//h2|//h3|//h4') as $h) {
            /** @var DOMElement $h */
            $text = trim($h->textContent);
            $id = self::anchor($text) ?: 'section';
            $base = $id;
            for ($n = 1; isset($used[$id]); $n++) {
                $id = "{$base}-{$n}";
            }
            $used[$id] = true;
            $h->setAttribute('id', $id);
            $a = $dom->createElement('a');
            $a->setAttribute('class', 'heading-anchor');
            $a->setAttribute('href', '#'.$id);
            $a->setAttribute('aria-hidden', 'true');
            $a->setAttribute('tabindex', '-1');
            $h->appendChild($a);
            $toc[] = ['level' => (int) substr($h->nodeName, 1), 'id' => $id, 'text' => $text];
        }

        return $toc;
    }

    public static function anchor(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/[^\p{L}\p{N}\p{M} _-]/u', '', $text);

        return str_replace(' ', '-', $text);
    }

    /** > [!tip] Title  →  callout box. > [!warning]- Title → folded box. */
    private function callouts(DOMDocument $dom, DOMXPath $xpath, string $locale): void
    {
        foreach (iterator_to_array($xpath->query('//blockquote')) as $quote) {
            $first = $xpath->query('./p[1]', $quote)->item(0);
            if (! $first instanceof DOMElement || ! preg_match('/^\s*\[!([a-z-]+)\]([+-]?)[ \t]*([^\n]*)\n?/i', $first->textContent, $m)) {
                continue;
            }
            $type = strtolower($m[1]);
            $group = self::CALLOUT_GROUPS[$type] ?? 'info';
            $title = trim($m[3]) ?: __("callouts.{$type}", [], $locale);
            if ($title === "callouts.{$type}") {
                $title = ucfirst($type);
            }

            // Remove the marker line from the first paragraph, keeping the rest of its content.
            $this->stripFirstLine($first);

            $box = $dom->createElement($m[2] !== '' ? 'details' : 'div');
            $box->setAttribute('class', "callout callout-{$group}");
            if ($m[2] === '+') {
                $box->setAttribute('open', 'open');
            }
            if ($m[2] === '') {
                $box->setAttribute('role', 'note');
            }
            $head = $dom->createElement($m[2] !== '' ? 'summary' : 'p');
            $head->setAttribute('class', 'callout-title');
            $head->appendChild($dom->createTextNode($title));
            $body = $dom->createElement('div');
            $body->setAttribute('class', 'callout-body');
            while ($quote->firstChild) {
                $body->appendChild($quote->firstChild);
            }
            if (trim($first->textContent) === '' && $first->getElementsByTagName('*')->length === 0) {
                $body->removeChild($first);
            }
            $box->appendChild($head);
            $box->appendChild($body);
            $quote->parentNode->replaceChild($box, $quote);
        }
    }

    private function stripFirstLine(DOMElement $p): void
    {
        $node = $p->firstChild;
        while ($node) {
            if ($node->nodeType === XML_TEXT_NODE) {
                $pos = strpos($node->nodeValue, "\n");
                if ($pos === false) {
                    $next = $node->nextSibling;
                    $p->removeChild($node);
                    $node = $next;
                    if ($node && $node->nodeName === 'br') {
                        $next = $node->nextSibling;
                        $p->removeChild($node);
                        $node = $next;
                        break;
                    }

                    continue;
                }
                $node->nodeValue = substr($node->nodeValue, $pos + 1);
                break;
            }
            $next = $node->nextSibling;
            $p->removeChild($node);
            $node = $next;
        }
    }

    private function images(DOMDocument $dom, DOMXPath $xpath, string $mediaDirectory): void
    {
        $disk = Storage::disk('public');
        foreach (iterator_to_array($xpath->query('//img')) as $img) {
            /** @var DOMElement $img */
            $src = $img->getAttribute('src');
            $isLocal = $src !== '' && ! preg_match('#^(https?:)?//|^/|^data:#i', $src);
            $path = $isLocal ? trim($mediaDirectory.'/'.rawurldecode($src), '/') : null;
            $url = $path ? $disk->url($path) : $src;
            $ext = strtolower(pathinfo(parse_url($src, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));

            if (in_array($ext, self::AUDIO, true) || in_array($ext, self::VIDEO, true)) {
                $player = $dom->createElement(in_array($ext, self::AUDIO, true) ? 'audio' : 'video');
                $player->setAttribute('class', in_array($ext, self::AUDIO, true) ? 'inline-audio' : 'inline-video');
                $player->setAttribute('controls', 'controls');
                $player->setAttribute('preload', in_array($ext, self::AUDIO, true) ? 'none' : 'metadata');
                $player->setAttribute('src', $url);
                $this->replaceInline($img, $player);

                continue;
            }
            if ($ext === 'pdf') {
                $a = $dom->createElement('a', $img->getAttribute('alt') ?: basename($src));
                $a->setAttribute('href', $url);
                $img->parentNode->replaceChild($a, $img);

                continue;
            }

            $img->setAttribute('src', $url);
            $img->setAttribute('loading', 'lazy');
            $img->setAttribute('decoding', 'async');
            if ($path && $disk->exists($path)) {
                $variants = $this->variants->for($path);
                if ($variants) {
                    $img->setAttribute('src', $variants['src']);
                    $img->setAttribute('srcset', $variants['srcset']);
                    $img->setAttribute('sizes', '(max-width: 760px) 100vw, 720px');
                    $img->setAttribute('width', (string) $variants['width']);
                    $img->setAttribute('height', (string) $variants['height']);
                }
            }
        }
    }

    /** Put a block element where an inline image was; lift it out of an otherwise empty paragraph. */
    private function replaceInline(DOMElement $img, DOMElement $block): void
    {
        $parent = $img->parentNode;
        if ($parent->nodeName === 'p' && trim($parent->textContent) === '' && $parent->childNodes->length === 1) {
            $parent->parentNode->replaceChild($block, $parent);
        } else {
            $parent->replaceChild($block, $img);
        }
    }

    /** Wide tables scroll on their own; the scroll area is focusable and named for screen readers. */
    private function tables(DOMDocument $dom, DOMXPath $xpath, string $locale): void
    {
        foreach (iterator_to_array($xpath->query('//table')) as $table) {
            $wrap = $dom->createElement('div');
            $wrap->setAttribute('class', 'table-wrap');
            $wrap->setAttribute('tabindex', '0');
            $wrap->setAttribute('role', 'region');
            $wrap->setAttribute('aria-label', __('site.table', [], $locale));
            $table->parentNode->replaceChild($wrap, $table);
            $wrap->appendChild($table);
        }
    }

    private function links(DOMXPath $xpath): void
    {
        foreach ($xpath->query('//a[starts-with(@href, "http")]') as $a) {
            /** @var DOMElement $a */
            $a->setAttribute('rel', 'noopener');
            $a->setAttribute('class', trim($a->getAttribute('class').' external'));
        }
    }

    /** Footnote numbers in the text and in the notes list use the page's digits. */
    private function footnotes(DOMXPath $xpath, string $locale): void
    {
        if ($locale !== 'fa') {
            return;
        }
        foreach ($xpath->query('//a[contains(@class, "footnote-ref") or contains(@class, "footnote-backref")]//text()') as $text) {
            $text->nodeValue = Persian::digits($text->nodeValue);
        }
    }

    /** Task-list checkboxes are the only form controls a post may show. */
    private function inputs(DOMXPath $xpath): void
    {
        foreach (iterator_to_array($xpath->query('//input')) as $input) {
            /** @var DOMElement $input */
            if (strtolower($input->getAttribute('type')) !== 'checkbox') {
                $input->parentNode->removeChild($input);

                continue;
            }
            $input->setAttribute('disabled', '');
        }
    }
}
