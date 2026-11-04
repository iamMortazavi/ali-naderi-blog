"""Build the site on this computer and publish it to the cPanel host over FTPS.

This does the same job as .github/workflows/deploy.yml, for when GitHub Actions is not available.
Stages: settings → notes → build → upload → verify → Telegram → notify.

Uploads only files whose content changed. The list of uploaded files and their hashes is kept on the
host in .deploy-state.json, so files this tool never uploaded (cgi-bin, .well-known, …) are never deleted.
Order: assets first, then pages, then build.json last, so readers never get a page whose images are missing.

Settings come from .env in the repository root (see .env.example); environment variables win.

Usage:
    python -m blogtools.deploy [--dry-run] [--no-telegram] [--full]
"""

import argparse
import datetime as dt
import ftplib
import hashlib
import io
import json
import os
import re
import subprocess
import sys
import time
import urllib.request

from . import REPO_ROOT, report
from . import notify as notify_mod
from . import settings as settings_mod
from . import telegram as telegram_mod
from .build_content import build as build_content

PUBLIC = os.path.join(REPO_ROOT, "site", "public")
STATE_NAME = ".deploy-state.json"
LAST = ("build.json",)
PAGE_EXT = (".html", ".xml", ".json", ".txt")


class StageError(Exception):
    def __init__(self, stage, message):
        super().__init__(message)
        self.stage = stage


# ---------------------------------------------------------------- configuration

def load_env(path=os.path.join(REPO_ROOT, ".env")):
    """Read KEY=VALUE lines into os.environ without overriding variables that are already set."""
    if not os.path.exists(path):
        return
    with open(path, encoding="utf-8") as f:
        for line in f:
            line = line.strip()
            if not line or line.startswith("#") or "=" not in line:
                continue
            key, _, value = line.partition("=")
            value = value.strip()
            if len(value) >= 2 and value[0] == value[-1] and value[0] in "'\"":
                value = value[1:-1]
            os.environ.setdefault(key.strip(), value)


def need(*names):
    missing = [n for n in names if not os.environ.get(n)]
    if missing:
        raise StageError("setup", f"missing in .env: {', '.join(missing)}")


# ---------------------------------------------------------------- planning

def local_files(root):
    """Map of relative path → sha256 for every file in the built site, dotfiles included."""
    files = {}
    for dirpath, _, names in os.walk(root):
        for name in names:
            full = os.path.join(dirpath, name)
            rel = os.path.relpath(full, root).replace(os.sep, "/")
            with open(full, "rb") as f:
                files[rel] = hashlib.sha256(f.read()).hexdigest()
    return files


def plan(local, remote):
    """Return (uploads, deletes). Uploads are ordered: assets, pages, then build.json."""
    changed = [p for p, h in local.items() if remote.get(p) != h]

    def rank(path):
        if path in LAST:
            return 2
        return 1 if path.endswith(PAGE_EXT) else 0

    uploads = sorted(changed, key=lambda p: (rank(p), p))
    deletes = sorted(p for p in remote if p not in local)
    return uploads, deletes


# ---------------------------------------------------------------- transport

class FtpsTarget:
    """The site folder on the host, reached over explicit FTPS (FTP over TLS)."""

    def __init__(self, host, user, password, base, port=21, tls=True):
        self.args = (host, user, password, int(port))
        self.tls = tls
        self.base = "/" + base.strip("/") if base.strip("/") else "/"
        self.dirs = set()
        self.ftp = None
        self.connect()

    def connect(self):
        host, user, password, port = self.args
        ftp = ftplib.FTP_TLS(timeout=60, encoding="utf-8") if self.tls else ftplib.FTP(timeout=60, encoding="utf-8")
        ftp.connect(host, port)
        ftp.login(user, password)
        if self.tls:
            ftp.prot_p()
        try:
            ftp.sendcmd("OPTS UTF8 ON")
        except ftplib.all_errors:
            pass
        ftp.set_pasv(True)
        ftp.cwd(self.base)
        self.ftp = ftp
        self.dirs = set()

    def _retry(self, fn):
        try:
            return fn()
        except (ftplib.error_temp, ftplib.error_reply, OSError, EOFError):
            self.connect()
            return fn()

    def read_state(self):
        buf = io.BytesIO()
        try:
            self._retry(lambda: self.ftp.retrbinary(f"RETR {STATE_NAME}", buf.write))
        except ftplib.error_perm:
            return {}
        return json.loads(buf.getvalue().decode("utf-8")).get("files", {})

    def write_state(self, files):
        data = json.dumps({"files": files, "written_at": dt.datetime.now(dt.timezone.utc).isoformat()}).encode()
        self._retry(lambda: self.ftp.storbinary(f"STOR {STATE_NAME}", io.BytesIO(data)))

    def ensure_dir(self, rel_dir):
        parts = [p for p in rel_dir.split("/") if p]
        for i in range(1, len(parts) + 1):
            d = "/".join(parts[:i])
            if d in self.dirs:
                continue
            try:
                self._retry(lambda: self.ftp.mkd(d))
            except ftplib.error_perm:
                pass  # already exists
            self.dirs.add(d)

    def put(self, rel, local_path):
        self.ensure_dir(os.path.dirname(rel))

        def send():
            with open(local_path, "rb") as f:
                self.ftp.storbinary(f"STOR {rel}", f)
        self._retry(send)

    def delete(self, rel):
        try:
            self._retry(lambda: self.ftp.delete(rel))
        except ftplib.error_perm:
            pass  # already gone

    def close(self):
        try:
            self.ftp.quit()
        except ftplib.all_errors:
            pass


def sync(target, root, *, full=False, dry_run=False, log=print):
    """Upload changed files, delete files from the previous deploy that no longer exist, save the new state."""
    local = local_files(root)
    remote = {} if full else target.read_state()
    uploads, deletes = plan(local, remote)
    log(f"{len(uploads)} file(s) to upload, {len(deletes)} to delete, {len(local) - len(uploads)} unchanged")
    if dry_run:
        for p in uploads:
            log(f"  + {p}")
        for p in deletes:
            log(f"  - {p}")
        return uploads, deletes
    done = dict((p, h) for p, h in remote.items() if p in local and p not in uploads)
    for i, rel in enumerate(uploads, 1):
        target.put(rel, os.path.join(root, rel))
        done[rel] = local[rel]
        if i % 50 == 0:
            log(f"  {i}/{len(uploads)}")
            target.write_state(done)  # a later run resumes from here if this one stops
    for rel in deletes:
        target.delete(rel)
    target.write_state(done)
    return uploads, deletes


# ---------------------------------------------------------------- stages

def git(*args, cwd=REPO_ROOT):
    try:
        return subprocess.run(["git", *args], cwd=cwd, capture_output=True, text=True, check=True).stdout.strip()
    except (subprocess.CalledProcessError, FileNotFoundError):
        return ""


def build_site(vault, base_url):
    code, manifest = build_content(vault, os.path.join(REPO_ROOT, "site", "content"),
                                   os.path.join(REPO_ROOT, "build", "manifest.json"))
    if code:
        raise StageError("content", "a note breaks the rules; see the errors above")
    result = subprocess.run(["hugo", "--source", os.path.join(REPO_ROOT, "site"), "--gc", "--minify",
                             "--cleanDestinationDir", "--environment", "production", "--baseURL", base_url + "/"])
    if result.returncode:
        raise StageError("build", "hugo failed")

    remote = git("remote", "get-url", "origin")
    m = re.search(r"github\.com[:/](.+?)(?:\.git)?$", remote)
    admin = os.path.join(PUBLIC, "admin", "config.yml")
    text = open(admin, encoding="utf-8").read()
    if m:
        text = re.sub(r"(?m)^  repo: .*$", f"  repo: {m.group(1)}", text)
    text = re.sub(r"(?m)^site_url: .*$", f"site_url: {base_url}", text)
    open(admin, "w", encoding="utf-8").write(text)

    build_id = hashlib.sha1(os.urandom(16)).hexdigest()[:12]
    info = {"build": build_id, "commit": git("rev-parse", "HEAD"), "dirty": bool(git("status", "--porcelain")),
            "content": git("rev-parse", "HEAD", cwd=vault), "built_at": dt.datetime.now(dt.timezone.utc).isoformat(),
            "host": "local"}
    with open(os.path.join(PUBLIC, "build.json"), "w", encoding="utf-8") as f:
        json.dump(info, f)
    return build_id, manifest


def verify(base_url, build_id, tries=6, wait=5):
    url = f"{base_url}/build.json?nocache={int(time.time())}"
    for _ in range(tries):
        try:
            with urllib.request.urlopen(url, timeout=20) as resp:
                if json.loads(resp.read().decode()).get("build") == build_id:
                    return
        except Exception:  # noqa: BLE001 - any network or parse problem means "not yet"
            pass
        time.sleep(wait)
    raise StageError("verify", f"{base_url}/build.json does not show this build; check FTP_DIR and the domain")


def notify(status, stage="", published=()):
    token, chat = os.environ.get("TELEGRAM_BOT_TOKEN"), os.environ.get("NOTIFY_CHAT_ID")
    if not (token and chat):
        return
    try:
        telegram_mod.call(token, "sendMessage", {"chat_id": chat, "parse_mode": "HTML",
                                                 "text": notify_mod.message(status, stage, "", list(published)),
                                                 "link_preview_options": {"is_disabled": True}})
    except Exception as e:  # noqa: BLE001 - a failed notice must not hide the real result
        report("warning", f"could not send the maintainer notice: {e}")


def run(args):
    load_env()
    stage = "setup"
    try:
        settings = settings_mod.load()
        errors = settings_mod.validate(settings, settings_mod.load_schema(settings_mod.SETTINGS_SCHEMA_PATH))
        if errors:
            for e in errors:
                report("error", e, "site/data/settings.yaml")
            raise StageError("setup", "site/data/settings.yaml is invalid")
        base_url = settings["site"]["url"].rstrip("/")
        vault = os.path.abspath(os.path.join(REPO_ROOT, os.environ.get("VAULT", "example-vault")))
        if not args.dry_run:
            need("FTP_HOST", "FTP_USER", "FTP_PASSWORD")

        stage = "build"
        print(f"Building {base_url} from {vault}")
        build_id, manifest = build_site(vault, base_url)

        stage = "deploy"
        if args.dry_run and not os.environ.get("FTP_PASSWORD"):
            print(f"dry run: {len(local_files(PUBLIC))} files built in site/public; no FTP settings, nothing compared")
            return 0
        tls = os.environ.get("FTP_TLS", "true").lower() not in ("0", "false", "no")
        if not tls:
            report("warning", "FTP_TLS is off: the password and files travel unencrypted")
        target = FtpsTarget(os.environ["FTP_HOST"], os.environ["FTP_USER"], os.environ["FTP_PASSWORD"],
                            os.environ.get("FTP_DIR", "public_html"), os.environ.get("FTP_PORT", "21"), tls)
        try:
            sync(target, PUBLIC, full=args.full, dry_run=args.dry_run)
        finally:
            target.close()
        if args.dry_run:
            return 0

        stage = "verify"
        verify(base_url, build_id)
        print(f"live: {base_url}")

        published = []
        if not args.no_telegram and os.environ.get("TELEGRAM_BOT_TOKEN") and os.environ.get("TELEGRAM_CHAT_ID"):
            stage = "telegram"
            state_path = os.path.join(REPO_ROOT, "state", "telegram-sent.json")
            state = telegram_mod.load_json(state_path, {"version": 1, "posts": {}})
            sent = telegram_mod.run(manifest, state, settings, base_url, os.environ["TELEGRAM_BOT_TOKEN"],
                                    os.environ["TELEGRAM_CHAT_ID"], state_path)
            published = [{"title": p["title"], "url": base_url + p["path"]} for p in sent]
            if sent:
                print("Telegram record updated: commit and push state/telegram-sent.json")
        notify("success", published=published)
        return 0
    except (StageError, telegram_mod.TelegramError, ftplib.all_errors) as e:
        stage = getattr(e, "stage", stage)
        report("error", f"[{stage}] {e}")
        notify("failure", stage)
        return 1


def main(argv=None):
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--dry-run", action="store_true", help="build only; show nothing would be uploaded")
    ap.add_argument("--no-telegram", action="store_true", help="do not post new articles to the channel")
    ap.add_argument("--full", action="store_true", help="upload every file, ignoring the record on the host")
    return run(ap.parse_args(argv))


if __name__ == "__main__":
    sys.exit(main())
