<?php

namespace App\Services\Obsidian;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * One note from the vault's posts folder, with its properties checked against the rules below.
 * The same rules are documented for the writer in the vault's guide note.
 */
class Note
{
    public const SLUG = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public array $props = [];

    public string $body = '';

    public array $errors = [];

    public bool $publish = false;

    public string $locale = 'fa';

    public string $slug = '';

    public string $key = '';

    public string $title = '';

    public ?CarbonImmutable $date = null;

    public ?CarbonImmutable $updated = null;

    public function __construct(public string $path, string $text, public string $fingerprint)
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
        if (! is_array($props)) {
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
        $this->locale = $p['lang'] ?? 'fa';
        if (! in_array($this->locale, ['fa', 'en'], true)) {
            $this->errors[] = "'lang' must be fa or en, got ".json_encode($p['lang']);
        }

        $this->slug = (string) ($p['slug'] ?? '');
        if ($this->slug === '') {
            $this->errors[] = "required property 'slug' is missing (Latin letters, digits and dashes, e.g. my-first-post)";
        } elseif (! preg_match(self::SLUG, $this->slug) || strlen($this->slug) > 80) {
            $this->errors[] = "'slug' may only use a-z, 0-9 and single dashes: ".json_encode($this->slug);
        }

        $this->key = (string) ($p['id'] ?? $this->slug);
        if ($this->key !== '' && ! preg_match(self::SLUG, $this->key)) {
            $this->errors[] = "'id' may only use a-z, 0-9 and single dashes: ".json_encode($this->key);
        }

        $this->title = trim((string) ($p['title'] ?? '')) ?: pathinfo($this->path, PATHINFO_FILENAME);
        if (mb_strlen($this->title) > 160) {
            $this->errors[] = "'title' is longer than 160 characters";
        }

        $this->date = $this->parseDate($p['date'] ?? null, 'date', required: true);
        $this->updated = $this->parseDate($p['updated'] ?? null, 'updated', required: false);

        if (isset($p['description']) && (! is_string($p['description']) || mb_strlen($p['description']) > 300)) {
            $this->errors[] = "'description' must be text of at most 300 characters";
        }
        if (isset($p['tags']) && ! is_array($p['tags']) && ! is_string($p['tags'])) {
            $this->errors[] = "'tags' must be a list";
        }
        if (isset($p['video']) && ! str_starts_with((string) $p['video'], 'https://')) {
            $this->errors[] = "'video' must be an https:// address";
        }
        foreach (['toc', 'telegram'] as $flag) {
            if (isset($p[$flag]) && ! is_bool($p[$flag])) {
                $this->errors[] = "'{$flag}' must be true or false";
            }
        }
        if (array_key_exists('aliases', $p)) {
            $this->errors[] = "'aliases' is not allowed on published notes (it means something else on the site); remove it";
        }
    }

    private function parseDate(mixed $value, string $name, bool $required): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            if ($required) {
                $this->errors[] = "required property '{$name}' is missing (e.g. 2026-11-04 or 2026-11-04T09:30)";
            }

            return null;
        }
        $tz = config('app.timezone');
        if ($value instanceof DateTimeInterface) {
            // YAML reads dates without a time zone as UTC; the writer means Tehran time.
            return CarbonImmutable::parse($value->format('Y-m-d H:i:s'), $tz);
        }
        if (is_int($value)) {
            return CarbonImmutable::createFromTimestamp($value, $tz);
        }
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}([T ]\d{2}:\d{2}(:\d{2})?)?$/', $value)) {
            $this->errors[] = "'{$name}' must look like 2026-11-04 or 2026-11-04T09:30, got ".json_encode($value);

            return null;
        }

        return CarbonImmutable::parse(str_replace('T', ' ', $value), $tz);
    }

    /** @return list<string> */
    public function tags(): array
    {
        $tags = $this->props['tags'] ?? [];
        if (is_string($tags)) {
            $tags = preg_split('/[,،]\s*/u', $tags);
        }

        return collect($tags)->map(fn ($t) => ltrim(trim((string) $t), '#'))->filter()->unique()->take(10)->values()->all();
    }

    public function stem(): string
    {
        return mb_strtolower(pathinfo($this->path, PATHINFO_FILENAME));
    }
}
