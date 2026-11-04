<?php

namespace App\Services\Content;

use Dom\HTMLDocument;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerAction;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * What post and page HTML may contain once rendered: the elements and attributes the Markdown
 * renderer and the code highlighter produce, plus the small bits of HTML authors write by hand.
 * Scripts, styles, frames, event handlers (on*) and javascript: addresses never get through.
 */
class HtmlPolicy
{
    /** Attributes every allowed element may carry. */
    private const GLOBAL_ATTRIBUTES = [
        'class', 'id', 'dir', 'lang', 'title', 'role', 'tabindex',
        'aria-hidden', 'aria-label', 'aria-labelledby', 'aria-describedby',
    ];

    /** element → attributes it may carry besides the global ones */
    private const ELEMENTS = [
        'a' => ['href', 'rel', 'hreflang'],
        'abbr' => [], 'b' => [], 'bdi' => [], 'bdo' => [], 'br' => [], 'cite' => [], 'code' => [],
        'blockquote' => ['cite'],
        'caption' => [], 'col' => ['span'], 'colgroup' => ['span'],
        'table' => [], 'thead' => [], 'tbody' => [], 'tfoot' => [], 'tr' => [],
        'th' => ['colspan', 'rowspan', 'scope', 'align'],
        'td' => ['colspan', 'rowspan', 'align'],
        'dd' => [], 'dl' => [], 'dt' => [], 'dfn' => [],
        'del' => [], 'ins' => [], 's' => [], 'u' => [], 'mark' => [],
        'details' => ['open'], 'summary' => [],
        'div' => [], 'span' => [], 'p' => [], 'hr' => [],
        'em' => [], 'i' => [], 'strong' => [], 'small' => [], 'sub' => [], 'sup' => [],
        'figure' => [], 'figcaption' => [],
        'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => [],
        'img' => ['src', 'srcset', 'sizes', 'alt', 'width', 'height', 'loading', 'decoding'],
        'picture' => [],
        'source' => ['src', 'srcset', 'sizes', 'type', 'media'],
        'audio' => ['src', 'controls', 'preload'],
        'video' => ['src', 'controls', 'preload', 'poster', 'width', 'height'],
        'input' => ['type', 'checked', 'disabled'], // task-list checkboxes only (see MarkdownRenderer)
        'kbd' => [], 'samp' => [], 'var' => [], 'q' => ['cite'],
        'li' => [], 'ul' => [], 'ol' => ['start', 'type', 'reversed'],
        'pre' => ['data-lang'],
        'time' => ['datetime'],
    ];

    /** Removed together with everything inside them. Any other unknown element is unwrapped. */
    private const DROPPED = [
        'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'noscript',
        'template', 'textarea', 'select', 'option', 'button', 'form', 'svg', 'math', 'link', 'meta', 'base', 'title',
    ];

    private ?HtmlSanitizer $sanitizer = null;

    public function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }
        $this->sanitizer ??= new HtmlSanitizer($this->config());

        return $this->normalise($this->sanitizer->sanitize($html));
    }

    public function config(): HtmlSanitizerConfig
    {
        $config = (new HtmlSanitizerConfig)
            ->defaultAction(HtmlSanitizerAction::Block)
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            ->allowRelativeLinks()
            ->allowMediaSchemes(['http', 'https'])
            ->allowRelativeMedias()
            ->withMaxInputLength(-1);

        foreach (self::ELEMENTS as $element => $attributes) {
            $config = $config->allowElement($element, [...self::GLOBAL_ATTRIBUTES, ...$attributes]);
        }
        foreach (self::DROPPED as $element) {
            $config = $config->dropElement($element);
        }

        return $config;
    }

    /**
     * The sanitizer writes "=" or "+" in text as entities (&#61;). Parsing and serialising once more
     * gives plain HTML5 again, so stored HTML, summaries and search text read naturally.
     */
    private function normalise(string $html): string
    {
        $document = HTMLDocument::createFromString('<!DOCTYPE html><body>'.$html.'</body>', LIBXML_NOERROR, 'UTF-8');

        return $document->body->innerHTML;
    }
}
