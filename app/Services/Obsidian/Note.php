<?php

namespace App\Services\Obsidian;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * One note from the vault's posts folder, with its properties checked against the rules below.
 * The same rules are documented for the writer in the vault's guide note.
 *
 * Every problem with a property becomes an entry in $errors; a note never throws because of what the
 * writer typed (lists where text is expected, numbers where a date is expected, and so on).
 */
class Note
{
    public const SLUG = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public const MAX_SLUG = 80;

    public const MAX_TITLE = 160;

    public const MAX_DESCRIPTION = 300;

    public const MAX_TAG = 60;

    public const MAX_TAGS = 10;

    public const MAX_SERIES = 120;

    public const MAX_COVER_ALT = 200;

    public const MAX_REF = 255;

    public array $props = [];

    public string $body = '';

    /** @var list<string> */
    public array $errors = [];

    public bool $publish = false;

    public string $locale = 'fa';

    public string $slug = '';

    /** The note's `id`, or its slug when it has none. */
    public string $key = '';

    /** True when the note sets `id` itself (then the id, not the file, identifies the post). */
    public bool $hasId = false;

    public string $title = '';

    public ?string $description = null;

    public ?string $cover = null;

    public ?string $coverAlt = null;

    public ?string $audio = null;

    public ?string $video = null;

    public ?string $series = null;

    public bool $toc = true;

    public bool $telegram = true;

    /** @var list<string> */
    public array $tags = [];

    public ?CarbonImmutable $date = null;

    public ?CarbonImmutable $updated = null;

    public function __construct(public string $path, string $text)
    {
        if (! preg_match('/\A---[ \t]*\r?\n(.*?)\r?\n---[ \t]*(?:\r?\n|\z)/s', $text, $m)) {
            $this->body = $text;

            return; // no properties: a private note
        }
        $this->body = substr($text, strlen($m[0]));
        try {
            $props = Yaml::parse($m[1], Yaml::PARSE_DATETIME) ?? [];
        } catch (ParseException $e) {
            $this->errors[] = 'properties cannot be read: '.$e->getMessage();

            return;
        }
        if (! is_array($props) || array_is_list($props) && $props !== []) {
            $this->errors[] = 'properties must be "name: value" lines';

            return;
        }
        $this->props = $props;
        $this->publish = ($props['publish'] ?? false) === true;
        if ($this->publish) {
            $this->check();
        }
    }

    private function check(): void
    {
        $p = $this->props;

        $lang = $this->text('lang', 10);
        $this->locale = $lang ?? 'fa';
        if ($lang !== null && ! in_array($lang, ['fa', 'en'], true)) {
            $this->errors[] = "'lang' must be fa or en, got ".$this->show($p['lang']);
            $this->locale = 'fa';
        }

        $this->slug = $this->text('slug', self::MAX_SLUG) ?? '';
        if (! array_key_exists('slug', $p) || $p['slug'] === null || $p['slug'] === '') {
            $this->errors[] = "required property 'slug' is missing (Latin letters, digits and dashes, e.g. my-first-post)";
        } elseif ($this->slug !== '' && ! preg_match(self::SLUG, $this->slug)) {
            $this->errors[] = "'slug' may only use a-z, 0-9 and single dashes: ".$this->show($this->slug);
        }

        $id = $this->text('id', self::MAX_SLUG);
        $this->hasId = $id !== null && $id !== '';
        $this->key = $this->hasId ? $id : $this->slug;
        if ($this->hasId && ! preg_match(self::SLUG, $id)) {
            $this->errors[] = "'id' may only use a-z, 0-9 and single dashes: ".$this->show($id);
        }

        $this->title = trim($this->text('title', self::MAX_TITLE) ?? '') ?: pathinfo($this->path, PATHINFO_FILENAME);

        $this->date = $this->date('date', required: true);
        $this->updated = $this->date('updated', required: false);

        $description = $this->text('description', self::MAX_DESCRIPTION);
        $this->description = $description === null ? null : (trim($description) ?: null);
        $this->cover = $this->text('cover', self::MAX_REF) ?: null;
        $this->coverAlt = $this->text('cover_alt', self::MAX_COVER_ALT) ?: null;
        $this->audio = $this->text('audio', self::MAX_REF) ?: null;
        $this->video = $this->text('video', self::MAX_REF) ?: null;
        if ($this->video !== null && ! str_starts_with($this->video, 'https://')) {
            $this->errors[] = "'video' must be an https:// address";
        }
        $series = $this->text('series', self::MAX_SERIES);
        $this->series = $series === null ? null : (trim($series) ?: null);

        $this->toc = $this->flag('toc');
        $this->telegram = $this->flag('telegram');
        $this->tags = $this->readTags();

        if (array_key_exists('aliases', $p)) {
            $this->errors[] = "'aliases' is not allowed on published notes (it means something else on the site); remove it";
        }
    }

    /** A text property: numbers are accepted as text; lists, objects, dates and true/false are errors. */
    private function text(string $name, int $max): ?string
    {
        $value = $this->props[$name] ?? null;
        if ($value === null) {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            $value = (string) $value;
        }
        if (! is_string($value)) {
            $this->errors[] = "'{$name}' must be text, got ".$this->show($value);

            return null;
        }
        if (mb_strlen($value) > $max) {
            $this->errors[] = "'{$name}' is longer than {$max} characters";

            return null;
        }

        return $value;
    }

    private function flag(string $name): bool
    {
        $value = $this->props[$name] ?? null;
        if ($value === null) {
            return true;
        }
        if (! is_bool($value)) {
            $this->errors[] = "'{$name}' must be true or false";

            return true;
        }

        return $value;
    }

    /** @return list<string> */
    private function readTags(): array
    {
        $tags = $this->props['tags'] ?? null;
        if ($tags === null || $tags === '') {
            return [];
        }
        if (is_string($tags)) {
            $tags = preg_split('/[,،]\s*/u', $tags);
        } elseif (! is_array($tags) || ! array_is_list($tags)) {
            $this->errors[] = "'tags' must be a list";

            return [];
        }
        $clean = [];
        foreach ($tags as $tag) {
            if (is_int($tag) || is_float($tag)) {
                $tag = (string) $tag;
            }
            if (! is_string($tag)) {
                $this->errors[] = 'each tag must be text, got '.$this->show($tag);

                continue;
            }
            $tag = ltrim(trim($tag), '#');
            if ($tag === '') {
                continue;
            }
            if (mb_strlen($tag) > self::MAX_TAG) {
                $this->errors[] = 'tag is longer than '.self::MAX_TAG.' characters: '.$this->show(mb_substr($tag, 0, 30).'…');

                continue;
            }
            if (! in_array($tag, $clean, true)) {
                $clean[] = $tag;
            }
        }

        return array_slice($clean, 0, self::MAX_TAGS);
    }

    /**
     * Dates are Tehran time (the app's time zone) unless they carry their own zone:
     * 2026-11-04, 2026-11-04T09:30, 2026-11-04 09:30:00 → Tehran wall time;
     * 2026-11-04T09:30:00Z, 2026-11-04T09:30+03:30 → that moment, shown in Tehran time.
     */
    private function date(string $name, bool $required): ?CarbonImmutable
    {
        $value = $this->props[$name] ?? null;
        if ($value === null || $value === '') {
            if ($required) {
                $this->errors[] = "required property '{$name}' is missing (e.g. 2026-11-04 or 2026-11-04T09:30)";
            }

            return null;
        }
        $tz = config('app.timezone');
        if ($value instanceof DateTimeInterface) {
            // YAML reads a date without a zone as UTC (zone name "UTC"); the writer means Tehran time.
            // A date written with a zone ("Z", "+03:30") keeps its moment.
            if ($value->getTimezone()->getName() === 'UTC') {
                return CarbonImmutable::parse($value->format('Y-m-d H:i:s'), $tz);
            }

            return CarbonImmutable::instance($value)->setTimezone($tz);
        }
        if (! is_string($value) || ! preg_match('/^(\d{4}-\d{2}-\d{2})(?:[T ](\d{2}:\d{2}(?::\d{2})?)\s*(Z|[+-]\d{2}(?::?\d{2})?)?)?$/i', trim($value), $m)) {
            $this->errors[] = "'{$name}' must look like 2026-11-04 or 2026-11-04T09:30, got ".$this->show($value);

            return null;
        }
        try {
            $local = $m[1].(isset($m[2]) && $m[2] !== '' ? ' '.$m[2] : '');
            $zone = $m[3] ?? '';
            if ($zone === '') {
                $date = CarbonImmutable::createFromFormat('!Y-m-d'.(strlen($local) > 10 ? (strlen($local) > 16 ? ' H:i:s' : ' H:i') : ''), $local, $tz);
            } else {
                $date = CarbonImmutable::parse($local.' '.(strtoupper($zone) === 'Z' ? '+00:00' : $zone))->setTimezone($tz);
            }
        } catch (\Throwable) {
            $date = null;
        }
        if (! $date || $date->format('Y-m-d') !== $m[1] && $zone === '') {
            $this->errors[] = "'{$name}' is not a real date: ".$this->show($value);

            return null;
        }

        return $date;
    }

    private function show(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s P');
        }

        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
    }

    /** @return list<string> */
    public function tags(): array
    {
        return $this->tags;
    }

    public function stem(): string
    {
        return mb_strtolower(pathinfo($this->path, PATHINFO_FILENAME));
    }

    /** Properties in a stable, comparable form (for the fingerprint). */
    public function normalizedProps(): array
    {
        return [
            'locale' => $this->locale, 'slug' => $this->slug, 'key' => $this->key, 'title' => $this->title,
            'date' => $this->date?->toIso8601String(), 'updated' => $this->updated?->toIso8601String(),
            'description' => $this->description, 'cover' => $this->cover, 'cover_alt' => $this->coverAlt,
            'audio' => $this->audio, 'video' => $this->video, 'series' => $this->series, 'toc' => $this->toc,
            'telegram' => $this->telegram, 'tags' => $this->tags,
        ];
    }
}
