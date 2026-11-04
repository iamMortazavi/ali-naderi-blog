"""Local preview: runs Hugo's live server and re-converts the vault whenever a note or attachment changes.

Hugo reloads the browser by itself when site/ changes, including edits saved by the admin panel
in "Work with Local Repository" mode.

Usage:
    python -m blogtools.dev --vault ../content-vault [--port 1313]
"""

import argparse
import os
import subprocess
import sys
import time

from . import REPO_ROOT
from .build_content import build, iter_files


def snapshot(vault):
    """Modification times of every visible file in the vault."""
    state = {}
    for path in iter_files(vault):
        try:
            state[path] = os.stat(path).st_mtime_ns
        except FileNotFoundError:
            pass
    return state


def convert(vault):
    code, _ = build(vault, os.path.join(REPO_ROOT, "site", "content"), os.path.join(REPO_ROOT, "build", "manifest.json"))
    if code:
        print("Fix the notes above; the preview keeps the last good version.", file=sys.stderr)


def main(argv=None):
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--vault", required=True)
    ap.add_argument("--port", default="1313")
    args = ap.parse_args(argv)
    vault = os.path.abspath(args.vault)

    convert(vault)
    server = subprocess.Popen(["hugo", "server", "--source", os.path.join(REPO_ROOT, "site"),
                               "--bind", "127.0.0.1", "--port", args.port, "--disableFastRender"])
    print(f"Watching {vault} for changes. Site: http://localhost:{args.port}/  Admin: http://localhost:{args.port}/admin/")
    last = snapshot(vault)
    try:
        while server.poll() is None:
            time.sleep(1)
            now = snapshot(vault)
            if now != last:
                last = now
                print("Vault changed: converting notes…")
                convert(vault)
    except KeyboardInterrupt:
        pass
    finally:
        server.terminate()
        server.wait()
    return server.returncode or 0


if __name__ == "__main__":
    sys.exit(main())
