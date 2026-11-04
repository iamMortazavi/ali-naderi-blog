# Data model

The blog is a Laravel 13 application with a Filament 5 admin panel and a MySQL database. The schema is in [`database/migrations/2026_11_04_000001_create_blog_tables.php`](../database/migrations/2026_11_04_000001_create_blog_tables.php).

## Tables

| Table | One row is | Written by |
| --- | --- | --- |
| `users` | A panel account. `role` is `admin` (everything) or `editor` (posts, pages, tags). | Panel (admins), `db:seed` |
| `posts` | One post in one language. | Panel, Obsidian sync |
| `tags`, `post_tag` | A tag in one language, and which posts carry it. | Panel, Obsidian sync |
| `series` | A multi-part series in one language. | Panel, Obsidian sync |
| `pages` | A fixed page such as "about", per language. Address `/{slug}` or `/en/{slug}`. | Panel |
| `settings` | One settings group as JSON: `site`, `look`, `author`, `menu`, `social`, `telegram`, `seo`, `sync`. | Settings page |
| `telegram_messages` | The one channel message sent for a post. | Telegram publisher |
| `redirects` | An old address that now points to a new one (301). | Automatic on slug change, panel |
| `sync_runs` | One Obsidian import: counts and per-note messages. | Obsidian sync |

## Posts

| Column | Meaning |
| --- | --- |
| `locale` | `fa` or `en`. |
| `slug` | Latin address part. Unique per language: `(locale, slug)`. |
| `translation_key` | Shared by a post and its translation: `(translation_key, locale)` is unique. Defaults to the slug. |
| `title`, `description` | Description is used for search results, link previews and Telegram. |
| `body` | Markdown as written. |
| `body_html`, `toc`, `search_text`, `word_count`, `reading_minutes` | Worked out from `body` on every save (`App\Services\Content\MarkdownRenderer`). |
| `cover_path`, `cover_alt`, `audio_url`, `video_url` | Files are on the `public` disk (`public/uploads`); `audio_url` and `video_url` may also be `https://` addresses. |
| `status`, `published_at` | Readers see a post when `status = published` and `published_at` has passed. A future time schedules it. |
| `content_updated_at` | Last change to the text; shown as "updated" and used as `dateModified`. |
| `show_toc`, `send_to_telegram` | Per-post switches. |
| `series_id`, `author_id` | Optional links. |
| `source`, `source_path`, `source_hash` | `panel` or `obsidian`; for Obsidian posts the note's path and a fingerprint that lets unchanged notes be skipped. |
| `deleted_at` | Soft delete: deleted posts can be restored from the panel. |

Rules that keep addresses permanent:

- Changing the slug of a published post adds a row to `redirects` from the old address to the new one.
- A post is matched to its Obsidian note by `(translation_key, locale)`, so renaming the note's file never creates a second post.
- A post whose note is removed or set to `publish: false` becomes a draft; it is never deleted.

## What is not in the database

- **Secrets:** the Telegram bot token, the channel id, the GitHub token for the vault and the database password are in `.env` only.
- **Uploaded files:** `public/uploads/` (covers, audio, images from the panel and from Obsidian notes, and their resized copies in `_variants/` folders). Back this folder up together with the database.
- **The Obsidian vault:** stays in its own GitHub repository; the site only reads it.

## Obsidian note properties

Only notes inside the posts folder (`posts/` by default) with `publish: true` are imported. Checked in `App\Services\Obsidian\Note`:

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

Inside notes: `[[links]]` become links to the published post (or plain text if that note is private), `![[image.png]]` and `![[episode.mp3]]` are copied next to the post, `%% comments %%` are removed, and embedding a whole note (`![[Other note]]`) is reported as an error.
