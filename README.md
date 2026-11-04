# Ali Naderi Blog

![PHP 8.4+](https://img.shields.io/badge/PHP-8.4%2B-777bb4?logo=php&logoColor=white)
![Laravel 13](https://img.shields.io/badge/Laravel-13-ff2d20?logo=laravel&logoColor=white)
![Filament 5](https://img.shields.io/badge/Filament-5-fdae4b)
![License: proprietary](https://img.shields.io/badge/license-proprietary-lightgrey)

A bilingual Persian/English blog built for **cPanel shared hosting**: Laravel 13, a Persian Filament 5 admin panel, MySQL, and posts written either in the panel or in **Obsidian** (imported from a GitHub repository). New posts are announced once on a **Telegram** channel.

It installs and updates without SSH, Node.js or a queue worker: upload a zip, run one cron command, keep one scheduler cron.

## Features

- **Two languages:** Persian (right-to-left, at `/`) and English (left-to-right, at `/en`), with translations linked to each other.
- **Writing:** Markdown with Obsidian features: callouts, `==highlight==`, math, footnotes, tables, highlighted code, `[[wikilinks]]` and `![[embeds]]`; scheduled posts, series, tags, table of contents, audio and video.
- **Obsidian import:** every 10 minutes from a GitHub repository (read-only token) or a folder on the server. A broken note is reported in the panel and skipped; the rest still publish. Renaming a note never creates a duplicate post.
- **Admin panel (Persian):** posts, pages, tags, series, users with admin/editor roles, redirects, sync history, and site settings (titles, author, menu, social links, look, SEO, Telegram, Obsidian).
- **Look:** two themes (paper, ink), each with light and dark mode; Jalali dates and Persian digits; search that treats ي/ی and ك/ک alike.
- **SEO:** canonical and hreflang links, Open Graph and Twitter cards with 1200×630 images, schema.org JSON-LD (WebSite, Person, BlogPosting, BreadcrumbList), sitemap with language alternates, full-text RSS per language and tag, automatic 301 redirects when an address changes.
- **Telegram:** each new post is sent to the channel once, in a fixed format, with retries and a status in the panel.
- **Speed:** public pages carry no cookies and are served from a whole-page file cache; images are resized to WebP.
- **Security:** HTTPS with HSTS, script execution disabled in uploads, no SVG uploads, sanitised HTML, role-based policies, secrets only in `.env`.

## Requirements

| | Production (cPanel) | Development |
| --- | --- | --- |
| PHP | 8.4+ with `intl`, `gd`, `pdo_mysql`, `mbstring`, `fileinfo`, `zip`, `openssl`, `ctype`, `dom`, `tokenizer`, `xmlreader`, `iconv`, `session`, `filter`, `curl` | Same, plus `pdo_sqlite` for tests |
| Database | MySQL 8 or MariaDB 10.6+ | MySQL/MariaDB, or SQLite |
| Web server | Apache (`mod_rewrite`, `mod_headers`) | `php artisan serve` |
| Other | Cron jobs | Composer 2, Git |

Node.js is not needed: the site's CSS and JavaScript are plain files in `public/site/`.

## Quick start

```bash
git clone https://github.com/iamMortazavi/ali-naderi-blog.git blog && cd blog
composer install
cp .env.example .env
```

Edit `.env` for local development: `APP_ENV=local`, `APP_DEBUG=true`, `APP_URL=http://localhost:8000`, `SESSION_SECURE_COOKIE=false`, your database settings (MySQL, or `DB_CONNECTION=sqlite`), and `ADMIN_EMAIL` / `ADMIN_PASSWORD`. Then:

```bash
php artisan key:generate
php artisan migrate --seed
php artisan blog:sync --path=example-vault   # import the sample Obsidian vault
php artisan serve
```

- Site: <http://localhost:8000> (English: `/en`). Preview a look on any page with `?look=ink`.
- Admin panel: <http://localhost:8000/admin>, signing in with `ADMIN_EMAIL` and `ADMIN_PASSWORD`.

## Checks

```bash
composer check        # everything CI runs: code style, static analysis, tests
composer lint         # fix code style (Laravel Pint)
composer lint:check   # check code style only
composer analyse      # Larastan (PHPStan level 5)
composer test         # PHPUnit
```

Tests run on SQLite in memory (see `phpunit.xml`); no database setup is needed.

## Deployment

The site is deployed by uploading a zip built from the last commit:

```bash
scripts/package.sh    # → build/blog-release.zip
```

Then follow the step-by-step guide: [**cPanel install and update guide (Persian)**](docs/deploy-cpanel.fa.md).

## Project structure

```
app/
  Console/Commands/         blog:install (install/update), blog:sync (Obsidian), blog:publish-due (schedule + Telegram)
  Enums/                    PostStatus, PostSource, UserRole, TelegramStatus, SyncStatus, SyncDriver
  Filament/                 admin panel: Resources (posts, pages, tags, series, users, redirects, sync runs),
                            Pages/Settings, Widgets
  Http/Controllers/Site/    one controller per page type: Home, Post, Page, Archive, Taxonomy, Search, Feed, Sitemap, NotFound
  Http/Middleware/          CachePublicPages, SetSiteLocale
  Models/                   Post, Tag, Series, Page, Setting, TelegramMessage, Redirect, SyncRun, User
  Policies/                 who may do what in the panel (admin / editor)
  Services/Content/         MarkdownRenderer, HtmlPolicy (sanitising), ImageVariants (WebP and share images)
  Services/Obsidian/        VaultFactory (which vault), VaultSync (one import run), NoteImporter (note → post),
                            Note (property rules), LinkConverter, GitHubVault, LocalVault
  Services/Telegram/        TelegramPublisher (send once, retries, notices)
  Support/                  SiteSettings, Seo (meta + JSON-LD), PageCache, Jalali, Persian, Locales, Url, Assets
config/blog.php             blog behaviour fixed per installation (Telegram limits, vault root, …)
database/                   migrations, seeders, factories
docs/                       data model, install guide, design history
example-vault/              a sample Obsidian vault for trying the import
lang/                       Persian and English strings
public/site/                the public site's CSS, JavaScript, font and math renderer
resources/views/site/       public templates
routes/                     site.php (public addresses), console.php (scheduler)
scripts/package.sh          build the release zip for cPanel
tests/                      Admin, Site, Obsidian, Telegram, Console (feature) and Unit
```

## Documentation

- [Data model](docs/content-model.md): tables, roles, Telegram states, redirects, page cache, uploads, Obsidian note properties.
- [cPanel install and update guide (Persian)](docs/deploy-cpanel.fa.md): requirements, cron jobs, Obsidian, Telegram, backups, troubleshooting.
- [Contributing](CONTRIBUTING.md): local setup, checks, commit style, releases.
- [Changelog](CHANGELOG.md).
- [Original design](docs/designs/bilingual-obsidian-blog.md): the first Hugo-based design, kept for history (superseded).

## Security

- Secrets (database password, Telegram bot token, GitHub token) live only in `.env`, which is never committed or packaged.
- The GitHub token for the vault should be fine-grained, limited to the vault repository, with **Contents: Read-only**.
- Uploaded files are served from `public/uploads`, where `.htaccess` disables PHP and scripts and refuses HTML and SVG.
- Please report security problems privately to the maintainer rather than in a public issue.

## License

Proprietary. All rights reserved; no license is granted to use, copy or distribute this code without the owner's permission.
