#!/usr/bin/env bash
# Build the site on Vercel (used by vercel.json). Vercel builds on its own machines, so this works
# without GitHub Actions. It does not upload to cPanel or post to Telegram; deploy.yml does that.
#
# Vercel environment variables (Project → Settings → Environment Variables), all optional:
#   SITE_ENVIRONMENT  "staging" (default: hidden from search engines) or "production"
#   CONTENT_REPO      owner/name of the friend's vault; without it example-vault/ is used
#   CONTENT_TOKEN     fine-grained token with Contents: read on CONTENT_REPO
set -euo pipefail
cd "$(dirname "$0")/.."

HUGO_VERSION=$(cat .hugo-version)
if ! command -v hugo >/dev/null || ! hugo version | grep -q "v${HUGO_VERSION}"; then
  echo "Installing Hugo ${HUGO_VERSION}"
  curl -fsSL -o /tmp/hugo.tar.gz \
    "https://github.com/gohugoio/hugo/releases/download/v${HUGO_VERSION}/hugo_extended_${HUGO_VERSION}_linux-amd64.tar.gz"
  tar -xzf /tmp/hugo.tar.gz -C /tmp hugo
  export PATH="/tmp:$PATH"
fi
hugo version

export PYTHONPATH=tools
python3 -m blogtools.settings --check

vault=example-vault
if [ -n "${CONTENT_REPO:-}" ] && [ -n "${CONTENT_TOKEN:-}" ]; then
  rm -rf _vault
  git clone --quiet --depth 1 "https://x-access-token:${CONTENT_TOKEN}@github.com/${CONTENT_REPO}.git" _vault
  vault=_vault
fi
echo "Content: ${vault}"
python3 -m blogtools.build_content --vault "$vault"

# Address: the project's main Vercel domain, or the preview address for preview deployments.
host="${VERCEL_PROJECT_PRODUCTION_URL:-${VERCEL_URL:-localhost:1313}}"
[ "${VERCEL_ENV:-}" = "preview" ] && host="${VERCEL_URL}"
base_url="https://${host}"
environment="${SITE_ENVIRONMENT:-staging}"
echo "Building ${base_url} (${environment})"

hugo --source site --gc --minify --environment "$environment" --baseURL "${base_url}/"

python3 - "$base_url" <<'EOF'
import re, sys
path = "site/public/admin/config.yml"
text = open(path, encoding="utf-8").read()
text = re.sub(r"(?m)^site_url: .*$", f"site_url: {sys.argv[1]}", text)
open(path, "w", encoding="utf-8").write(text)
EOF
printf '{"commit":"%s","built_at":"%s","host":"vercel"}\n' \
  "${VERCEL_GIT_COMMIT_SHA:-local}" "$(date -u +%Y-%m-%dT%H:%M:%SZ)" > site/public/build.json
