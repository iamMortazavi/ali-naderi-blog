# Blog site

A bilingual (Persian/English) static blog. Posts are written in Obsidian, built with Hugo on GitHub Actions, uploaded to a cPanel host over FTPS, and announced once on a Telegram channel.

Design and decisions: [docs/designs/bilingual-obsidian-blog.md](docs/designs/bilingual-obsidian-blog.md). Data model: [docs/content-model.md](docs/content-model.md). Operations: [docs/runbook.md](docs/runbook.md).

## Layout

```
.github/
  actions/build-site/  shared build: tests → settings check → vault conversion → Hugo
  workflows/           deploy.yml   production: cPanel over FTPS → verify → Telegram → notify
                       pages.yml    staging: GitHub Pages, hidden from search engines
                       ci.yml       pull requests
.hugo-version          the one place the Hugo version is pinned
.env.example           settings for make deploy (copy to .env)
schema/                post.schema.json (rules for notes), settings.schema.json (rules for panel settings)
tools/blogtools/       build_content.py (vault → Hugo), obsidian.py (link conversion), settings.py,
                       telegram.py (channel posts, sent once), notify.py (maintainer alerts),
                       dev.py (local preview), deploy.py (publish from this computer over FTPS)
tools/tests/           unit tests and a full Hugo build test
site/                  the Hugo project
  config/_default/     hugo.toml, languages.toml, markup.toml, params.toml (maintainer only)
  data/settings.yaml   everything the friend may change, edited from the admin panel
  layouts/             own RTL/LTR theme: pages, _partials/, _markup/ (Obsidian callouts, math, images)
  assets/              CSS (main + looks/paper, looks/ink), JS, default share image
  static/admin/        admin panel (Sveltia CMS)
  content/<lang>/      fixed pages; posts/ is generated and never committed
state/                 telegram-sent.json: which posts were already sent to the channel
example-vault/         a sample content repository: posts, attachments, template, guide, publish workflow
```

## Local development

Requires Hugo extended (version in `.hugo-version`) and Python 3.10+ with PyYAML.

```bash
make serve                         # live preview on http://localhost:1313, admin on /admin/; rebuilds when notes change
make serve VAULT=../content-vault  # the real vault
make test
make deploy-check                  # build and show what would be uploaded
make deploy                        # publish to cPanel over FTPS (settings in .env, see .env.example)
```

The admin panel works locally without any login: open `http://localhost:1313/admin/` in Chrome or Edge, choose "Work with Local Repository" and select this folder. Changes are saved straight into `site/` and the preview reloads.

Preview the other look on any page by adding `?look=ink` to the address.

## What is in v1

- Persian (right-to-left, default, at `/`) and English (left-to-right, at `/en/`); translations linked by a shared `id`.
- Jalali dates and Persian digits on Persian pages; Persian-aware search (ی/ي, ک/ك, half-spaces).
- Code highlighting, math rendered to MathML at build time, Obsidian callouts, highlights, footnotes, tables.
- Podcast audio and video (YouTube, Aparat, files) from note properties or embeds.
- Responsive WebP images with fixed sizes; cover images cropped for link previews.
- SEO: canonical, hreflang with x-default, Open Graph and Twitter cards, sitemap index, RSS per language and tag, robots.txt.
- schema.org JSON-LD: WebSite with search action and Person on the home page; BlogPosting and BreadcrumbList on posts.
- Two looks (paper, ink), each with light and dark mode; switched with one setting.
- Admin panel (Sveltia CMS) for every setting: site address and titles, look, author, menu, links, Telegram format, search-engine verification codes, and the about pages. Settings are checked before each build.
- Telegram channel posts, sent exactly once per post and language, only after the page is live.
- Failure and success messages to a private Telegram chat.
