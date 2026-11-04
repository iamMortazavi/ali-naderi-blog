import datetime as dt
import io
import json
import os
import tempfile
import unittest
from unittest import mock

from blogtools import telegram

NOW = dt.datetime(2026, 11, 4, 9, 0, tzinfo=dt.timezone.utc)
SETTINGS = {"telegram": {"languages": ["fa"], "hashtags": True, "footer_fa": "@channel"}}


def post(key, lang="fa", date="2026-11-01T10:00:00+03:30", **extra):
    pid = key.split(":")[0]
    p = {"key": key, "id": pid, "lang": lang, "slug": pid, "title": f"عنوان {pid}", "description": "خلاصه",
         "date": date, "future": False, "path": f"/posts/{pid}/", "cover": None, "tags": ["هوش مصنوعی", "Hugo"],
         "telegram": True, "source": f"posts/{pid}.md"}
    p.update(extra)
    return p


class FakeTelegram:
    """Stands in for both the Bot API and the live site."""

    def __init__(self):
        self.calls = []
        self.next_id = 100

    def __call__(self, req, timeout=None):
        url = req.full_url
        if "api.telegram.org" in url:
            method = url.rsplit("/", 1)[1]
            self.calls.append((method, json.loads(req.data)))
            self.next_id += 1
            body = {"ok": True, "result": {"message_id": self.next_id}}
        else:
            body = {}
        resp = mock.MagicMock()
        resp.status = 200
        resp.read.return_value = json.dumps(body).encode()
        resp.__enter__.return_value = resp
        return resp


class TelegramTest(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.state_path = os.path.join(self.tmp.name, "state.json")

    def tearDown(self):
        self.tmp.cleanup()

    def run_once(self, posts, state=None, **kw):
        state = state or {"version": 1, "posts": {}}
        fake = FakeTelegram()
        sent = telegram.run({"posts": posts}, state, SETTINGS, "https://blog.test", "T", "@c", self.state_path,
                            now=NOW, opener=fake, pause=0, **kw)
        return sent, state, fake

    def test_sends_each_post_once(self):
        posts = [post("a:fa"), post("b:fa")]
        sent, state, fake = self.run_once(posts)
        self.assertEqual([p["key"] for p in sent], ["a:fa", "b:fa"])
        self.assertEqual(state["posts"]["a:fa"]["message_id"], 101)
        with open(self.state_path, encoding="utf-8") as f:
            saved = json.load(f)
        sent_again, _, fake2 = self.run_once(posts, saved)
        self.assertEqual(sent_again, [])
        self.assertEqual(fake2.calls, [])

    def test_skips_future_disabled_and_other_languages(self):
        posts = [post("a:fa", date="2026-11-05T10:00:00+03:30"), post("b:fa", telegram=False), post("c:en", lang="en")]
        sent, _, fake = self.run_once(posts)
        self.assertEqual(sent, [])
        self.assertEqual(fake.calls, [])

    def test_message_format(self):
        _, _, fake = self.run_once([post("a:fa")])
        method, payload = fake.calls[0]
        self.assertEqual(method, "sendMessage")
        self.assertEqual(payload["parse_mode"], "HTML")
        text = payload["text"]
        self.assertTrue(text.startswith("<b>عنوان a</b>"))
        self.assertIn("#هوش_مصنوعی #Hugo", text)
        self.assertIn('<a href="https://blog.test/posts/a/">', text)
        self.assertTrue(text.endswith("@channel"))

    def test_cover_uses_photo_with_short_caption(self):
        long = post("a:fa", cover="/posts/a/cover.png", description="و" * 3000)
        _, _, fake = self.run_once([long])
        method, payload = fake.calls[0]
        self.assertEqual(method, "sendPhoto")
        self.assertEqual(payload["photo"], "https://blog.test/posts/a/cover.png")
        self.assertLessEqual(len(payload["caption"]), telegram.CAPTION_LIMIT)

    def test_baseline_marks_without_sending(self):
        sent, state, fake = self.run_once([post("a:fa"), post("b:fa")], baseline=True)
        self.assertEqual(len(sent), 2)
        self.assertEqual(fake.calls, [])
        self.assertTrue(state["posts"]["a:fa"]["baseline"])

    def test_dry_run_changes_nothing(self):
        with mock.patch("sys.stdout", new=io.StringIO()):
            sent, state, fake = self.run_once([post("a:fa")], dry_run=True)
        self.assertEqual(len(sent), 1)
        self.assertEqual(state["posts"], {})
        self.assertFalse(os.path.exists(self.state_path))

    def test_api_error_stops_without_recording(self):
        def failing(req, timeout=None):
            if "api.telegram.org" not in req.full_url:
                return FakeTelegram()(req)
            resp = mock.MagicMock()
            resp.read.return_value = json.dumps({"ok": False, "error_code": 400, "description": "bad"}).encode()
            resp.__enter__.return_value = resp
            return resp
        state = {"version": 1, "posts": {}}
        with self.assertRaises(telegram.TelegramError):
            telegram.run({"posts": [post("a:fa")]}, state, SETTINGS, "https://blog.test", "T", "@c",
                         self.state_path, now=NOW, opener=failing, pause=0)
        self.assertEqual(state["posts"], {})

    def test_hashtag(self):
        self.assertEqual(telegram.hashtag("یادگیری ماشین"), "#یادگیری_ماشین")
        self.assertEqual(telegram.hashtag("نیم‌فاصله"), "#نیم_فاصله")
        self.assertEqual(telegram.hashtag("open-source"), "#open_source")


if __name__ == "__main__":
    unittest.main()
