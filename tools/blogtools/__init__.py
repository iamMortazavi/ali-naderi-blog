"""Build tools for the blog: Obsidian vault → Hugo content, and Telegram posting."""

import os
import sys

REPO_ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
SCHEMA_PATH = os.path.join(REPO_ROOT, "schema", "post.schema.json")
SETTINGS_PATH = os.path.join(REPO_ROOT, "site", "data", "settings.yaml")

# Address patterns. Must match [permalinks] and the language setup in site/hugo.toml.
LANG_PREFIX = {"fa": "", "en": "/en"}


def post_path(lang, slug):
    return f"{LANG_PREFIX[lang]}/posts/{slug}/"


def report(level, message, file=None):
    """Print a message; in GitHub Actions also emit an annotation."""
    if os.environ.get("GITHUB_ACTIONS") == "true":
        where = f" file={file}" if file else ""
        print(f"::{level}{where}::{message}")
    else:
        prefix = f"{file}: " if file else ""
        print(f"{level.upper()}: {prefix}{message}", file=sys.stderr)
