# Ali Naderi Blog

A bilingual Persian/English blog for cPanel hosting: Laravel 13, a Filament 5 admin panel in Persian, MySQL, and posts written either in the panel or in Obsidian.

- Persian (right-to-left, at `/`) and English (left-to-right, at `/en`), with translations linked to each other.
- Two looks (paper, ink), each with light and dark mode, chosen in the panel.
- Jalali dates, Persian digits, search that treats ي/ی and ك/ک alike.
- Markdown with Obsidian features: callouts, `==highlight==`, math, footnotes, tables, highlighted code, wikilinks and embeds.
- SEO: canonical and hreflang links, Open Graph and Twitter cards with 1200×630 images, schema.org JSON-LD (WebSite, Person, BlogPosting, BreadcrumbList), sitemap with language alternates, full-text RSS per language and tag, automatic 301 redirects when an address changes.
- Telegram: each new post is sent to the channel once, in a fixed format.
- Speed: public pages carry no cookies and are served from a whole-page cache; images are resized to WebP.

Documents: [data model](docs/content-model.md) · [cPanel install guide (Persian)](docs/deploy-cpanel.fa.md) · [original design](docs/designs/bilingual-obsidian-blog.md)

## Layout

```
app/
  Models/                 Post, Tag, Series, Page, Setting, TelegramMessage, Redirect, SyncRun, User
  Services/Content/       MarkdownRenderer (Markdown → HTML), ImageVariants (WebP and share images)
  Services/Obsidian/      VaultSync (import), Note (property rules), LinkConverter, LocalVault, GitHubVault
  Services/Telegram/      TelegramPublisher (send once, notices)
  Support/                SiteSettings, Seo (meta + JSON-LD), Jalali, Persian, Locales, PageCache, Assets
  Http/Controllers/Site/  public pages, feeds, sitemap
  Filament/               admin panel: Resources (posts, pages, tags, series, users, redirects, sync runs),
                          Pages/Settings, Widgets
  Console/Commands/       blog:sync, blog:publish-due, blog:install
resources/views/site/     public templates
public/site/              CSS (main + looks), JS, font, math renderer
routes/site.php           public addresses
example-vault/            a sample Obsidian vault for trying the import
scripts/package.sh        build the zip to upload to cPanel
```

## Local development

Needs PHP 8.3+, Composer and MySQL.

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
php artisan blog:sync --path=example-vault
php artisan serve
```

- Site: `http://localhost:8000`
- Admin panel: `http://localhost:8000/admin`. It signs in with `ADMIN_EMAIL` and `ADMIN_PASSWORD` from `.env` (set them before `migrate --seed`).
- Preview a look on any page: add `?look=ink`.

```bash
php artisan test
```
