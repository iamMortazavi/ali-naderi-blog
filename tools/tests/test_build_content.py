import datetime as dt
import json
import os
import tempfile
import unittest

from blogtools import REPO_ROOT
from blogtools.build_content import TEHRAN, build
from blogtools.schema import load_schema, validate

EXAMPLE_VAULT = os.path.join(REPO_ROOT, "example-vault")
NOW = dt.datetime(2026, 11, 4, 12, 0, tzinfo=TEHRAN)


def write(root, rel, text):
    path = os.path.join(root, rel)
    os.makedirs(os.path.dirname(path), exist_ok=True)
    with open(path, "w", encoding="utf-8") as f:
        f.write(text)


class SchemaTest(unittest.TestCase):
    def setUp(self):
        self.schema = load_schema()

    def test_valid(self):
        self.assertEqual(validate({"slug": "a-b", "date": "2026-11-04", "publish": True, "tags": ["x"]}, self.schema), [])

    def test_errors(self):
        errors = validate({"slug": "Bad Slug", "date": "4/11/2026", "publish": "yes", "lang": "de",
                           "aliases": ["x"]}, self.schema)
        text = "\n".join(errors)
        for field in ("slug", "date", "publish", "lang", "aliases"):
            self.assertIn(f"'{field}'", text)

    def test_required(self):
        self.assertIn("required property 'slug' is missing", validate({"date": "2026-11-04", "publish": True}, self.schema))


class BuildTest(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.out = os.path.join(self.tmp.name, "content")
        self.manifest = os.path.join(self.tmp.name, "build", "manifest.json")

    def tearDown(self):
        self.tmp.cleanup()

    def test_example_vault(self):
        code, manifest = build(EXAMPLE_VAULT, self.out, self.manifest, now=NOW)
        self.assertEqual(code, 0)
        keys = [p["key"] for p in manifest["posts"]]
        self.assertEqual(sorted(keys), ["blog-architecture:fa", "hello-world:en", "hello-world:fa"])
        # Private notes never reach the output.
        self.assertFalse(os.path.exists(os.path.join(self.out, "fa", "posts", "raw-ideas")))
        with open(os.path.join(self.out, "fa", "posts", "hello-world", "index.md"), encoding="utf-8") as f:
            page = f.read()
        self.assertNotIn("خصوصی است", page)          # %% comment %% removed
        self.assertIn("translationKey: hello-world", page)
        self.assertIn("(/posts/blog-architecture/)", page)
        self.assertTrue(os.path.exists(os.path.join(self.out, "fa", "posts", "hello-world", "cover-hello.png")))
        with open(self.manifest, encoding="utf-8") as f:
            saved = json.load(f)
        post = next(p for p in saved["posts"] if p["key"] == "hello-world:fa")
        self.assertEqual(post["path"], "/posts/hello-world/")
        self.assertEqual(post["cover"], "/posts/hello-world/cover-hello.png")
        self.assertFalse(post["future"])

    def test_invalid_note_stops_the_build(self):
        vault = os.path.join(self.tmp.name, "vault")
        write(vault, "posts/good.md", "---\nslug: good\ndate: 2026-11-01\npublish: true\n---\nok\n")
        write(vault, "posts/bad.md", "---\nslug: Bad Slug\ndate: 2026-11-01\npublish: true\n---\nno\n")
        code, manifest = build(vault, self.out, self.manifest, now=NOW)
        self.assertEqual(code, 1)
        self.assertIsNone(manifest)
        self.assertFalse(os.path.exists(self.out))

    def test_duplicate_slug(self):
        vault = os.path.join(self.tmp.name, "vault")
        for name in ("a", "b"):
            write(vault, f"posts/{name}.md", "---\nslug: same\ndate: 2026-11-01\npublish: true\n---\n")
        code, _ = build(vault, self.out, self.manifest, now=NOW)
        self.assertEqual(code, 1)

    def test_future_post_and_defaults(self):
        vault = os.path.join(self.tmp.name, "vault")
        write(vault, "posts/Later.md", "---\nslug: later\ndate: 2026-12-01T09:00\npublish: true\ntags: a, b\n---\nx\n")
        code, manifest = build(vault, self.out, self.manifest, now=NOW)
        self.assertEqual(code, 0)
        post = manifest["posts"][0]
        self.assertTrue(post["future"])
        self.assertEqual(post["title"], "Later")
        self.assertEqual(post["id"], "later")
        self.assertEqual(post["tags"], ["a", "b"])
        self.assertEqual(post["date"], "2026-12-01T09:00:00+03:30")


if __name__ == "__main__":
    unittest.main()
