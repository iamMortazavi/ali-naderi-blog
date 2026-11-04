# Contributing

Thanks for helping with the blog. This file covers local setup, the checks every change must pass, where things live, commit style and how a release is packaged.

## Local setup

You need PHP 8.4+ (with the extensions listed in the [README](README.md#requirements)), Composer 2 and Git. Node.js is not used.

```bash
composer install
cp .env.example .env
```

`.env.example` holds production defaults. For local work change these first (with `APP_ENV=production`, `key:generate` asks for confirmation):

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
SESSION_SECURE_COOKIE=false   # plain http, or you cannot sign in
LOG_LEVEL=debug
ADMIN_EMAIL=you@example.com
ADMIN_PASSWORD=a-long-local-password
```

### Database: MySQL or MariaDB

The same engine as production. Create an empty database and set:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=ali_naderi_blog
DB_USERNAME=root
DB_PASSWORD=
```

### Database: SQLite (quickest)

```bash
touch database/database.sqlite
```

```dotenv
DB_CONNECTION=sqlite
# remove or comment out the other DB_ lines
```

### Run it

```bash
php artisan key:generate
php artisan migrate --seed                    # tables + first admin from ADMIN_*
php artisan blog:sync --path=example-vault    # import the sample Obsidian vault
php artisan serve                             # http://localhost:8000, panel at /admin
```

Scheduled work (publishing due posts, Telegram, Obsidian sync) runs with `php artisan schedule:work` locally. Telegram stays off unless `TELEGRAM_*` is set; use a test channel, never the real one.

## Checks

Every change must pass:

```bash
composer check
```

which runs, in order:

| Command | What |
| --- | --- |
| `composer lint:check` | Code style, [Laravel Pint](https://laravel.com/docs/pint) with the `laravel` preset (`pint.json`). Fix with `composer lint`. |
| `composer analyse` | Static analysis, [Larastan](https://github.com/larastan/larastan) level 5 on `app/` (`phpstan.neon`). |
| `composer test` | PHPUnit, on SQLite in memory (`phpunit.xml`). |

CI (`.github/workflows/ci.yml`) runs the same commands on PHP 8.4. Static analysis is not blocking in CI yet; do not add new findings.

Guidelines:

- Add or update a test for every behaviour change and every bug fix. Tests live in `tests/Feature/{Admin,Site,Obsidian,Telegram,Console}` and `tests/Unit`.
- Keep the shared-hosting constraints: no Node build step, no queue worker, no SSH-only steps, no long-running processes. Anything periodic goes in `routes/console.php` and runs from `schedule:run`.
- Persian is the panel's language: labels, validation messages and notifications are in Persian (`lang/fa`).
- Never commit secrets. Configuration that changes per installation goes in `.env` (and `.env.example` with a comment); site-owner settings go in the Settings page.

## Where things are

See [Project structure](README.md#project-structure) in the README, and [docs/content-model.md](docs/content-model.md) for the tables and rules (roles, Telegram, redirects, page cache, uploads, Obsidian note properties). Update those documents in the same change when you change the behaviour they describe.

## Commit style

- One logical change per commit; the tree passes `composer check` at every commit.
- Subject: imperative, capitalised, no trailing period, at most ~72 characters. Say what the change does for the site, e.g. `Send only posts from the last 48 hours to Telegram`.
- Body (optional, wrapped at 72): why, and anything a reviewer or the person deploying must know (new `.env` keys, migrations, a step in the install guide).
- Add a line under **Unreleased** in [CHANGELOG.md](CHANGELOG.md) for anything a site owner would notice.

## Releasing

1. Move the **Unreleased** entries in `CHANGELOG.md` under a new version and date ([Keep a Changelog](https://keepachangelog.com/en/1.1.0/), [Semantic Versioning](https://semver.org/)).
2. Commit, then tag: `git tag -a v1.2.0 -m "v1.2.0"`.
3. Build the zip from that commit:

   ```bash
   scripts/package.sh            # → build/blog-release.zip
   ```

   The script refuses to run with uncommitted changes, because the zip is built from `HEAD` with `git archive` (tests, the example vault, CI files and design notes are left out; `.env` and uploads are never included). It then installs production Composer packages and publishes Filament's assets.
   `PACKAGE_ALLOW_DIRTY=1 scripts/package.sh` skips the clean-tree check **for testing the script only**; the zip still contains `HEAD`, not your uncommitted work.
4. Deploy it with the update steps in [docs/deploy-cpanel.fa.md](docs/deploy-cpanel.fa.md) (section «به‌روزرسانی»).
