import unittest
from types import SimpleNamespace

from blogtools.obsidian import Converter, heading_anchor, safe_filename, split_fenced


class FakeResolver:
    def __init__(self):
        self.posts = {
            "other note": SimpleNamespace(path="/posts/other/", lang="fa"),
        }
        self.files = {"pic.png": "/vault/attachments/pic.png", "نقشه.png": "/vault/attachments/نقشه.png",
                      "ep.mp3": "/vault/attachments/ep.mp3"}

    def find_post(self, target, lang):
        return self.posts.get(target.lower())

    def note_exists(self, target):
        return target in ("draft note",)

    def find_attachment(self, ref, note_path):
        return self.files.get(ref.split("/")[-1])


def convert(body):
    c = Converter(FakeResolver(), SimpleNamespace(path="/vault/posts/a.md", lang="fa"))
    return c.convert(body), c


class ConverterTest(unittest.TestCase):
    def test_wikilinks(self):
        out, c = convert("see [[Other note]] and [[Other note|this]] and [[Other note#Part One|part]]")
        self.assertEqual(out, "see [Other note](/posts/other/) and [this](/posts/other/) and [part](/posts/other/#part-one)")

    def test_link_to_unpublished_note_becomes_text(self):
        out, c = convert("see [[draft note|the draft]] and [[missing]]")
        self.assertEqual(out, "see the draft and missing")
        self.assertEqual(len(c.warnings), 2)
        self.assertIn("unpublished", c.warnings[0])

    def test_embeds_copy_attachments(self):
        out, c = convert("![[pic.png]]\n![[نقشه.png|نقشه‌ی راه]]\n![[pic.png|300]]\n![[ep.mp3]]")
        lines = out.splitlines()
        self.assertEqual(lines[0], "![](pic.png)")
        self.assertRegex(lines[1], r"^!\[نقشه‌ی راه\]\(file-[0-9a-f]{8}\.png\)$")
        self.assertEqual(lines[2], "![](pic.png)")
        self.assertEqual(lines[3], "![](ep.mp3)")
        self.assertEqual(set(c.attachments.values()), set(FakeResolver().files.values()))

    def test_note_embed_is_an_error(self):
        _, c = convert("![[Other note]]")
        self.assertTrue(c.errors and "not supported" in c.errors[0])

    def test_missing_attachment_is_an_error(self):
        _, c = convert("![[nope.png]]")
        self.assertIn("attachment not found: nope.png", c.errors)

    def test_markdown_links(self):
        out, _ = convert("[x](Other%20note.md) ![a](../attachments/pic.png) [web](https://example.com) [top](#intro)")
        self.assertEqual(out, "[x](/posts/other/) ![a](pic.png) [web](https://example.com) [top](#intro)")

    def test_code_is_untouched(self):
        body = "```\n[[Other note]] %% keep %%\n```\nand `[[Other note]]` but [[Other note]]\n"
        out, _ = convert(body)
        self.assertIn("```\n[[Other note]] %% keep %%\n```", out)
        self.assertIn("`[[Other note]]`", out)
        self.assertTrue(out.endswith("but [Other note](/posts/other/)\n"))

    def test_comments_are_removed(self):
        out, _ = convert("public %% private\nsecret %% text")
        self.assertEqual(out, "public  text")

    def test_unclosed_fence_is_code(self):
        chunks = split_fenced("a\n```\ncode [[x]]\n")
        self.assertEqual(chunks[-1], (True, "```\ncode [[x]]\n"))

    def test_heading_anchor_matches_hugo(self):
        self.assertEqual(heading_anchor("چرا می‌نویسم"), "چرا-مینویسم")
        self.assertEqual(heading_anchor("Part One: Setup!"), "part-one-setup")

    def test_safe_filename(self):
        used = {}
        self.assertEqual(safe_filename("My Photo.PNG", used), "my-photo.png")
        self.assertEqual(safe_filename("my photo.png", used), "my-photo-2.png")
        self.assertRegex(safe_filename("عکس.jpg", used), r"^file-[0-9a-f]{8}\.jpg$")


if __name__ == "__main__":
    unittest.main()
