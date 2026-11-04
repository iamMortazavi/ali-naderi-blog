# Content model

This is the data design of the blog. There is no MySQL database in v1: the site is static, so every record lives in a file under version control. Git is the database, each folder is a table, and each file is a row. This keeps the site fast, cheap to host, easy to back up (clone the repositories) and easy to move to another host.

MySQL on the cPanel host is reserved for later features that need visitors to write data, such as comments or a newsletter. Those are out of scope for v1.

## Repositories

| Repository | Owner | Written by | Holds |
| --- | --- | --- | --- |
| Content (the friend's Obsidian vault) | Friend | Friend, through Obsidian and obsidian-git | Posts, attachments, note templates |
| Site (this repository) | Shared organisation, maintainer is admin | Maintainer; admin panel; deploy workflow | Hugo theme, settings, fixed pages, tools, Telegram record |

The friend never sees code, and the maintainer never edits posts. Each repository has exactly one kind of writer per folder, so the two never produce merge conflicts.

## Entities

### Post (a note in `posts/` of the content repository)

One Markdown file per post per language. Properties are defined in [`schema/post.schema.json`](../schema/post.schema.json) and checked on every build. The build fails with a clear message when a published note breaks a rule.

| Property | Required | Rule |
| --- | --- | --- |
| `slug` | yes | Latin lowercase words joined by `-`. Unique per language. Never changes after publishing. |
| `date` | yes | `YYYY-MM-DD` or `YYYY-MM-DDTHH:MM`, Tehran time. A future date schedules the post. |
| `publish` | yes | Only `true` is built. Missing or `false` means private. |
| `id` | no | Defaults to `slug`. Shared by a post and its translation. Never changes after publishing. |
| `lang` | no | `fa` (default) or `en`. |
| `title` | no | Defaults to the file name. |
| `description` | recommended | 50 to 160 characters. Used for search results, link previews and Telegram. |
| `tags`, `series` | no | Taxonomies. |
| `cover`, `cover_alt` | no | Attachment name or `https://` address. |
| `audio`, `video` | no | Podcast file or video address. |
| `toc`, `telegram` | no | Both default to `true`. |
| `aliases` | forbidden | Obsidian meaning differs from Hugo meaning. |

Identity rules:

- **Primary key:** `(id, lang)`. It must be unique.
- **Address key:** `(lang, slug)`. It must be unique and defines the URL.
- **Translation link:** posts with the same `id` and different `lang` are translations of each other. Hugo receives `id` as `translationKey`. A translation is optional.

### Attachment

Any non-Markdown file in the content repository (images, audio, PDF). It is copied into the site only when a published post references it, either with `![[file]]` or `![](path)` or through `cover`/`audio`. Unreferenced files are never published. Large audio and video should live outside the repository (podcast host, YouTube, Aparat) and be referenced by `https://` address; GitHub rejects files over 100 MB.

### Taxonomies

`tags` and `series`, per language. Hugo builds `/tags/<tag>/` and `/series/<name>/` list pages and RSS feeds for them.

### Fixed pages (site repository: `site/content/<lang>/*.md`)

About, archives and search, one file per language. The about page is edited from the admin panel. These files are committed in the site repository; the converter never touches them.

### Site settings (site repository: `site/data/settings.yaml`)

One record, edited from the admin panel:

Rules: [`schema/settings.schema.json`](../schema/settings.schema.json), checked before every build.

| Field | Meaning |
| --- | --- |
| `site.url` | Public address. Used as Hugo's baseURL, for canonical links, the sitemap and Telegram links. |
| `site.title_fa`, `site.title_en` | Site title per language. |
| `look` | Active look: `paper` or `ink`. One value switches the whole site. |
| `author` | Name and short bio per language. |
| `menu` | Top menu items: `{label_fa, label_en, path}`. A site path is shown in each language's version. |
| `social` | List of `{name, url}` links shown on the home page. |
| `telegram.channel` | Public channel address used for the "join" link. |
| `telegram.languages` | Languages whose new posts go to the channel. |
| `telegram.hashtags` | Whether tags become hashtags in channel posts. |
| `telegram.footer_fa`, `telegram.footer_en` | Line added under every channel post. |
| `seo.google_verification`, `seo.bing_verification` | Search-console ownership codes. |

Not in settings, on purpose: passwords and tokens (GitHub secrets only), and addresses of posts (fixed rules in `site/config/_default/hugo.toml`).

### Telegram record (site repository: `state/telegram-sent.json`)

The memory of what was already sent to the channel. Only the deploy workflow writes it.

```json
{
  "version": 1,
  "posts": {
    "my-first-post:fa": {
      "message_id": 42,
      "sent_at": "2026-11-04T09:30:00+03:30",
      "url": "https://example.com/posts/my-first-post/"
    }
  }
}
```

- **Key:** `<id>:<lang>`. Renaming a file, re-running a build, redeploying an old version or adding a translation later never sends a post twice.
- A post is sent only when all of these hold: `publish` is true, `telegram` is not false, the date is not in the future, the upload succeeded, and the post's URL answers with status 200.
- `message_id` lets a later version edit the channel message when the post changes.
- `--baseline` marks every current post as sent without posting. Run it once when importing old posts so the channel is not flooded.

### Build manifest (generated: `build/manifest.json`)

Written by the converter on every build, not committed. It is the list of published posts that the Telegram sender and the post-deploy check read.

```json
{
  "generated_at": "2026-11-04T09:30:00+03:30",
  "posts": [
    {
      "key": "my-first-post:fa",
      "id": "my-first-post",
      "lang": "fa",
      "slug": "my-first-post",
      "title": "...",
      "description": "...",
      "date": "2026-11-04T09:00:00+03:30",
      "future": false,
      "path": "/posts/my-first-post/",
      "cover": "/posts/my-first-post/cover.jpg",
      "tags": ["..."],
      "telegram": true,
      "source": "posts/my-first-post.md"
    }
  ]
}
```

### Build record (generated: `/build.json` on the live site)

`{"commit": "<site sha>", "content": "<content sha>", "built_at": "..."}`. The deploy workflow fetches it after uploading and fails if it does not match. The daily check compares it with the latest commit.

## Addresses

| Page | Persian | English |
| --- | --- | --- |
| Home | `/` | `/en/` |
| Post | `/posts/<slug>/` | `/en/posts/<slug>/` |
| Tag | `/tags/<tag>/` | `/en/tags/<tag>/` |
| Archive | `/archives/` | `/en/archives/` |
| Search | `/search/` | `/en/search/` |
| RSS | `/index.xml` | `/en/index.xml` |
| Sitemap | `/sitemap.xml` (index of both languages) | |

Every link sent to Telegram is permanent, so these patterns are fixed. Changing the look never changes an address.

## Data flow

```
Obsidian (friend)
  └─ obsidian-git push ──► content repository
                              └─ notify-site workflow ──► repository_dispatch
site repository: deploy workflow
  1. checkout site + content
  2. blogtools.build_content   vault/posts → site/content/<lang>/posts/<slug>/index.md
                               + attachments, + build/manifest.json  (fails on invalid notes)
  3. hugo --minify             → site/public/
  4. FTPS upload (changed files only)
  5. verify /build.json and new post URLs
  6. blogtools.telegram        manifest + state → channel, update state, commit [skip ci]
  7. notify maintainer chat    success or failing stage
```
