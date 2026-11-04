<?php

namespace Tests\Unit\Obsidian;

use App\Services\Content\MarkdownRenderer;
use App\Services\Obsidian\LinkConverter;
use App\Services\Obsidian\Note;
use Tests\TestCase;

class LinkConverterTest extends TestCase
{
    /** @var array<string,Note> */
    private array $notes = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->notes = [
            'other' => $this->makeNote('posts/Other.md', 'other'),
            'سلام' => $this->makeNote('posts/سلام.md', 'salam', 'fa', 'hello'),
            'hello' => $this->makeNote('posts/Hello.md', 'hello-en', 'en', 'hello'),
        ];
    }

    private function makeNote(string $path, string $slug, string $lang = 'fa', ?string $id = null): Note
    {
        $note = new Note($path, "---\nslug: {$slug}\nlang: {$lang}\n".($id ? "id: {$id}\n" : '')."date: 2026-11-01\npublish: true\n---\n");
        $this->assertSame([], $note->errors);

        return $note;
    }

    /** @param array<string,list<string>> $files */
    private function converter(?Note $note = null, array $files = [], array $private = []): LinkConverter
    {
        $note ??= $this->makeNote('posts/Current.md', 'current');
        $byStem = [];
        $byKey = [];
        foreach ($this->notes as $n) {
            $byStem[$n->stem()][] = $n;
            $byKey[$n->key.':'.$n->locale] = $n;
        }
        $all = array_fill_keys([...array_map(fn (Note $n) => $n->stem(), $this->notes), ...$private], true);
        $index = [];
        foreach ($files as $path) {
            $index[mb_strtolower(basename($path))][] = $path;
        }

        return new LinkConverter($note, $byStem, $byKey, $all, $index);
    }

    public function test_relative_attachment_paths_are_resolved_from_the_note_folder(): void
    {
        $c = $this->converter(files: ['attachments/pic.png', 'posts/pic.png']);

        $this->assertSame('![](<pic.png>)', $c->convert('![](../attachments/pic.png)'));
        $this->assertSame(['pic.png' => 'attachments/pic.png'], $c->attachments);
        $this->assertSame([], $c->errors);
    }

    public function test_angle_bracket_targets_and_encoded_spaces(): void
    {
        $c = $this->converter(files: ['attachments/My Photo.JPG']);

        $this->assertSame('![a photo](<my-photo.jpg> "title")', $c->convert('![a photo](<../attachments/My Photo.JPG> "title")'));
        $this->assertSame('[the pdf](<doc.pdf>)', $this->converter(files: ['x/doc.pdf'])->convert('[the pdf](doc.pdf)'));
        $this->assertSame('[x](/posts/other)', $this->converter()->convert('[x](<Other.md>)'));
        $this->notes['my note'] = $this->makeNote('posts/My Note.md', 'my-note');
        $this->assertSame('[x](/posts/my-note)', $this->converter()->convert('[x](My%20Note.md)'));
    }

    public function test_wikilinks_with_headings_and_labels(): void
    {
        $c = $this->converter();

        $this->assertSame('[x](/posts/other#'.MarkdownRenderer::anchor('Some Heading').')', $c->convert('[[Other#Some Heading|x]]'));
        $this->assertSame('[Other](/posts/other)', $c->convert('[[Other]]'));
        $this->assertSame('[see](/posts/other)', $c->convert('[[Other#^abc123|see]]'), 'block ids are dropped');
        $this->assertSame('[x](/posts/other)', $c->convert('[x](Other.md#^abc123)'));
        $this->assertSame('see', $c->convert('[[#^abc123|see]]'));
        $this->assertSame('[Top](#'.MarkdownRenderer::anchor('Top').')', $c->convert('[[#Top]]'));
    }

    public function test_links_prefer_the_translation_in_the_note_language(): void
    {
        $english = $this->makeNote('posts/Current.md', 'current', 'en');

        $this->assertSame('[hi](/en/posts/hello-en)', $this->converter($english)->convert('[[سلام|hi]]'));
        $this->assertSame('[hi](/posts/salam)', $this->converter()->convert('[[Hello|hi]]'));
        $this->assertSame('[o](/posts/other)', $this->converter($english)->convert('[[Other|o]]'), 'no translation: the other language');
    }

    public function test_private_and_missing_notes_become_plain_text(): void
    {
        $c = $this->converter(private: ['secret']);

        $this->assertSame('Secret', $c->convert('[[Secret]]'));
        $this->assertSame('label', $c->convert('[[Nowhere|label]]'));
        $this->assertCount(2, $c->warnings);
        $this->assertStringContainsString('unpublished', $c->warnings[0]);
        $this->assertStringContainsString('missing', $c->warnings[1]);
    }

    public function test_safe_names_are_unique_and_ascii(): void
    {
        $c = $this->converter(files: ['a/pic.png', 'b/pic.png', 'attachments/نمودار.png']);

        $out = $c->convert("![[a/pic.png]]\n![[b/pic.png]]\n![[a/pic.png|alt]]\n![[نمودار.png|300]]");

        $this->assertStringContainsString('![](<pic.png>)', $out);
        $this->assertStringContainsString('![](<pic-2.png>)', $out);
        $this->assertStringContainsString('![alt](<pic.png>)', $out);
        $this->assertMatchesRegularExpression('/!\[\]\(<file-[0-9a-f]{8}\.png>\)/', $out, 'non-ASCII names and size hints');
        $this->assertCount(3, $c->attachments);
        $this->assertSame('a/pic.png', $c->attachments['pic.png']);
        $this->assertSame('b/pic.png', $c->attachments['pic-2.png']);
    }

    public function test_embedding_a_note_is_an_error(): void
    {
        $c = $this->converter();

        $this->assertSame('', $c->convert('![[Other]]'));
        $this->assertStringContainsString('embedding a note is not supported', $c->errors[0]);
    }

    public function test_only_safe_file_types_are_attached(): void
    {
        $c = $this->converter(files: ['x.php', 'logo.svg', 'notes/run.sh']);

        $this->assertSame('', $c->convert('![[x.php]]'));
        $this->assertNull($c->attach('logo.svg'));
        $this->assertSame('[run](notes/run.sh)', $c->convert('[run](notes/run.sh)'));
        $this->assertContains('file type not allowed: x.php (allowed: '.implode(', ', LinkConverter::ATTACHABLE).')', $c->errors);
        $this->assertTrue(collect($c->errors)->contains(fn ($e) => str_starts_with($e, 'file type not allowed: logo.svg')));
        $this->assertTrue(collect($c->errors)->contains(fn ($e) => str_starts_with($e, 'file type not allowed: run.sh')));
        $this->assertSame([], $c->attachments);
    }

    public function test_paths_climbing_out_of_the_vault_are_refused(): void
    {
        $c = $this->converter(files: ['pic.png']);

        $this->assertNull($c->attach('../../pic.png'));
        $this->assertStringContainsString('outside the vault', $c->errors[0]);
        $this->assertSame('pic.png', $c->attach('../pic.png'), 'posts/../pic.png is the vault root');
    }

    public function test_missing_attachments_and_web_addresses(): void
    {
        $c = $this->converter();

        $this->assertNull($c->attach('nothing.png'));
        $this->assertSame('attachment not found: nothing.png', $c->errors[0]);
        $this->assertSame('https://cdn.example/a.mp3', $c->attach('https://cdn.example/a.mp3'));
        $this->assertSame('![](https://cdn.example/a.png)', $c->convert('![](https://cdn.example/a.png)'));
    }

    public function test_links_in_code_are_untouched(): void
    {
        $c = $this->converter();
        $text = "Use `[[Other]]` and `![[x.php]]` inline.\n\n```md\n[[Other]] ![](x.php)\n```\n";

        $this->assertSame($text, $c->convert($text));
        $this->assertSame([], $c->errors);
    }

    public function test_comments_are_removed(): void
    {
        $this->assertSame("a  b\n", $this->converter()->convert("a %% private [[Other]] %% b\n"));
    }
}
