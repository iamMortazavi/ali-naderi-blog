"""Tell the maintainer (and optionally the friend) how a deploy went, through a private Telegram chat.

Environment: TELEGRAM_BOT_TOKEN, NOTIFY_CHAT_ID.

Usage:
    python -m blogtools.notify --status failure --stage build --run-url https://github.com/...
    python -m blogtools.notify --status success --summary build/telegram-summary.json
"""

import argparse
import html
import json
import os
import sys

from .telegram import call

STAGES_FA = {
    "content": "بررسی یادداشت‌ها (یکی از یادداشت‌ها مشکل دارد)",
    "build": "ساخت سایت",
    "deploy": "آپلود روی هاست",
    "verify": "بررسی سایت بعد از آپلود",
    "telegram": "ارسال به کانال تلگرام",
}


def message(status, stage, run_url, published):
    if status == "success":
        if not published:
            return "✅ سایت به‌روز شد."
        lines = "\n".join(f'• <a href="{html.escape(p["url"])}">{html.escape(p["title"])}</a>' for p in published)
        return f"✅ منتشر شد:\n{lines}"
    where = STAGES_FA.get(stage, stage or "نامشخص")
    text = f"❌ انتشار انجام نشد.\nمرحله: {where}\nStage: {stage}"
    if run_url:
        text += f'\n<a href="{html.escape(run_url)}">جزئیات / details</a>'
    return text


def main(argv=None):
    ap = argparse.ArgumentParser()
    ap.add_argument("--status", choices=["success", "failure"], required=True)
    ap.add_argument("--stage", default="")
    ap.add_argument("--run-url", default="")
    ap.add_argument("--summary", help="JSON list written by blogtools.telegram --summary")
    args = ap.parse_args(argv)

    token, chat_id = os.environ.get("TELEGRAM_BOT_TOKEN"), os.environ.get("NOTIFY_CHAT_ID")
    if not (token and chat_id):
        print("NOTIFY_CHAT_ID or TELEGRAM_BOT_TOKEN not set; skipping notification")
        return 0
    published = []
    if args.summary and os.path.exists(args.summary):
        with open(args.summary, encoding="utf-8") as f:
            published = json.load(f)
    call(token, "sendMessage", {"chat_id": chat_id, "parse_mode": "HTML",
                                "text": message(args.status, args.stage, args.run_url, published),
                                "link_preview_options": {"is_disabled": True}})
    return 0


if __name__ == "__main__":
    sys.exit(main())
