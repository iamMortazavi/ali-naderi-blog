# Data model

The blog is a Laravel 13 application with a Filament 5 admin panel and a MySQL database. The schema is in [`database/migrations/2026_11_04_000001_create_blog_tables.php`](../database/migrations/2026_11_04_000001_create_blog_tables.php).

## Tables

| Table | One row is | Written by |
| --- | --- | --- |
| `users` | A panel account. `role` is `admin` or `editor`; see [Roles](#roles). | Panel (admins), `blog:install` (first admin) |
| `posts` | One post in one language. | Panel, Obsidian sync |
| `tags`, `post_tag` | A tag in one language, and which posts carry it. | Panel, Obsidian sync |
| `series` | A multi-part series in one language. | Panel, Obsidian sync |
| `pages` | A fixed page such as "about", per language. Address `/{slug}` or `/en/{slug}`. | Panel |
| `settings` | One settings group as JSON: `site`, `look`, `author`, `menu`, `social`, `telegram`, `seo`, `sync`. | Settings page |
| `telegram_messages` | The one channel message for a post, and its delivery state. | Telegram publisher |
| `redirects` | An old address that now points to a new one (301 or 302). | Automatic on slug change, panel (admins) |
| `sync_runs` | One Obsidian import: counts and per-note messages. Pruned after 90 days. | Obsidian sync |

## Posts

| Column | Meaning |
| --- | --- |
| `locale` | `fa` or `en`. |
| `slug` | Latin address part. Unique per language: `(locale, slug)`. |
| `translation_key` | Shared by a post and its translation: `(translation_key, locale)` is unique. A new post with no translation chosen gets a key no other post uses. |
| `title`, `description` | Description is used for search results, link previews and Telegram. |
| `body` | Markdown as written. |
| `body_html`, `toc`, `search_text`, `word_count`, `reading_minutes` | Worked out from `body` on every save (`App\Services\Content\MarkdownRenderer`). |
| `cover_path`, `cover_alt`, `audio_url`, `video_url` | Files are on the `public` disk (`public/uploads`, see [Uploaded files](#uploaded-files)); `audio_url` and `video_url` may also be `https://` addresses. |
| `status`, `published_at` | Readers see a post when `status = published` and `published_at` has passed. A future time schedules it. |
| `content_updated_at` | Last change to the text; shown as "updated" and used as `dateModified`. |
| `show_toc`, `send_to_telegram` | Per-post switches. |
| `series_id`, `author_id` | Optional links. |
| `source`, `source_path`, `source_hash` | `panel` or `obsidian`; for Obsidian posts the note's path and a fingerprint that lets unchanged notes be skipped. |
| `deleted_at` | Soft delete: deleted posts can be restored from the panel. |

Rules that keep addresses permanent:

- Changing the slug of a published post adds a row to `redirects` from the old address to the new one.
- A post is matched to its Obsidian note by the note's `id` and language when the note has an `id`, otherwise by the note's file path, then by its slug. Renaming the file or changing the slug updates the same post (a slug change adds a 301 redirect), so a post is never duplicated or sent to Telegram twice.
- A post whose note is removed or set to `publish: false` becomes a draft; it is never deleted.

## Telegram messages

`telegram_messages` is the memory of what was sent to the channel: at most one row per post (`post_id` is unique), so a post is never sent twice, even when it is edited, unpublished and published again.

| Column | Meaning |
| --- | --- |
| `post_id` | The post. Unique. |
| `chat_id` | The channel the message went to (`TELEGRAM_CHAT_ID` at the time). |
| `status` | `sending`: the row was claimed just before the API call; if it stays here the result is unknown (for example a timeout), so check the channel before sending again. `sent`: delivered. `failed`: Telegram refused it; retried up to `blog.telegram.max_attempts` (3) times, then left for the panel. `skipped`: marked as sent without sending. |
| `message_id` | Telegram's id of the channel message; `null` unless `sent`. |
| `attempts` | How many times sending was tried. |
| `last_error` | Telegram's last error message, shortened to 500 characters. |
| `sent_at` | When the message was delivered (or marked `skipped`). |

When posts are sent:

- Automatically, by `blog:publish-due` (every minute), only for posts with `send_to_telegram` on that were published within the last **48 hours** (`blog.telegram.max_age_hours`). Importing an old archive therefore never floods the channel.
- On the first install, `blog:install` marks every post that already exists as `skipped`.
- Manually from the panel ("ارسال به تلگرام" / "ارسال دوباره به تلگرام") for older posts and failed ones.

## Sync runs

One row per Obsidian import (`blog:sync`, every 10 minutes from the scheduler, or the button in the panel). `source` is the driver (`github` or `local`), `status` is `running`, `success` or `failed`, the `*_count` columns count created, updated, unpublished, unchanged and skipped (private) notes, and `messages` holds per-note problems as `[{level, file, text}]`. A note with an error is reported and skipped; the other notes are still imported.

Rows older than **90 days** are deleted by `php artisan model:prune` (`App\Models\SyncRun` is `Prunable`; the scheduler runs it daily).

## Roles

| Role | Can |
| --- | --- |
| `admin` | Everything: all posts, pages, tags, series, users, redirects, sync runs and the Settings page. Can delete Obsidian posts. |
| `editor` | See all posts; write posts and edit, delete or restore **only the panel posts they wrote**. Create and edit pages, tags and series, but not delete them. No access to users, redirects, sync runs or settings. |

Posts imported from Obsidian are **read-only in the panel for everyone**: they are edited in Obsidian and the next sync would overwrite panel changes anyway. The rules are in `app/Policies` (`PostPolicy`, `UserPolicy`, …), which Filament checks for every page and action.

## Redirects

`redirects` maps an old site path to a new one. Both columns are stored **normalised** (`App\Support\Url::path`): decoded, one leading slash, no trailing slash, no host and no query string, so `https://example.com/tags/%D9%81/` is stored as `/tags/ف`. Incoming requests are normalised the same way before the lookup, so a redirect matches however the old address is written. `from_path` is unique; `to_path` must be a path on this site (starting with `/`). `status_code` is 301 (permanent, the default) or 302.

## Page cache

Public pages are served to visitors from a whole-page HTML cache (`App\Support\PageCache`) in its own file store, `pages` (`storage/framework/cache/pages`), so they work on shared hosting without Redis. Only plain `GET` requests whose only query is a valid `page` number are cached, keyed by the configured site address (never the visitor's `Host` header), the path and the page number; only `200` responses without cookies are stored, and nothing is cached in the `local` and `testing` environments. Any change to posts, pages, tags, series or settings (including every Obsidian import that changes a post) **flushes the whole store**, so an edit is visible on the next request and stale copies never pile up on disk. Browsers may reuse a page for 5 minutes (`PageCache::MAX_AGE`).

## Uploaded files

Files are written to the `public` disk: `public/uploads` by default, or `UPLOADS_PATH` when the site is served from `public_html` (see the install guide). Writes throw on failure (`'throw' => true`), so a post never points at a file that was not saved.

| From | Allowed |
| --- | --- |
| Panel: cover and site images | png, jpg, webp, gif (`PostForm::IMAGE_TYPES`) |
| Panel: audio | mp3, m4a, ogg, wav (up to 100 MB; `config/livewire.php`) |
| Obsidian attachments (`![[...]]`, `cover`, `audio`) | images png, jpg, webp, gif, avif; audio mp3, m4a, ogg, wav; video mp4, webm; pdf |

- **No SVG**, anywhere: an SVG file can carry JavaScript. Other types in a note are reported as errors in the sync run.
- `public/uploads/.htaccess` turns PHP and CGI off for the folder, refuses script extensions (also as a double extension such as `x.php.jpg`) and refuses `.html` and `.svg`, so an uploaded file can never run on the server or in the visitor's browser.
- Large audio and video are better hosted elsewhere (Castbox, YouTube, Aparat) and linked with an `https://` address.

## What is not in the database

- **Secrets:** the Telegram bot token, the channel id, the GitHub token for the vault and the database password are in `.env` only.
- **Uploaded files:** `public/uploads/` (or `UPLOADS_PATH`): covers, audio, images from the panel and from Obsidian notes, and their resized copies in `_variants/` folders. Back this folder up together with the database.
- **Cached pages:** `storage/framework/cache/pages/` (see [Page cache](#page-cache)); safe to delete at any time.
- **The Obsidian vault:** stays in its own GitHub repository; the site only reads it.

## Obsidian note properties

Only notes inside the posts folder (`posts/` by default) with `publish: true` are imported. Checked in `App\Services\Obsidian\Note`, written by `App\Services\Obsidian\NoteImporter`:

| Property | Required | Rule |
| --- | --- | --- |
| `slug` | yes | `a-z`, `0-9` and single dashes. |
| `date` | yes | `2026-11-04` or `2026-11-04T09:30`, Tehran time. Future = scheduled. |
| `publish` | yes | Only `true` is imported. |
| `id` | no | Becomes `translation_key`; the same id on a Persian and an English note links them. |
| `lang` | no | `fa` (default) or `en`. |
| `title` | no | Defaults to the file name. |
| `description`, `tags`, `series`, `cover`, `cover_alt`, `audio`, `video`, `toc`, `telegram`, `updated` | no | As in the posts table. |
| `aliases` | not allowed | It means something different on a website. |

Inside notes: `[[links]]` become links to the published post (or plain text if that note is private), `![[image.png]]` and `![[episode.mp3]]` are copied next to the post (allowed types: see [Uploaded files](#uploaded-files)), `%% comments %%` are removed, and embedding a whole note (`![[Other note]]`) is reported as an error.
