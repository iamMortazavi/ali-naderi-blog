# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-11-04

First release of the Laravel rebuild. It replaces the earlier Hugo static site and its GitHub Actions / Vercel / FTPS publishing.

### Added

- **Laravel 13 + Filament 5 rebuild** for cPanel shared hosting: MySQL/MariaDB, file cache and sessions, no Node.js, SSH or queue worker. Installs and updates with `php artisan blog:install` from a one-off cron job; scheduled work runs from `schedule:run`.
- Bilingual site: Persian (right-to-left, `/`) and English (`/en`), linked translations, Jalali dates, Persian digits, Persian-aware search, two looks with light and dark mode.
- Persian admin panel: posts, pages, tags, series, users, redirects, sync history, site settings, dashboard widgets.
- **Obsidian import** every 10 minutes from a GitHub repository (fine-grained read-only token) or a server folder: callouts, highlights, math, footnotes, wikilinks and embeds; attachments copied to uploads; a broken note is reported in the panel and skipped while the rest publish; renaming a note never duplicates a post; sync history kept for 90 days.
- **Telegram:** each post is sent to the channel once, automatically only when published within the last 48 hours; delivery state (`sending`, `sent`, `failed`, `skipped`), retries and manual sending from the panel; optional private notices for the owner.
- **SEO:** canonical and hreflang links, Open Graph and Twitter cards with generated 1200×630 images, JSON-LD (WebSite, Person, BlogPosting, BreadcrumbList), sitemap with language alternates, full-text RSS per language and tag, automatic 301 redirects on address changes.
- Whole-page file cache for visitors, flushed on every edit; WebP image variants.
- `scripts/package.sh` builds the release zip from the last commit; Persian cPanel install guide; data model documentation; CI workflow, issue and pull request templates; Pint, Larastan and `composer check`.

### Security

Hardening from the multi-agent review:

- Roles and policies: editors edit only their own panel posts; Obsidian posts are read-only in the panel; users, redirects, sync runs and settings are admin-only.
- Uploads: PHP and scripts disabled in `public/uploads`, HTML and SVG refused, an allow-list of attachment types (no SVG), 100 MB upload limit, failed writes surface as errors.
- HTTPS everywhere with HSTS, secure session cookie, dot-files blocked, security headers.
- Sanitised Markdown HTML; normalised redirect paths; page cache keyed by the configured site address, not the request's Host header.
- Local vault folders restricted to `VAULT_LOCAL_ROOT`; secrets only in `.env`.
- `blog:install` clears cached configuration first, uses maintenance mode while migrating, and on the first install marks existing posts as already sent to Telegram.
- PHP 8.4 required; production `.env` defaults (`APP_DEBUG=false`, daily logs at `warning`).

### Removed

- Hugo build, GitHub Actions deployment, Vercel and FTPS publishing.
- Unused Vite/npm skeleton (`package.json`, `vite.config.js`, `resources/css`, `resources/js`).

[Unreleased]: https://github.com/iamMortazavi/ali-naderi-blog/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/iamMortazavi/ali-naderi-blog/releases/tag/v1.0.0
