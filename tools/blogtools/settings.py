"""Check site/data/settings.yaml, or print one value from it for the deploy workflow.

Usage:
    python -m blogtools.settings --check
    python -m blogtools.settings --get site.url
"""

import argparse
import sys

import yaml

from . import SETTINGS_PATH, report
from .schema import SETTINGS_SCHEMA_PATH, load_schema, validate


def load(path=SETTINGS_PATH):
    with open(path, encoding="utf-8") as f:
        return yaml.safe_load(f) or {}


def get(settings, dotted):
    value = settings
    for part in dotted.split("."):
        value = value[part]
    return value


def main(argv=None):
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--file", default=SETTINGS_PATH)
    mode = ap.add_mutually_exclusive_group(required=True)
    mode.add_argument("--check", action="store_true")
    mode.add_argument("--get", metavar="KEY")
    args = ap.parse_args(argv)

    settings = load(args.file)
    errors = validate(settings, load_schema(SETTINGS_SCHEMA_PATH))
    for e in errors:
        report("error", e, "site/data/settings.yaml")
    if errors:
        return 1
    if args.get:
        print(get(settings, args.get))
    else:
        print("settings OK")
    return 0


if __name__ == "__main__":
    sys.exit(main())
