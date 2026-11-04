import unittest

from blogtools.schema import SETTINGS_SCHEMA_PATH, load_schema, validate
from blogtools.settings import get, load


class SettingsTest(unittest.TestCase):
    def setUp(self):
        self.schema = load_schema(SETTINGS_SCHEMA_PATH)
        self.settings = load()

    def test_committed_settings_are_valid(self):
        self.assertEqual(validate(self.settings, self.schema), [])

    def test_get(self):
        self.assertTrue(get(self.settings, "site.url").startswith("http"))

    def test_panel_mistakes_are_caught(self):
        bad = dict(self.settings, look="neon", site={"url": "https://x.com/", "title_fa": "", "title_en": "X"},
                   social=[{"name": "myspace", "url": "x"}], menu=[{"label_fa": "a", "label_en": "a", "path": "tags"}])
        text = "\n".join(validate(bad, self.schema))
        for field in ("'look'", "'site.url'", "'site.title_fa'", "'social[0].name'", "'social[0].url'", "'menu[0].path'"):
            self.assertIn(field, text)


if __name__ == "__main__":
    unittest.main()
