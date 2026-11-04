<?php

namespace Tests\Unit\Obsidian;

use App\Services\Obsidian\Note;
use Tests\TestCase;

class NoteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'Asia/Tehran']);
    }

    private function note(string $props, string $body = "body\n", string $path = 'posts/Note.md'): Note
    {
        return new Note($path, "---\n{$props}\n---\n{$body}");
    }

    private function valid(string $extra = ''): Note
    {
        return $this->note("slug: my-post\ndate: 2026-11-04\npublish: true".($extra !== '' ? "\n{$extra}" : ''));
    }

    private function assertError(Note $note, string $fragment): void
    {
        $this->assertTrue(
            collect($note->errors)->contains(fn ($e) => str_contains($e, $fragment)),
            "No error containing \"{$fragment}\" in: ".json_encode($note->errors, JSON_UNESCAPED_UNICODE),
        );
    }

    public function test_a_valid_note(): void
    {
        $note = $this->valid("title: سلام\nlang: en\nid: shared-key\ntags: [a, '#b', a]\ndescription: ' short '\ntoc: false");

        $this->assertSame([], $note->errors);
        $this->assertTrue($note->publish);
        $this->assertSame('en', $note->locale);
        $this->assertSame('my-post', $note->slug);
        $this->assertSame('shared-key', $note->key);
        $this->assertTrue($note->hasId);
        $this->assertSame('سلام', $note->title);
        $this->assertSame('short', $note->description);
        $this->assertSame(['a', 'b'], $note->tags());
        $this->assertFalse($note->toc);
        $this->assertTrue($note->telegram);
        $this->assertSame('body', trim($note->body));
    }

    public function test_without_id_the_key_is_the_slug_and_the_title_the_file_name(): void
    {
        $note = $this->valid();

        $this->assertFalse($note->hasId);
        $this->assertSame('my-post', $note->key);
        $this->assertSame('Note', $note->title);
    }

    public function test_private_notes_are_not_checked(): void
    {
        $note = $this->note("slug: [whatever]\npublish: false");

        $this->assertFalse($note->publish);
        $this->assertSame([], $note->errors);
        $this->assertFalse((new Note('posts/x.md', 'no properties'))->publish);
    }

    public function test_lists_and_objects_where_text_is_expected_are_errors(): void
    {
        foreach (['slug', 'lang', 'id', 'title', 'description', 'cover', 'cover_alt', 'audio', 'video', 'series'] as $name) {
            $base = $name === 'slug' ? "date: 2026-11-04\npublish: true" : "slug: ok\ndate: 2026-11-04\npublish: true";
            $this->assertError($this->note("{$base}\n{$name}: [a, b]"), "'{$name}' must be text");
            $this->assertError($this->note("{$base}\n{$name}: {x: 1}"), "'{$name}' must be text");
            $this->assertError($this->note("{$base}\n{$name}: true"), "'{$name}' must be text");
        }
    }

    public function test_tag_items_must_be_text(): void
    {
        $this->assertError($this->valid("tags:\n  - [nested]"), 'each tag must be text');
        $this->assertError($this->valid("tags:\n  - {a: 1}"), 'each tag must be text');
        $this->assertError($this->valid('tags: {a: 1}'), "'tags' must be a list");
        $this->assertSame(['2026', 'x'], $this->valid('tags: [2026, x]')->tags());
        $this->assertSame(['یک', 'دو'], $this->valid('tags: یک، دو')->tags());
    }

    public function test_lengths(): void
    {
        $this->assertError($this->valid('tags: ['.str_repeat('ت', 61).']'), 'tag is longer than 60');
        $this->assertSame([], $this->valid('tags: ['.str_repeat('ت', 60).']')->errors);
        $this->assertError($this->note('slug: '.str_repeat('a', 81)."\ndate: 2026-11-04\npublish: true"), "'slug' is longer than 80");
        $this->assertSame([], $this->note('slug: '.str_repeat('a', 80)."\ndate: 2026-11-04\npublish: true")->errors);
        $this->assertError($this->valid('id: '.str_repeat('a', 81)), "'id' is longer than 80");
        $this->assertError($this->valid('title: '.str_repeat('ع', 161)), "'title' is longer than 160");
        $this->assertSame([], $this->valid('title: '.str_repeat('ع', 160))->errors);
        $this->assertError($this->valid('cover_alt: '.str_repeat('a', 201)), "'cover_alt' is longer than 200");
        $this->assertError($this->valid('video: https://x.ir/'.str_repeat('a', 250)), "'video' is longer than 255");
        $this->assertError($this->valid('description: '.str_repeat('a', 301)), "'description' is longer than 300");
    }

    public function test_slug_id_lang_and_video_rules(): void
    {
        $this->assertError($this->note("slug: My Post\ndate: 2026-11-04\npublish: true"), "'slug' may only use");
        $this->assertError($this->note("date: 2026-11-04\npublish: true"), "required property 'slug'");
        $this->assertError($this->valid('id: Bad_Id'), "'id' may only use");
        $this->assertError($this->valid('lang: de'), "'lang' must be fa or en");
        $this->assertError($this->valid('video: http://x.ir/v.mp4'), "'video' must be an https:// address");
        $this->assertError($this->valid('toc: yes please'), "'toc' must be true or false");
        $this->assertError($this->valid('aliases: [x]'), "'aliases' is not allowed");
        $this->assertSame('2026', $this->note("slug: 2026\ndate: 2026-11-04\npublish: true")->slug);
    }

    public function test_dates_without_a_zone_are_tehran_time(): void
    {
        $this->assertSame('2026-11-04 00:00 +03:30', $this->note("slug: a\ndate: 2026-11-04\npublish: true")->date->format('Y-m-d H:i P'));
        $this->assertSame('2026-11-04 09:30 +03:30', $this->note("slug: a\ndate: 2026-11-04T09:30\npublish: true")->date->format('Y-m-d H:i P'));
        $this->assertSame('2026-11-04 09:30 +03:30', $this->note("slug: a\ndate: 2026-11-04 09:30:00\npublish: true")->date->format('Y-m-d H:i P'));
        $this->assertSame('2026-11-04 09:30 +03:30', $this->note("slug: a\ndate: '2026-11-04T09:30:00'\npublish: true")->date->format('Y-m-d H:i P'));
    }

    public function test_dates_with_a_zone_are_converted_to_tehran_time(): void
    {
        // Unquoted: YAML makes a DateTime carrying the zone.
        $this->assertSame('2026-11-04 13:00 +03:30', $this->note("slug: a\ndate: 2026-11-04T09:30:00Z\npublish: true")->date->format('Y-m-d H:i P'));
        $this->assertSame('2026-11-04 09:30 +03:30', $this->note("slug: a\ndate: 2026-11-04 09:30:00 +03:30\npublish: true")->date->format('Y-m-d H:i P'));
        // Quoted or without seconds: a string with a zone.
        $this->assertSame('2026-11-04 13:00 +03:30', $this->note("slug: a\ndate: '2026-11-04T09:30:00Z'\npublish: true")->date->format('Y-m-d H:i P'));
        $this->assertSame('2026-11-04 13:00 +03:30', $this->note("slug: a\ndate: 2026-11-04T09:30Z\npublish: true")->date->format('Y-m-d H:i P'));
        $this->assertSame('2026-11-04 11:30 +03:30', $this->note("slug: a\ndate: 2026-11-04T09:30+01:30\npublish: true")->date->format('Y-m-d H:i P'));
        $this->assertSame('2026-11-04 13:00 +03:30', $this->note("slug: a\ndate: 2026-11-04\nupdated: 2026-11-04T09:30:00Z\npublish: true")->updated->format('Y-m-d H:i P'));
    }

    public function test_bad_dates_are_errors(): void
    {
        $note = $this->note("slug: a\ndate: 1730000000\npublish: true");
        $this->assertNull($note->date);
        $this->assertError($note, "'date' must look like");

        $this->assertError($this->note("slug: a\ndate: '2026-02-30'\npublish: true"), "'date' is not a real date");
        $this->assertError($this->note("slug: a\ndate: next week\npublish: true"), "'date' must look like");
        $this->assertError($this->note("slug: a\ndate: [2026-11-04]\npublish: true"), "'date' must look like");
        $this->assertError($this->note("slug: a\npublish: true"), "required property 'date'");
        $this->assertError($this->valid('updated: 12'), "'updated' must look like");
    }

    public function test_unreadable_properties(): void
    {
        $this->assertError($this->note("slug: [unclosed\npublish: true"), 'properties cannot be read');
        $this->assertError($this->note("- a\n- b"), 'properties must be "name: value" lines');
        $this->assertError($this->note('just text'), 'properties must be "name: value" lines');
    }
}
