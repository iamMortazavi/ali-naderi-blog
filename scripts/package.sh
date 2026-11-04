#!/usr/bin/env bash
#
# Build build/blog-release.zip: everything a cPanel host needs, ready to upload with
# File Manager and extract next to public_html (it unpacks into a "blog" folder).
#
# The release is built from the last commit (git archive HEAD), never from the working
# tree, so a release always matches a commit. It contains the code plus production-only
# Composer packages, and never contains .env, uploaded files, tests, the example vault,
# CI files or design notes (see "export-ignore" in .gitattributes and EXCLUDES below).
#
# Usage:   scripts/package.sh
# Needs:   git, composer, PHP 8.4+ (with the extensions in docs/deploy-cpanel.fa.md), zip
#
# PACKAGE_ALLOW_DIRTY=1 skips the "uncommitted changes" check. For testing this script
# only: the zip is still built from HEAD, so uncommitted work is NOT in it.

set -euo pipefail

cd "$(dirname "$0")/.."
root="$(pwd)"
out="$root/build/blog-release.zip"

# Paths left out of the release even if .gitattributes was not committed yet.
EXCLUDES=(example-vault tests .github docs/designs)

fail() { echo "package.sh: $*" >&2; exit 1; }

for tool in git composer zip php; do
    command -v "$tool" >/dev/null 2>&1 || fail "'$tool' is not installed or not on PATH."
done
git rev-parse --verify --quiet HEAD >/dev/null || fail "no commit to package yet."

if [[ -n "$(git status --porcelain --untracked-files=normal)" ]]; then
    if [[ "${PACKAGE_ALLOW_DIRTY:-}" == "1" ]]; then
        echo "package.sh: WARNING: uncommitted changes are NOT included (PACKAGE_ALLOW_DIRTY=1, testing only)." >&2
    else
        git status --short >&2
        fail "the working tree has uncommitted changes. Commit them first; the release is built from the last commit."
    fi
fi

commit="$(git rev-parse --short HEAD)"
work="$(mktemp -d "${TMPDIR:-/tmp}/blog-package.XXXXXX")"
trap 'rm -rf "$work"' EXIT
stage="$work/blog"
mkdir -p "$stage" "$root/build"

# 1. Committed files only.
pathspec=(.)
for path in "${EXCLUDES[@]}"; do
    pathspec+=(":(exclude)$path")
done
git archive --format=tar HEAD -- "${pathspec[@]}" | tar -x -C "$stage"

# 2. Writable folders Laravel expects (empty folders are not stored in git).
mkdir -p \
    "$stage/storage/app/private" \
    "$stage/storage/app/vault-cache" \
    "$stage/storage/framework/cache/data" \
    "$stage/storage/framework/sessions" \
    "$stage/storage/framework/views" \
    "$stage/storage/logs" \
    "$stage/bootstrap/cache" \
    "$stage/public/uploads"

# 3. Production dependencies with an optimised autoloader. Composer's post-autoload-dump
#    scripts run package discovery and `filament:upgrade`, which publishes the panel's
#    CSS/JS/fonts into public/ (they are generated, so they are not committed).
composer install \
    --working-dir="$stage" \
    --no-dev --optimize-autoloader \
    --no-interaction --no-progress
if [[ ! -d "$stage/public/js/filament" ]]; then
    fail "Filament assets were not published (public/js/filament missing)."
fi

# 4. Safety checks: secrets and test data must never ship; uploads must stay script-free.
for path in .env "${EXCLUDES[@]}"; do
    if [[ -e "$stage/$path" ]]; then
        fail "$path ended up in the release."
    fi
done
if [[ ! -f "$stage/public/uploads/.htaccess" ]]; then
    if [[ "${PACKAGE_ALLOW_DIRTY:-}" == "1" ]]; then
        echo "package.sh: WARNING: public/uploads/.htaccess is not committed; this test zip lacks it." >&2
    else
        fail "public/uploads/.htaccess is missing from the last commit; uploads would allow scripts."
    fi
fi

# 5. Zip with a top-level "blog" folder.
rm -f "$out"
(cd "$work" && zip -qr -X "$out" blog)

echo "Built $out from commit $commit ($(du -h "$out" | cut -f1))"
