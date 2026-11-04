<?php

namespace App\Services\Obsidian;

use App\Services\Content\MarkdownRenderer;
use App\Services\Content\Prose;
use App\Support\Locales;

/**
 * Turns Obsidian links into links the site understands, for one note:
 *   [[Note]], [[Note|text]], [[Note#Heading|text]]  → link to the published post (or plain text)
 *   ![[image.png]], ![[image.png|alt]], ![[ep.mp3]]  → attachment copied next to the post
 *   [text](Other%20note.md), ![](../attachments/a.png) → the same, for standard Markdown links
 *   %% private comment %%                           → removed
 * Embedding a whole note (![[Other note]]) is reported as an error.
 */
class LinkConverter
{
    private const MEDIA = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'svg', 'mp3', 'm4a', 'ogg', 'oga', 'wav', 'mp4', 'webm', 'pdf'];

    /** @var array<string,string> name in the post folder → vault path */
    public array $attachments = [];

    public array $errors = [];

    public array $warnings = [];

    /**
     * @param  array<string,list<Note>>  $postsByStem  published notes by lower-case file name
     * @param  array<string,Note>  $postsByKey  published notes by "key:locale"
     * @param  array<string,true>  $allNotes  every note in the vault by lower-case file name
     * @param  array<string,list<string>>  $files  every other file by lower-case file name → vault paths
     */
    public function __construct(
        private Note $note,
        private array $postsByStem,
        private array $postsByKey,
        private array $allNotes,
        private array $files,
    ) {}

    public function convert(string $body): string
    {
        return Prose::map($body, function (string $text) {
            // Standard links first, so the links that wikilinks turn into are not resolved twice.
            $text = preg_replace_callback('/(!?)\[([^\]]*)\]\(\s*(<[^>]+>|[^)\s]+)(\s+"[^"]*")?\s*\)/u', $this->markdownLink(...), $text);

            return preg_replace_callback('/(!?)\[\[([^\[\]|#^]*)(#\^?[^\[\]|]*)?(?:\|([^\[\]]*))?\]\]/u', $this->wikilink(...), $text);
        });
    }

    /** Copy an attachment next to the post; returns its file name there, an https address, or null. */
    public function attach(string $ref): ?string
    {
        if (preg_match('#^https?://#', $ref)) {
            return $ref;
        }
        $source = $this->findFile($ref);
        if (! $source) {
            $this->errors[] = "attachment not found: {$ref}";

            return null;
        }
        $name = $this->safeName(basename($source), $source);
        $this->attachments[$name] = $source;

        return $name;
    }

    private function wikilink(array $m): string
    {
        [$all, $bang, $target, $anchor, $text] = $m + ['', '', '', '', ''];
        $target = trim($target);
        $text = $text !== '' ? $text : null;
        if ($target === '') {
            return $anchor !== '' ? '['.($text ?? substr($anchor, 1)).'](#'.MarkdownRenderer::anchor(substr($anchor, 1)).')' : $all;
        }
        if ($bang) {
            return $this->embed($target, $text);
        }

        return $this->noteLink($target, $anchor ?: null, $text);
    }

    private function markdownLink(array $m): string
    {
        [$all, $bang, $text, $target] = $m;
        $title = $m[4] ?? '';
        $target = str_starts_with($target, '<') ? substr($target, 1, -1) : $target;
        if (preg_match('#^([a-z][a-z0-9+.-]*:|\#|/)#i', $target)) {
            return $all; // web, mail, in-page or site links stay as they are
        }
        $target = rawurldecode($target);
        [$path, $anchor] = array_pad(explode('#', $target, 2), 2, null);
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === 'md' || $ext === '') {
            if ($bang) {
                return $this->embed($path, $text);
            }

            return $this->noteLink(pathinfo($path, PATHINFO_FILENAME), $anchor ? '#'.$anchor : null, $text);
        }
        $name = $this->attach($path);
        if ($name === null) {
            return $bang ? '' : $all;
        }

        return "{$bang}[{$text}](<{$name}>{$title})";
    }

    private function embed(string $target, ?string $text): string
    {
        $ext = strtolower(pathinfo($target, PATHINFO_EXTENSION));
        if (! in_array($ext, self::MEDIA, true)) {
            $this->errors[] = "embedding a note is not supported: ![[{$target}]]. Use a link [[{$target}]] instead";

            return '';
        }
        $name = $this->attach($target);
        if ($name === null) {
            return '';
        }
        $alt = ($text === null || preg_match('/^\d+(x\d+)?$/', $text)) ? '' : $text;

        return "![{$alt}](<{$name}>)";
    }

    private function noteLink(string $target, ?string $anchor, ?string $text): string
    {
        $label = $text ?? $target;
        $post = $this->findPost($target);
        if (! $post) {
            $kind = isset($this->allNotes[mb_strtolower(basename($target, '.md'))]) ? 'unpublished' : 'missing';
            $this->warnings[] = "link to {$kind} note '{$target}' shown as plain text";

            return $label;
        }
        $url = Locales::path($post->locale, '/posts/'.$post->slug);
        if ($anchor && ! str_starts_with($anchor, '#^')) {
            $url .= '#'.MarkdownRenderer::anchor(substr($anchor, 1));
        }

        return "[{$label}]({$url})";
    }

    /** Prefer the note in the same language; otherwise its translation in this language; otherwise any. */
    private function findPost(string $target): ?Note
    {
        $candidates = $this->postsByStem[mb_strtolower(basename($target, '.md'))] ?? [];
        foreach ($candidates as $n) {
            if ($n->locale === $this->note->locale) {
                return $n;
            }
        }
        foreach ($candidates as $n) {
            if (isset($this->postsByKey[$n->key.':'.$this->note->locale])) {
                return $this->postsByKey[$n->key.':'.$this->note->locale];
            }
        }

        return $candidates[0] ?? null;
    }

    private function findFile(string $ref): ?string
    {
        $ref = trim($ref);
        if (str_contains($ref, '/')) {
            foreach ([dirname($this->note->path), ''] as $base) {
                $candidate = $this->normalizePath(ltrim($base.'/'.$ref, '/'));
                foreach ($this->files[mb_strtolower(basename($candidate))] ?? [] as $path) {
                    if ($path === $candidate) {
                        return $path;
                    }
                }
            }
        }
        $matches = $this->files[mb_strtolower(basename($ref))] ?? [];
        foreach ($matches as $path) {
            if (dirname($path) === dirname($this->note->path)) {
                return $path;
            }
        }

        return $matches[0] ?? null;
    }

    private function normalizePath(string $path): string
    {
        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '..') {
                array_pop($parts);
            } elseif ($part !== '.' && $part !== '') {
                $parts[] = $part;
            }
        }

        return implode('/', $parts);
    }

    /** URL-friendly file name, unique inside the post's folder. */
    private function safeName(string $name, string $source): string
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $stem = pathinfo($name, PATHINFO_FILENAME);
        $ascii = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($stem)), '-');
        if ($ascii === '' || preg_match('/[^\x20-\x7E]/', $stem)) {
            $ascii = 'file-'.substr(sha1($name), 0, 8);
        }
        $candidate = "{$ascii}.{$ext}";
        for ($n = 2; isset($this->attachments[$candidate]) && $this->attachments[$candidate] !== $source; $n++) {
            $candidate = "{$ascii}-{$n}.{$ext}";
        }

        return $candidate;
    }
}
