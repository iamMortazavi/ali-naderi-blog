"""Turn the friend's Obsidian vault into Hugo content.

Reads only <vault>/posts/. A note is published only when its properties say
publish: true. Writes one page bundle per post:

    site/content/<lang>/posts/<slug>/index.md  (+ the attachments it uses)

and build/manifest.json, the list of published posts used by the Telegram
sender. Exits with status 1, without writing anything, when a published note
breaks the rules in schema/post.schema.json.

Usage:
    python -m blogtools.build_content --vault ../content-vault
"""

import argparse
import datetime as dt
import json
import os
import re
import shutil
import sys
from dataclasses import dataclass, field

import yaml

from . import REPO_ROOT, post_path, report
from .obsidian import Converter
from .schema import load_schema, validate

try:
    from zoneinfo import ZoneInfo
    TEHRAN = ZoneInfo("Asia/Tehran")
except Exception:  # tz database missing: Iran has used a fixed +03:30 offset since 2022
    TEHRAN = dt.timezone(dt.timedelta(hours=3, minutes=30))

FRONT_MATTER_RE = re.compile(r"\A---[ \t]*\r?\n(.*?)\r?\n---[ \t]*(?:\r?\n|\Z)", re.S)
LANGS = ("fa", "en")
SECTION_TITLES = {"fa": "نوشته‌ها", "en": "Posts"}


@dataclass
class Note:
    rel: str            # path inside the vault, for messages
    abspath: str
    props: dict
    body: str
    lang: str = "fa"
    slug: str = ""
    id: str = ""
    title: str = ""
    date: dt.datetime = None
    errors: list = field(default_factory=list)

    @property
    def path(self):
        return post_path(self.lang, self.slug)

    @property
    def key(self):
        return f"{self.id}:{self.lang}"


def split_front_matter(text):
    m = FRONT_MATTER_RE.match(text)
    if not m:
        return None, text
    props = yaml.safe_load(m.group(1)) or {}
    if not isinstance(props, dict):
        raise ValueError("properties block is not a list of key: value pairs")
    return props, text[m.end():]


def normalize(props):
    """Undo YAML conveniences so values match what the schema expects."""
    out = {}
    for k, v in props.items():
        if isinstance(v, (dt.datetime, dt.date)):
            v = v.isoformat(timespec="minutes") if isinstance(v, dt.datetime) else v.isoformat()
        out[k] = v
    if isinstance(out.get("tags"), str):
        out["tags"] = [t for t in re.split(r"[,،]\s*", out["tags"]) if t.strip()]
    if isinstance(out.get("tags"), list):
        out["tags"] = [str(t).strip().lstrip("#") for t in out["tags"] if str(t).strip()]
    return out


def parse_date(value):
    d = dt.datetime.fromisoformat(value.replace(" ", "T"))
    return d if d.tzinfo else d.replace(tzinfo=TEHRAN)


def iter_files(root, skip_dirs=True):
    for dirpath, dirnames, filenames in os.walk(root):
        dirnames[:] = sorted(d for d in dirnames if not d.startswith("."))
        for name in sorted(filenames):
            if not name.startswith("."):
                yield os.path.join(dirpath, name)


class Resolver:
    """Finds notes and attachments the way Obsidian does: by file name, anywhere in the vault."""

    def __init__(self, vault):
        self.vault = vault
        self.notes = set()
        self.files = {}
        self.posts = {}
        for path in iter_files(vault):
            name = os.path.basename(path)
            if name.lower().endswith(".md"):
                self.notes.add(name[:-3].lower())
            else:
                self.files.setdefault(name.lower(), []).append(path)

    def add_posts(self, notes):
        self.by_id = {(n.id, n.lang): n for n in notes}
        for n in notes:
            stem = os.path.splitext(os.path.basename(n.abspath))[0].lower()
            self.posts.setdefault(stem, []).append(n)

    def note_exists(self, target):
        return os.path.basename(target).removesuffix(".md").lower() in self.notes

    def find_post(self, target, lang):
        stem = os.path.basename(target).removesuffix(".md").lower()
        candidates = self.posts.get(stem, [])
        for n in candidates:
            if n.lang == lang:
                return n
        for n in candidates:
            if (n.id, lang) in self.by_id:
                return self.by_id[(n.id, lang)]
        return candidates[0] if candidates else None

    def find_attachment(self, ref, note_abspath):
        ref = ref.strip()
        if "/" in ref:
            for base in (os.path.dirname(note_abspath), self.vault):
                p = os.path.normpath(os.path.join(base, ref))
                if p.startswith(os.path.abspath(self.vault)) and os.path.isfile(p):
                    return p
        matches = self.files.get(os.path.basename(ref).lower(), [])
        if not matches:
            return None
        here = os.path.dirname(note_abspath)
        return next((p for p in matches if os.path.dirname(p) == here), matches[0])


def load_notes(vault, posts_dir, schema):
    notes, skipped = [], 0
    root = os.path.join(vault, posts_dir)
    if not os.path.isdir(root):
        raise SystemExit(f"posts folder not found: {root}")
    for path in iter_files(root):
        if not path.lower().endswith(".md"):
            continue
        rel = os.path.relpath(path, vault)
        with open(path, encoding="utf-8") as f:
            text = f.read()
        try:
            props, body = split_front_matter(text)
        except (yaml.YAMLError, ValueError) as e:
            note = Note(rel, path, {}, text)
            note.errors.append(f"cannot read properties: {e}")
            notes.append(note)
            continue
        if not props or props.get("publish") is not True:
            skipped += 1
            continue
        props = normalize(props)
        note = Note(rel, path, props, body)
        note.errors.extend(validate(props, schema))
        if not note.errors:
            note.lang = props.get("lang", "fa")
            note.slug = props["slug"]
            note.id = props.get("id", note.slug)
            note.title = props.get("title") or os.path.splitext(os.path.basename(path))[0]
            note.date = parse_date(props["date"])
        notes.append(note)
    return notes, skipped


def check_unique(notes):
    seen_addr, seen_key = {}, {}
    for n in notes:
        if n.errors:
            continue
        for seen, key, what in ((seen_addr, (n.lang, n.slug), "slug"), (seen_key, (n.lang, n.id), "id")):
            if key in seen:
                n.errors.append(f"{what} '{key[1]}' is already used by {seen[key].rel}")
            else:
                seen[key] = n


def hugo_front_matter(note, cover, audio):
    p = note.props
    fm = {
        "title": note.title,
        "date": note.date.isoformat(),
        "slug": note.slug,
        "translationKey": note.id,
        "draft": False,
    }
    if "updated" in p:
        fm["lastmod"] = parse_date(p["updated"]).isoformat()
    if p.get("description"):
        fm["description"] = p["description"].strip()
    if p.get("tags"):
        fm["tags"] = p["tags"]
    if p.get("series"):
        fm["series"] = [p["series"]]
    params = {"id": note.id, "source": note.rel, "toc": p.get("toc", True), "telegram": p.get("telegram", True)}
    if cover:
        params["cover"] = {"image": cover, "alt": p.get("cover_alt", "")}
    if audio:
        params["audio"] = audio
    if p.get("video"):
        params["video"] = p["video"]
    fm["params"] = params
    return fm


def build(vault, out_dir, manifest_path, posts_dir="posts", now=None):
    """Convert the vault. Returns (exit_code, manifest)."""
    now = now or dt.datetime.now(TEHRAN)
    schema = load_schema()
    notes, skipped = load_notes(vault, posts_dir, schema)
    check_unique(notes)
    valid = [n for n in notes if not n.errors]
    resolver = Resolver(vault)
    resolver.add_posts(valid)

    rendered = []
    for n in valid:
        conv = Converter(resolver, n)
        body = conv.convert(n.body)
        cover = conv.attach(n.props["cover"]) if n.props.get("cover") else None
        audio = conv.attach(n.props["audio"]) if n.props.get("audio") else None
        n.errors.extend(conv.errors)
        for w in conv.warnings:
            report("warning", w, n.rel)
        if not n.props.get("description"):
            report("warning", "no description: search engines and Telegram will use the first paragraph", n.rel)
        rendered.append((n, body, cover, conv.attachments, hugo_front_matter(n, cover, audio)))

    failed = [n for n in notes if n.errors]
    for n in failed:
        for e in n.errors:
            report("error", e, n.rel)
    if failed:
        print(f"{len(failed)} note(s) have errors; nothing was written.", file=sys.stderr)
        return 1, None

    for lang in LANGS:
        target = os.path.join(out_dir, lang, "posts")
        shutil.rmtree(target, ignore_errors=True)
        os.makedirs(target)
        with open(os.path.join(target, "_index.md"), "w", encoding="utf-8") as f:
            f.write(f"---\ntitle: {SECTION_TITLES[lang]}\n---\n")

    manifest = {"generated_at": now.isoformat(timespec="seconds"), "posts": []}
    for n, body, cover, attachments, fm in rendered:
        bundle = os.path.join(out_dir, n.lang, "posts", n.slug)
        os.makedirs(bundle)
        with open(os.path.join(bundle, "index.md"), "w", encoding="utf-8") as f:
            f.write("---\n" + yaml.safe_dump(fm, allow_unicode=True, sort_keys=False) + "---\n" + body)
        for name, src in attachments.items():
            shutil.copy2(src, os.path.join(bundle, name))
        cover_url = cover if (cover or "").startswith("http") else (n.path + cover if cover else None)
        manifest["posts"].append({
            "key": n.key, "id": n.id, "lang": n.lang, "slug": n.slug, "title": n.title,
            "description": fm.get("description", ""), "date": n.date.isoformat(),
            "future": n.date > now, "path": n.path, "cover": cover_url,
            "tags": n.props.get("tags", []), "telegram": n.props.get("telegram", True), "source": n.rel,
        })
    manifest["posts"].sort(key=lambda p: p["date"])

    os.makedirs(os.path.dirname(manifest_path), exist_ok=True)
    with open(manifest_path, "w", encoding="utf-8") as f:
        json.dump(manifest, f, ensure_ascii=False, indent=2)
    print(f"published {len(rendered)} post(s), skipped {skipped} private note(s)")
    return 0, manifest


def main(argv=None):
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--vault", required=True, help="path to the content repository (Obsidian vault)")
    ap.add_argument("--posts-dir", default="posts", help="published folder inside the vault")
    ap.add_argument("--out", default=os.path.join(REPO_ROOT, "site", "content"))
    ap.add_argument("--manifest", default=os.path.join(REPO_ROOT, "build", "manifest.json"))
    args = ap.parse_args(argv)
    code, _ = build(args.vault, args.out, args.manifest, args.posts_dir)
    return code


if __name__ == "__main__":
    sys.exit(main())
