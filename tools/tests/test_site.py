"""Builds the example site with Hugo and checks the output. Skipped when Hugo is not installed."""

import json
import os
import re
import shutil
import subprocess
import tempfile
import unittest

from blogtools import REPO_ROOT
from blogtools.build_content import build

HUGO = shutil.which("hugo")


@unittest.skipUnless(HUGO, "hugo is not installed")
class SiteTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.tmp = tempfile.TemporaryDirectory()
        site = os.path.join(cls.tmp.name, "site")
        def skip_generated(directory, names):
            generated = {"public", "resources"}
            if os.path.basename(os.path.dirname(directory)) == "content":
                generated.add("posts")  # site/content/<lang>/posts is rebuilt below
            return generated & set(names)

        shutil.copytree(os.path.join(REPO_ROOT, "site"), site, ignore=skip_generated)
        code, _ = build(os.path.join(REPO_ROOT, "example-vault"), os.path.join(site, "content"),
                        os.path.join(cls.tmp.name, "manifest.json"))
        assert code == 0
        cls.public = os.path.join(cls.tmp.name, "public")
        subprocess.run([HUGO, "--source", site, "--destination", cls.public, "--quiet", "--panicOnWarning"],
                       check=True)

    @classmethod
    def tearDownClass(cls):
        cls.tmp.cleanup()

    def page(self, rel):
        with open(os.path.join(self.public, rel), encoding="utf-8") as f:
            return f.read()

    def test_jalali_dates(self):
        html = self.page("posts/hello-world/index.html")  # 2026-11-01
        self.assertIn("۱۰ آبان ۱۴۰۵", html)
        self.assertIn("Nov 1, 2026", self.page("en/posts/hello-world/index.html"))

    def test_direction_and_language(self):
        self.assertRegex(self.page("index.html"), r'<html lang=fa dir=rtl')
        self.assertRegex(self.page("en/index.html"), r'<html lang=en dir=ltr')

    def test_hreflang_pairs(self):
        html = self.page("posts/hello-world/index.html")
        self.assertIn("hreflang=en href=https://example.com/en/posts/hello-world/", html)
        self.assertIn("hreflang=x-default href=https://example.com/posts/hello-world/", html)

    def test_structured_data_is_valid_json(self):
        html = self.page("posts/hello-world/index.html")
        raw = re.search(r"<script type=application/ld\+json>(.*?)</script>", html, re.S).group(1)
        graph = json.loads(raw)["@graph"]
        types = {item["@type"] for item in graph}
        self.assertEqual(types, {"BlogPosting", "BreadcrumbList"})
        post = next(i for i in graph if i["@type"] == "BlogPosting")
        self.assertEqual(post["inLanguage"], "fa-IR")
        self.assertTrue(post["image"][0].endswith(".jpg"))

    def test_private_notes_not_published(self):
        self.assertFalse(os.path.exists(os.path.join(self.public, "posts", "raw-ideas")))
        for root, _, files in os.walk(self.public):
            for name in files:
                if name.endswith((".html", ".json", ".xml")):
                    with open(os.path.join(root, name), encoding="utf-8") as f:
                        self.assertNotIn("خصوصی است و هرگز", f.read(), name)

    def test_math_is_prerendered(self):
        self.assertIn("<math", self.page("posts/hello-world/index.html"))

    def test_search_index(self):
        items = json.loads(self.page("index.json"))
        self.assertEqual({i["u"] for i in items}, {"/posts/hello-world/", "/posts/blog-architecture/"})


if __name__ == "__main__":
    unittest.main()
