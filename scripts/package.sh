#!/usr/bin/env bash
# Build build/blog-release.zip: everything the cPanel host needs, ready to upload with File Manager.
# Contains the committed code plus production-only Composer packages. Never contains .env or uploads.
# Usage: scripts/package.sh
set -euo pipefail
cd "$(dirname "$0")/.."

out="build/blog-release.zip"
stage="$(mktemp -d)/blog"
mkdir -p "$stage" build

git ls-files -z --cached --others --exclude-standard -- . ':!example-vault' ':!tests' | xargs -0 -I{} rsync -R "{}" "$stage/"
composer install --no-dev --optimize-autoloader --no-interaction --quiet --working-dir="$stage"
mkdir -p "$stage/storage/framework/"{cache/data,sessions,views} "$stage/storage/logs" "$stage/storage/app/vault-cache"
rm -f "$out"
(cd "$(dirname "$stage")" && zip -qr - blog) > "$out"
rm -rf "$(dirname "$stage")"
echo "$out ($(du -h "$out" | cut -f1))"
