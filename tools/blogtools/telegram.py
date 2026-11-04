"""Send newly published posts to the Telegram channel, exactly once each.

Reads build/manifest.json and state/telegram-sent.json. A post is sent when:
  - telegram is not false and its language is listed in settings telegram.languages,
  - its date is not in the future,
  - its key (<id>:<lang>) is not in the state file,
  - its live URL answers with status 200 (skip with --no-verify).
The state file is saved after every successful message, so a failure halfway
never causes repeats on the next run.

Environment: TELEGRAM_BOT_TOKEN, TELEGRAM_CHAT_ID (channel @name or -100… id).

Usage:
    python -m blogtools.telegram --base-url https://example.com [--dry-run | --baseline]
"""

import argparse
import datetime as dt
import html
import json
import os
import re
import sys
import time
import urllib.error
import urllib.parse
import urllib.request

import yaml

from . import REPO_ROOT, SETTINGS_PATH, report

API = "https://api.telegram.org/bot{token}/{method}"
CAPTION_LIMIT = 1024
MESSAGE_LIMIT = 4096
READ_MORE = {"fa": "ادامهٔ مطلب", "en": "Read more"}


class TelegramError(Exception):
    pass


def call(token, method, payload, opener=urllib.request.urlopen, retries=3):
    """Call the Bot API. Waits and retries when Telegram asks to slow down."""
    data = json.dumps(payload).encode("utf-8")
    for attempt in range(retries):
        req = urllib.request.Request(API.format(token=token, method=method), data=data,
                                     headers={"Content-Type": "application/json"})
        try:
            with opener(req, timeout=30) as resp:
                body = json.loads(resp.read().decode("utf-8"))
        except urllib.error.HTTPError as e:
            body = json.loads(e.read().decode("utf-8") or "{}")
        if body.get("ok"):
            return body["result"]
        retry_after = (body.get("parameters") or {}).get("retry_after")
        if body.get("error_code") == 429 and retry_after and attempt < retries - 1:
            time.sleep(retry_after + 1)
            continue
        raise TelegramError(f"{method} failed: {body.get('error_code')} {body.get('description')}")
    raise TelegramError(f"{method} failed after {retries} attempts")


def hashtag(tag):
    """Telegram hashtags allow letters, digits and _ only; half-spaces and dashes break them."""
    return "#" + re.sub(r"[\s‌\-‌.]+", "_", tag.strip()).strip("_")


def compose(post, url, settings, limit):
    tg = settings.get("telegram", {})
    lang = post["lang"]
    title = f"<b>{html.escape(post['title'])}</b>"
    tags = " ".join(hashtag(t) for t in post.get("tags", [])) if tg.get("hashtags", True) else ""
    link = f'<a href="{html.escape(url)}">{READ_MORE[lang]}</a>'
    footer = html.escape(tg.get(f"footer_{lang}", "") or "")
    tail = "\n\n".join(x for x in (tags, link, footer) if x)
    desc = post.get("description", "")
    room = limit - len(title) - len(tail) - 8
    if len(desc) > room:
        desc = desc[: max(room - 1, 0)].rstrip() + "…"
    return "\n\n".join(x for x in (title, html.escape(desc), tail) if x)


def due_posts(manifest, state, settings, now):
    languages = settings.get("telegram", {}).get("languages", ["fa"])
    for post in manifest["posts"]:
        if post["key"] in state["posts"] or not post.get("telegram", True):
            continue
        if post["lang"] not in languages:
            continue
        if dt.datetime.fromisoformat(post["date"]) > now:
            continue
        yield post


def is_live(url, opener=urllib.request.urlopen):
    try:
        with opener(urllib.request.Request(url, method="GET"), timeout=20) as resp:
            return resp.status == 200
    except (urllib.error.URLError, TimeoutError):
        return False


def load_json(path, default):
    if not os.path.exists(path):
        return default
    with open(path, encoding="utf-8") as f:
        return json.load(f)


def save_state(path, state):
    os.makedirs(os.path.dirname(path), exist_ok=True)
    tmp = path + ".tmp"
    with open(tmp, "w", encoding="utf-8") as f:
        json.dump(state, f, ensure_ascii=False, indent=2, sort_keys=True)
        f.write("\n")
    os.replace(tmp, path)


def run(manifest, state, settings, base_url, token, chat_id, state_path, *, dry_run=False,
        baseline=False, verify=True, now=None, opener=urllib.request.urlopen, pause=3):
    """Send due posts. Returns the list of posts sent (or that would be sent)."""
    now = now or dt.datetime.now(dt.timezone.utc)
    sent = []
    for post in due_posts(manifest, state, settings, now):
        url = base_url.rstrip("/") + post["path"]
        if baseline:
            state["posts"][post["key"]] = {"message_id": None, "sent_at": None, "url": url, "baseline": True}
            save_state(state_path, state)
            sent.append(post)
            continue
        if verify and not dry_run and not is_live(url, opener):
            report("warning", f"{url} is not live yet; will retry on the next run", post["source"])
            continue
        cover = post.get("cover")
        if cover and not cover.startswith("http"):
            cover = base_url.rstrip("/") + cover
        if dry_run:
            print(f"--- would send {post['key']} ---\n{compose(post, url, settings, CAPTION_LIMIT if cover else MESSAGE_LIMIT)}\n")
            sent.append(post)
            continue
        if cover:
            result = call(token, "sendPhoto", {"chat_id": chat_id, "photo": cover, "parse_mode": "HTML",
                                               "caption": compose(post, url, settings, CAPTION_LIMIT)}, opener)
        else:
            result = call(token, "sendMessage", {"chat_id": chat_id, "parse_mode": "HTML",
                                                 "text": compose(post, url, settings, MESSAGE_LIMIT),
                                                 "link_preview_options": {"url": url, "prefer_large_media": True}},
                          opener)
        state["posts"][post["key"]] = {"message_id": result["message_id"],
                                       "sent_at": now.isoformat(timespec="seconds"), "url": url}
        save_state(state_path, state)
        sent.append(post)
        print(f"sent {post['key']} → message {result['message_id']}")
        time.sleep(pause)
    return sent


def main(argv=None):
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--base-url", required=True)
    ap.add_argument("--manifest", default=os.path.join(REPO_ROOT, "build", "manifest.json"))
    ap.add_argument("--state", default=os.path.join(REPO_ROOT, "state", "telegram-sent.json"))
    ap.add_argument("--settings", default=SETTINGS_PATH)
    ap.add_argument("--summary", help="write the list of sent posts here as JSON")
    mode = ap.add_mutually_exclusive_group()
    mode.add_argument("--dry-run", action="store_true", help="print messages, send nothing, change nothing")
    mode.add_argument("--baseline", action="store_true", help="mark all current posts as sent without sending")
    ap.add_argument("--no-verify", dest="verify", action="store_false", help="do not check that URLs are live")
    args = ap.parse_args(argv)

    manifest = load_json(args.manifest, None)
    if manifest is None:
        raise SystemExit(f"manifest not found: {args.manifest}; run build_content first")
    state = load_json(args.state, {"version": 1, "posts": {}})
    with open(args.settings, encoding="utf-8") as f:
        settings = yaml.safe_load(f) or {}
    token, chat_id = os.environ.get("TELEGRAM_BOT_TOKEN"), os.environ.get("TELEGRAM_CHAT_ID")
    if not (args.dry_run or args.baseline) and not (token and chat_id):
        raise SystemExit("TELEGRAM_BOT_TOKEN and TELEGRAM_CHAT_ID must be set")

    sent = run(manifest, state, settings, args.base_url, token, chat_id, args.state,
               dry_run=args.dry_run, baseline=args.baseline, verify=args.verify)
    if args.summary:
        with open(args.summary, "w", encoding="utf-8") as f:
            json.dump([{"title": p["title"], "url": args.base_url.rstrip("/") + p["path"]} for p in sent],
                      f, ensure_ascii=False)
    verb = "marked as sent" if args.baseline else "would be sent" if args.dry_run else "sent"
    print(f"{len(sent)} post(s) {verb}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
