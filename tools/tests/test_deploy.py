import os
import tempfile
import unittest
from unittest import mock

from blogtools import deploy


class FakeTarget:
    """Stands in for the FTPS host: a dict of uploaded files plus the state record."""

    def __init__(self, state=None, extra=()):
        self.state = dict(state or {})
        self.files = set(self.state) | set(extra)
        self.log = []

    def read_state(self):
        return dict(self.state)

    def write_state(self, files):
        self.state = dict(files)

    def put(self, rel, local_path):
        assert os.path.exists(local_path)
        self.files.add(rel)
        self.log.append(("put", rel))

    def delete(self, rel):
        self.files.discard(rel)
        self.log.append(("delete", rel))


def write(root, rel, text):
    path = os.path.join(root, rel)
    os.makedirs(os.path.dirname(path), exist_ok=True)
    with open(path, "w", encoding="utf-8") as f:
        f.write(text)


class PlanTest(unittest.TestCase):
    def test_order_and_deletes(self):
        local = {"index.html": "a", "build.json": "b", "js/main.js": "c", "posts/x/index.html": "d", "img.webp": "e"}
        remote = {"img.webp": "e", "old/index.html": "z"}
        uploads, deletes = deploy.plan(local, remote)
        self.assertEqual(uploads, ["js/main.js", "index.html", "posts/x/index.html", "build.json"])
        self.assertEqual(deletes, ["old/index.html"])


class SyncTest(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.root = self.tmp.name
        write(self.root, "index.html", "home")
        write(self.root, ".htaccess", "rules")
        write(self.root, "tags/یادداشت/index.html", "tag")
        write(self.root, "build.json", "{}")

    def tearDown(self):
        self.tmp.cleanup()

    def test_first_deploy_uploads_everything_including_dotfiles(self):
        target = FakeTarget()
        deploy.sync(target, self.root, log=lambda *_: None)
        self.assertEqual(target.files, {"index.html", ".htaccess", "tags/یادداشت/index.html", "build.json"})
        self.assertEqual(target.log[-1], ("put", "build.json"))
        self.assertEqual(set(target.state), target.files)

    def test_second_deploy_uploads_only_changes_and_keeps_foreign_files(self):
        target = FakeTarget(extra={"cgi-bin/script", ".well-known/acme"})
        deploy.sync(target, self.root, log=lambda *_: None)
        write(self.root, "index.html", "home v2")
        os.remove(os.path.join(self.root, "tags/یادداشت/index.html"))
        target.log.clear()
        deploy.sync(target, self.root, log=lambda *_: None)
        self.assertEqual(target.log, [("put", "index.html"), ("delete", "tags/یادداشت/index.html")])
        self.assertIn("cgi-bin/script", target.files)
        self.assertIn(".well-known/acme", target.files)

    def test_dry_run_changes_nothing(self):
        target = FakeTarget()
        uploads, _ = deploy.sync(target, self.root, dry_run=True, log=lambda *_: None)
        self.assertEqual(len(uploads), 4)
        self.assertEqual(target.files, set())
        self.assertEqual(target.state, {})


class EnvTest(unittest.TestCase):
    def test_load_env(self):
        with tempfile.NamedTemporaryFile("w", suffix=".env", delete=False) as f:
            f.write("# comment\nFTP_HOST=ftp.example.com\nFTP_PASSWORD='p@ss=word'\nFTP_DIR=\"public_html\"\n\nBAD LINE\n")
        try:
            with mock.patch.dict(os.environ, {"FTP_HOST": "kept"}, clear=False):
                os.environ.pop("FTP_PASSWORD", None)
                os.environ.pop("FTP_DIR", None)
                deploy.load_env(f.name)
                self.assertEqual(os.environ["FTP_HOST"], "kept")
                self.assertEqual(os.environ["FTP_PASSWORD"], "p@ss=word")
                self.assertEqual(os.environ["FTP_DIR"], "public_html")
        finally:
            os.unlink(f.name)


if __name__ == "__main__":
    unittest.main()
