# Bilingual Persian/English Blog Published from Obsidian, with Telegram Posting

* **Author:** iammortazavi
* **Date:** 2026-11-04

## Overview

The requester wants to give a friend a blog in Persian (right-to-left) and English (left-to-right) that looks and works like aprd.ir. The friend already writes in Obsidian, should only need to write and do a few simple admin tasks, and should be able to change the site's look. Every new post should also go to the friend's Telegram channel in a fixed format. The requester will maintain the system alone after handing it over.

The site runs on cPanel shared hosting with PHP and MySQL. That hosting has no Node.js and no SSH access, and nobody knows yet whether it can run programs such as Hugo. The requester has hosting both inside and outside Iran. Servers inside Iran cannot reach Telegram, and reaching GitHub from Iran is unreliable.

The current plan:
- All posts live as Markdown files in one GitHub repository.
- The friend writes in Obsidian, which syncs to GitHub through the obsidian-git plugin, or in a browser-based admin panel.
- GitHub Actions (GitHub's hosted build service) builds the site with Hugo, uploads it to the host by FTP, and posts new articles to Telegram.
- There is one right-to-left-first base theme with several switchable "looks".

Six reviewers looked at this concept, each from a different angle. All six completed. This brief merges their findings and removes duplicates. Several claims about outside tools come from the reviewers' own knowledge and could not be checked online. Those claims are marked "verify".

**Main conclusion.** No reviewer said the plan cannot work. All six said it could not start until three facts were known, because each one changes the design. The requester answered all three after the review:
1. Which host serves the public site. **Answered:** the host outside Iran.
2. Whether the friend can reliably reach GitHub. **Answered:** yes. The friend's access is from outside Iran.
3. Whether the friend will keep writing in Obsidian. **Answered:** yes. The site reads posts only from one fixed folder of the GitHub repository.

These answers select option (a), Hugo with a Git-based admin panel. See "Decided by the requester after the review" at the end of this brief.

Four things must be defined before any theme work:
- When a post counts as "new" for Telegram.
- How private notes are kept out of the site.
- What "finished" means.
- How failures are reported.

## Analysis

Severity: 🔴 Blocker (must be settled before building), 🟡 Major (must be in the design), 🟢 Nit (small or easy to fix). Lens names: design, codebase-details, zoom-out, comprehension, implementation-readiness, structural.

### 1. Hosting, location and network access

- 🔴 **It is not decided which host serves the public site.** (all six lenses)
  - GitHub's build machines are mostly in the US and use different addresses every run. Uploading from them by FTP to a host in Iran may be blocked, slow, or banned by the host's firewall. It fails completely during national internet shutdowns, which happened in 2019, 2022 and 2025.
  - Telegram fetches link previews from outside Iran, and Google's crawler also visits from outside. Both may fail against an Iranian host.
  - If both hosts serve the site, search engines need to be told which copy is the main one. Otherwise the two copies compete.
  - All lenses recommend the host outside Iran as the only public site for v1. A mirror inside Iran can come later. It would copy a release from the outside host on a schedule, and it must point search engines at the main site.
- 🔴 **Whether the friend can reach GitHub decides whether the plan works at all.** (design, codebase-details, comprehension, structural, implementation-readiness)
  - Both writing paths talk to GitHub from the friend's own device: obsidian-git, and the browser-based admin panel.
  - If the friend is in Iran and GitHub is throttled, publishing stops and the friend sees unclear errors.
  - GitHub accounts used from Iranian addresses may also face trade-sanction restrictions (implementation-readiness; verify).
  - Run a real test from the friend's own network and device, with and without a VPN, before choosing the design. If the friend cannot reach GitHub reliably, structural argues that a PHP CMS on the outside host (option c) wins by default.
- 🟡 **Drop "build the site on the shared host" as a fallback.** (design, codebase-details, implementation-readiness, zoom-out)
  - Shared hosts usually block programs from being started by PHP. They limit memory and processes. They often do not allow running files from the home folder at all.
  - The host inside Iran also cannot fetch from GitHub.
  - The real manual fallback: download the finished build as a zip from GitHub, then upload and extract it with cPanel's File Manager.
- 🟢 **A few checks on each host settle the remaining unknowns.** (design, structural)
  - A PHP info page shows whether running programs is allowed, and whether zip extraction and outgoing web requests work.
  - A cPanel scheduled task that runs `git --version` shows whether programs can run at all.
  - An FTPS upload from a GitHub build machine shows whether deploys can reach the host.

### 2. Privacy and the publishing boundary

- 🔴 **The friend's private notes could end up on GitHub or on the site.** (design, codebase-details, comprehension, implementation-readiness, zoom-out)
  - obsidian-git pushes the whole vault, meaning the whole folder of notes, unless it is limited.
  - If the repository is public, which is what makes GitHub build time free, every draft is readable. The plugin's own settings folder may also hold the friend's GitHub access key.
  - Required:
    - A separate blog vault, or a single blog folder that is the only thing synced and built.
    - A note is published only if it is explicitly marked, for example with `publish: true`. Everything else is excluded by default.
    - The settings folder is excluded from sync.
    - Links to notes that are not published show as plain text. They never become broken links, and they never pull the private note into the site.
  - Several lenses recommend a private repository for this reason.

### 3. Scope and the meaning of "finished"

- 🔴 **"Masterpiece", "professional and complete" and "all the features of Hugo-like sites" have no test for when the work is done.** (all six lenses)
  - One maintainer cannot finish a list with no end.
  - Replace these words with a short checklist built from what aprd.ir actually shows:
    - Post list with excerpt, date, reading time, word count and author.
    - Tags, archive, about page, podcast, RSS and an English section.
    - Code highlighting and math.
  - Add measurable targets, for example:
    - Google's mobile page-speed score of 90 or higher.
    - Google's structured-data test passes.
    - Language links between translations check out.
    - Pages work with JavaScript turned off.
- 🔴 **"Simple admin tasks" are not listed.** (comprehension, zoom-out, codebase-details)
  - Each possible task changes the design. Candidates: edit the about page, edit menus, rename tags, unpublish a post, schedule a post, upload audio, moderate comments, see visitor numbers, change the look, change the Telegram format.
  - The friend should list what they expect to do in a normal month. Anything else is the maintainer's job.
- 🟡 **Several separate themes multiply the work.** (all six lenses)
  - Each theme would need its own versions of code, math, callouts, structured data, language links and Persian dates, in both directions, checked on every page type.
  - All lenses agree with one base theme plus "looks". Limit the looks to colour, font and dark/light, chosen by one setting. Different home-page layouts come later.
  - Ship two looks at launch at most. zoom-out suggests even one look with dark/light and an accent colour is enough for v1.

### 4. Obsidian content and Hugo

- 🟡 **Hugo does not understand most Obsidian-only syntax.** (all six lenses)
  - Callouts (`> [!note]`) are handled by recent Hugo versions (about 0.132–0.134 onward; verify).
  - `[[wikilinks]]`, `![[embeds]]`, embedding one note inside another, Obsidian's shortest-name attachment lookup, and file names with spaces or Persian characters are not handled.
  - Two remedies were raised:
    - **Cheapest (codebase-details, comprehension):** in Obsidian, turn off "Use [[Wikilinks]]" and set links to relative paths. The friend's links then become standard Markdown that Hugo can follow, so no converter is needed and the content stays portable. Note embedding then has to be banned.
    - **Otherwise (design, structural, implementation-readiness, zoom-out):** a separate conversion step runs during the build. It uses an existing tool such as obsidian-export or a small script, with test files. It reports broken links and missing attachments, and never rewrites the friend's files.
  - Do not do the conversion with text replacement inside Hugo templates. That breaks tables of contents, excerpts and word counts (codebase-details).
- 🔴 **There is no agreed list of post fields (the "properties" at the top of each note).** (structural, implementation-readiness, codebase-details, design)
  - Five parts of the system read these fields: Obsidian templates, the admin panel, Hugo, the converter and the Telegram poster.
  - Required: one written, versioned field list:
    - Fields: title, date, language, a stable ID that links translations (Hugo calls it `translationKey`), Latin slug, publish/draft, publish date, tags, description, cover, audio, video, and a Telegram on/off switch.
    - Folder layout, and where media lives.
  - A check during the build rejects files that break these rules.
- 🟡 **Obsidian property names clash with Hugo's.** (codebase-details, comprehension)
  - In Obsidian, `aliases` means other names for a note. In Hugo it means redirect addresses, so carrying it across creates junk redirect pages.
  - Obsidian notes often have no title field and use the file name instead.
  - Dates need a time zone (Asia/Tehran).
- 🟡 **Obsidian's file layout and Hugo's preferred layout differ.** (structural, comprehension, codebase-details)
  - Obsidian puts all pasted images in one shared folder, with timestamp names and full camera resolution.
  - Hugo's image processing works best with a folder per post.
  - Decide the layout once, set it in Obsidian and in the admin panel's media setting, and warn on very large files.
- 🟡 **Do not put Hugo "shortcodes" (Hugo-only tags) in posts.** (zoom-out, structural)
  - They show as junk in Obsidian and tie the content to Hugo.
  - Have Hugo turn ordinary embedded audio and video into players instead.

### 5. Persian-specific details

- 🟡 **Jalali (Persian calendar) dates are missing from the requirements.** (design, codebase-details, zoom-out, structural, implementation-readiness)
  - Hugo only produces Gregorian dates, with Persian month names at best.
  - A conversion is needed, either while the site is built or in the reader's browser.
  - Persian digits must also be decided.
- 🟡 **Persian URLs.** (comprehension, codebase-details, implementation-readiness, design)
  - Persian file names become long, unreadable web addresses when shared on Telegram.
  - Most lenses recommend a required Latin slug for each post.
- 🟡 **Persian search.** (comprehension, zoom-out, structural, codebase-details)
  - Search must treat the Arabic and Persian forms of ی/ي and ک/ك as the same letter, and handle the half-space (zero-width non-joiner).
  - Pagefind, a search tool that runs at build time, has no Persian word-stem matching. Test it on real text.
- 🟢 **Reading time and word count.** (comprehension, structural) Check them on real Persian text with half-spaces, so the home-page numbers are believable.
- 🟡 **Self-host everything.** (all six lenses)
  - Google Fonts, public script libraries, YouTube and some analytics tools are slow or blocked in Iran.
  - Serve these from the site itself: the font (for example Vazirmatn), the math styling, search files and the admin panel's script.
  - Render math while the site is built, using Hugo's built-in math feature (verify the version). Readers then need no math script.
  - Keep code blocks and formulas left-to-right inside Persian text.

### 6. Media, podcast, video and comments

- 🟡 **Audio and video must not go into the repository.** (all six lenses)
  - GitHub rejects files over 100 MB, and large repositories slow obsidian-git, especially on phones.
  - Keep audio on the host or a podcast host, and link to it from the post.
  - Telegram posts should link to the audio rather than upload it. Bots can upload at most 50 MB.
- 🟡 **"Podcast section" is ambiguous.** (comprehension, structural, codebase-details)
  - It could mean audio players inside posts, or a real podcast feed listed on Apple Podcasts or Castbox.
  - A real feed needs a custom RSS template with episode data.
- 🟡 **Video platform not named.** (comprehension, design, implementation-readiness) YouTube is blocked in Iran, and Aparat may be slow abroad. Choose one, or support both.
- 🟡 **Comments are not addressed, though the reference site links to comments.** (comprehension, structural, design, codebase-details, zoom-out)
  - Giscus needs readers to have GitHub accounts.
  - Disqus is heavy and filtered in Iran.
  - Self-hosted comment programs need software shared hosting does not run.
  - Options: no comments, a "discuss on Telegram" link (blocked for readers inside Iran), or a small PHP comment form on the host. Decide explicitly.
- 🟢 Mermaid diagrams, common in Obsidian, are not mentioned. (design) Decide whether they are in scope.

### 7. Two writing paths and the admin panel

- 🔴 **Obsidian and a web admin panel writing the same files will conflict.** (all six lenses)
  - When both save at once, obsidian-git shows merge-conflict markers or cryptic errors the friend cannot fix.
  - The admin panel's rich-text editor may reorder or drop note properties. It may also break wikilinks and callouts by adding escape characters, for example `\[\[`. Decap is known for this; Sveltia says it keeps unknown fields (verify).
  - Required:
    - Obsidian is the main tool for writing posts.
    - The panel is limited to site settings, the about page, the look switch and quick fixes, using plain Markdown fields.
    - obsidian-git always fetches the latest version before it pushes.
    - The friend gets a one-line rule for sync failures: stop and message the maintainer.
- 🔴 **Admin-panel login is not designed.** (implementation-readiness, structural, design, codebase-details)
  - Decap needs a separate login helper service when it is not hosted on Netlify.
  - Sveltia can use a pasted personal access key or a Cloudflare helper. Cloudflare reachability from Iran varies.
  - A small PHP login helper on the outside host is possible.
  - Access keys expire, and then the panel and obsidian-git stop working without warning.
  - Choose the method, test it from the friend's network, and record each key's expiry date with a reminder.
- 🟡 **The Persian, right-to-left editing experience is unverified.** (all six lenses)
  - Decap may have a Persian interface (verify), but its editor handles right-to-left text poorly.
  - Sveltia's interface may not have Persian at all (verify).
  - The friend should type a mixed Persian/English post with code and a formula in each candidate editor, on desktop and phone, before anything is chosen.
- 🟡 **obsidian-git on phones is weak.** (all six lenses)
  - On mobile it uses a slower built-in version of git. It struggles with large vaults and handles conflicts poorly.
  - If the friend mostly writes on a phone, either limit publishing to desktop or budget for Obsidian's paid sync with a desktop machine doing the push.
- 🟡 **Look switching is not instant and has no preview.** (comprehension, design, codebase-details)
  - A switch changes one setting, starts a build and an upload, and appears minutes later. The panel's preview does not show the real theme.
  - Show a picture of each look. Say "applied, live in about N minutes". Optionally allow previewing a look on the live site with an address such as `?look=x`.
  - Store the look in a small separate settings file. The panel must never edit Hugo's main settings file, because it may drop entries it does not know.
  - Reader-side dark/light is separate and saved in the browser.
- 🟢 **Translations need a visible rule.** (comprehension, design)
  - Pair translations through the shared ID. Not every post needs both languages.
  - Show language links only when a pair exists.
- 🟡 **Note fields are a burden.** (design) Give the friend an Obsidian template that fills them in. Errors should come back in plain Persian.

### 8. Telegram posting

- 🔴 **"New post" is not defined, and nothing records what was already posted.** (all six lenses)
  - Comparing files between saves gets several cases wrong:
    - It posts again when a file is renamed, a job is re-run, an old build is redeployed, or a translation is added later.
    - It misses posts with a future date, which go live without any new save.
    - The first import of existing notes could flood the channel and hit Telegram's rate limit.
  - Required:
    - Post only notes marked as published, with a date that is not in the future.
    - Keep a record of each post that was sent, by ID and language, together with Telegram's message number. Structural warns that writing it back into the note can trigger endless rebuilds. A separate state file is safer, or the write-back must be marked to skip the build.
    - Post only after the upload succeeded and the post's address answers.
    - Provide a "no Telegram" switch for redeploys and manual runs.
    - Retry unsent posts on the next run.
    - Decide whether English posts go to the channel, and whether a pair becomes one message or two.
  - The stored message number lets the bot edit a post later. Deleting is limited to recent messages (verify).
- 🟡 **The "specific formatted style" is not given.** (comprehension) The friend should send one real example message.
- 🟢 **Telegram's limits are not a problem at blog volume.** (all six lenses) Points to handle:
  - About 20 messages a minute per channel.
  - 4,096 characters per text message, and 1,024 per photo caption.
  - Use HTML formatting. The Markdown option needs escaping that breaks easily on Persian punctuation.
  - The bot must be a channel admin.
  - Check whether half-spaces break Persian hashtags.
  - Sending the cover image directly avoids relying on Telegram's preview fetch.

### 9. Cost and quotas

- 🟡 **Automatic syncing causes a storm of builds.** (all six lenses)
  - If obsidian-git commits every few minutes, each commit starts a build and an upload, and could post a half-written article.
  - Example (comprehension): 30 builds a day at 3 minutes each is about 2,700 minutes a month.
  - Private repositories get about 2,000 free minutes a month; public ones are unlimited (verify current numbers).
  - Required:
    - Build only when blog content, theme or settings change.
    - Let only one build run at a time.
    - Preferably give the friend an explicit "Publish" action, with automatic sync used only as a backup.
  - Disagreement on overlapping builds:
    - Most lenses say a new build should cancel the older one.
    - design warns that cancelling halfway through an FTP upload leaves a half-updated site, so queue instead of cancel.
    - This brief recommends queuing.
- 🟡 **FTP upload is slow, not all-at-once, and may send passwords in clear text.** (all six lenses)
  - A look switch or Hugo upgrade rewrites every page. Thousands of files can take tens of minutes, and while the upload runs some pages show the old look and some the new.
  - Required:
    - Use encrypted FTP (FTPS).
    - Upload only changed files. FTP-Deploy-Action keeps a record file on the server for this; if that file is lost, everything is uploaded again.
    - Keep builds repeatable, so unchanged pages stay byte-for-byte the same.
  - Better option (design, implementation-readiness): upload one zip over HTTPS to a small password-protected PHP script. The script extracts it into a new folder and swaps folders. That takes seconds, avoids half-updated sites and allows instant rollback, but it uses about twice the file count.
  - codebase-details mentions `lftp` as a simpler upload tool to consider.
- 🟡 **Shared-host limits.** (all six lenses)
  - File-count limits are often 100,000–250,000 files. Tag pages, page numbers, image sizes and search files, times two languages, add up.
  - Disk and bandwidth limits: audio hits them first.
  - Limits on parallel FTP connections.
  - Measure the output with 200 dummy posts in both languages.
- 🟢 **Scheduled posts need a timed rebuild.** (comprehension, design, codebase-details, structural, implementation-readiness)
  - GitHub turns off timed jobs in public repositories after 60 days without activity, so scheduled posts can silently stop.
  - Add a keep-alive, or accept the risk.
- 🟢 **Use GitHub's build cache.** (design, structural) Caching Hugo's processed-image folder between runs avoids redoing image work on every build.

### 10. Failure reporting (observability)

- 🔴 **Nobody is told when something fails.** (all six lenses)
  - GitHub emails failures to whoever triggered the run. When the friend pushes, the friend gets an English email they will not understand, and the maintainer gets nothing.
  - Required:
    - A final step in every run sends a Telegram message to a private maintainer chat, or to a chat shared with the friend.
    - The message says which stage failed (check, build, upload, verify, Telegram), names the post, links to the run, and includes a short Persian line for the friend.
    - On success, the friend gets "published: <link>".
    - This runs on GitHub's machines outside Iran, so the Telegram block does not apply.
- 🟡 **The upload is not checked afterwards.** (design, codebase-details, implementation-readiness, structural)
  - After uploading, fetch the new post, the sitemap, the RSS feed and a small status file (`/build.json` with the build's version number and time).
  - Fail the run if they do not answer or the version does not match.
  - A daily job compares the live version with the latest save in the repository.
- 🟡 **Telegram errors are not checked.** (codebase-details)
  - Telegram returns specific errors: bad formatting, the bot was removed, or rate-limited.
  - Log the full answer, wait and retry when rate-limited, fail on anything else, and record the message number only on success.
- 🟡 **Content errors should be caught before building.** (codebase-details, design)
  - Check for broken links, wikilinks left unconverted, missing slugs or IDs, the `aliases` property, and missing image descriptions.
  - Report them to the friend in plain language.
- 🟡 **obsidian-git failures are silent on the friend's device.** (zoom-out, implementation-readiness, comprehension) Agree a simple rule: if no Telegram post appears within N minutes, the friend tells the maintainer.
- 🟢 **Other checks** (zoom-out, design, codebase-details, comprehension):
  - A free uptime monitor. Monitors run from outside Iran and may wrongly report an Iranian host as down.
  - Google Search Console.
  - Reminders for the security certificate, the domain and access-key expiry.
  - The build time in the page footer.

### 11. Migration and rollback

- 🔴 **Web addresses must be fixed before the first post.** (implementation-readiness, design, codebase-details)
  - Every link posted to Telegram is permanent. Changing the address pattern later breaks those links and loses search ranking.
  - Hugo's redirects are page-level redirects, not proper server redirects. Real server redirects would have to be written as `.htaccess` rules on the host.
  - Fix the address pattern before launch (for example `/fa/posts/<latin-slug>/`). Changing the look must never change addresses.
- 🟡 **Rolling back and redeploying an old build is not designed.** (all six lenses)
  - Today a rollback means undoing the change and running a full rebuild and slow upload. Re-running an old build through the normal pipeline would post to Telegram again.
  - Required:
    - A manual "deploy this version" action with Telegram turned off.
    - Keep finished builds. GitHub keeps these at most 90 days, so tag releases.
    - Optionally use the zip-and-folder-swap upload for instant rollback.
  - Target: the maintainer can roll back in 5 minutes or less.
- 🟡 **Content format decides future migrations.** (comprehension, implementation-readiness, zoom-out, codebase-details)
  - Keeping Obsidian-only syntax in the repository ties every future move to the converter.
  - Standard Markdown links reduce that tie.
  - Moving Markdown into WordPress later is possible. Moving from WordPress back to Markdown is much harder, which favours option (a).
- 🟢 **Moving hosts is cheap with a static site.** (all six lenses)
  - It only means changing the upload settings and DNS, as long as nothing depends on the host: no PHP comments, no PHP admin, no redirects that exist only there.
  - Look changes are one setting and are undone by one revert.

### 12. Long-term maintenance for one maintainer

- 🟡 **Hugo upgrades break templates.** (all six lenses)
  - Version 0.146 reworked how Hugo finds templates.
  - Pin the exact Hugo version, and each GitHub Action at a fixed version.
  - Upgrade on purpose, on a test branch, after comparing screenshots of key pages.
- 🟡 **Do not build the base theme from scratch.** (design, zoom-out, comprehension, codebase-details, implementation-readiness)
  - The requester's reason for avoiding Hugo is partly out of date. Hugo supports right-to-left per language, and aprd.ir itself is a Persian Hugo site.
  - Several maintained themes support right-to-left: Blowfish, Congo, PaperMod and Hextra (verify current state). Blowfish already has colour schemes, home layouts, dark/light, contents tables, related posts, search and math.
  - Use one such theme unchanged, and override only the pages that need the Persian or aprd.ir look.
  - Also check whether aprd.ir's own theme is open source and what its licence allows (codebase-details).
- 🟡 **Moving parts for one person.** (all six lenses)
  - On the Hugo route, structural counts about nine custom pieces: theme and looks, Jalali dates, structured data, podcast feed, Obsidian converter, build-and-upload, Telegram poster and its record, admin-panel setup and login, comments.
  - WordPress has about two pieces, but in exchange needs constant plugin and security updates.
  - Expected ongoing cost on the Hugo route: a few hours a quarter, plus renewing access keys and upgrading Hugo.
  - Required:
    - Keep custom code in one small, tested place.
    - Group automatic dependency updates.
    - Run a scheduled "does it still build" check.
    - Write a one-page runbook: how to publish, roll back, renew each key and upgrade Hugo, and what to do if GitHub becomes unavailable.
- 🟡 **Sveltia depends mostly on one developer, and Decap moves slowly.** (codebase-details, design, zoom-out, structural) Keep the panel's settings to the part both tools support, so either can replace the other. Pin the panel's version.
- 🟡 **Ownership after handover is not stated.** (design, comprehension)
  - Decide who owns the GitHub account, the bot and channel, the domain (a .ir domain needs IRNIC registration) and the hosting.
  - design suggests the friend owns the domain, channel and bot, and the repository sits under the friend's account or a shared organisation with the maintainer as admin.
  - Keep all passwords and keys in a password manager the friend can access.
  - Keep a regular copy of the repository outside GitHub in case the account is restricted (implementation-readiness).

## Goals vs. Non-Goals

**In-Scope Goals (v1)**
- One public site on the host outside Iran, with one main domain and fixed web addresses using Latin slugs.
- Persian (right-to-left) and English (left-to-right) sections. Translations are linked when they exist and are not required.
- Obsidian as the main writing tool. Only notes in the blog folder marked `publish: true` are published.
- A written, versioned field list for posts, checked during every build.
- Obsidian syntax handled one of two ways: standard Markdown links, or a tested conversion step during the build.
- One existing right-to-left Hugo theme with Persian-specific changes. Up to two looks (colour, font, dark/light), chosen by one setting.
- Jalali dates, Persian digits, Persian-aware search, self-hosted fonts and scripts.
- Code highlighting, math rendered while the site is built, audio and video players. Media lives outside the repository.
- Search-engine basics: per-page structured data (BlogPosting, BreadcrumbList, Person), sitemap and RSS per language, language links between translations, main-address tags, Open Graph tags.
- Telegram posting that sends each post exactly once, using a stored record, and only after a verified upload.
- Failure and success messages to a private Telegram chat, a check after each upload, and an uptime monitor.
- Pinned versions, a manual "deploy this version" action, a runbook and a list of passwords and keys with expiry dates.

**Out-of-Scope Non-Goals (v1)**
- Several independent themes, and different home-page layouts per look.
- A custom PHP admin panel.
- Building the site on the shared host.
- A public mirror inside Iran (later, and not indexed).
- Self-hosted video. Audio or video stored in the repository.
- Requiring every post in both languages.
- A full podcast feed for podcast directories, until episodes exist.
- Comments beyond a link, unless the friend asks for them.
- A web editor as a second main writing path.
- Scheduled posts, unless the friend asks for them.
- Third-party scripts loaded from outside the site.

## Alternatives Weighed

- **(b) Custom PHP admin panel on cPanel. Rejected by all six lenses.**
  - It would mean building and securing a login, file uploads, a Persian Markdown editor, and a GitHub access key stored on shared hosting. All of that repeats what Sveltia and Decap already do.
  - A panel on the Iranian host cannot reach GitHub or Telegram reliably.
  - It is the largest maintenance cost for one person.
  - The only part worth keeping is a small PHP helper on the outside host, for login or as a relay, and only if tests show it is needed.
- **(c) WordPress with Polylang, Rank Math, WP-Parsidate (Persian dates) and a Telegram plugin, on the outside host. Rejected after the requester's answers.** It was the fallback if the friend could not reach GitHub or did not want to write in Obsidian. Both conditions turned out false: the friend reaches GitHub from outside Iran and keeps writing in Obsidian. The points below are kept for the record.
  - It covers most requirements at once: Persian right-to-left admin, theme switching with live preview, comments, search, media library, scheduling, linked translations and structured data. It runs natively on cPanel.
  - It loses Obsidian and GitHub as the place posts live; Obsidian publishing to WordPress is one-way and drops wikilinks. It also gives up the speed and security of a static site, and needs constant plugin and security updates.
  - Telegram plugins cannot reach Telegram from the Iranian host without a relay.
  - It wins if the friend would happily write in a web editor, or cannot reach GitHub reliably.
- **Grav (within option c). Rejected.** Its Git sync needs the `git` program and permission to run programs, both unknown and often disabled. It has few right-to-left themes and a smaller ecosystem. Version 2.0 has been in development for a long time (verify).
- **Statamic (within option c). Rejected.** Git automation needs permission to run programs. Multi-site and multilingual need the paid Pro licence (about $275 per site plus yearly updates; verify). Installing without SSH is awkward.
- **Building Hugo on the shared host. Rejected.** See Analysis section 1.
- **Several independent themes. Rejected** in favour of one base theme with looks. See Analysis section 3.
- **Building a theme from scratch. Deprioritized** in favour of an existing right-to-left Hugo theme with changes.
- **Quartz** (a site builder designed for Obsidian notes). **Worth a short look, not chosen.** It handles wikilinks, embeds and callouts natively, but it is shaped like a notes garden rather than a blog, and its right-to-left support is unproven. (zoom-out, implementation-readiness)
- **Obsidian Publish. Deprioritized.** It needs no build and understands Obsidian natively. But it gives little control over right-to-left layout, search-engine data or structured data, has no Telegram posting, and is a paid subscription. (zoom-out)
- **Ready-made RSS-to-Telegram services (IFTTT, n8n and similar). Deprioritized.** They add a third party to trust and may not support the required format. A short script in the build is enough. (zoom-out, codebase-details)
- **Giscus or Disqus for comments. Deprioritized.** Giscus needs readers to have GitHub accounts, and Disqus is heavy and blocked in Iran.

## Key Decisions & Staging for Next Steps

**Decisions resolved (agreed by all lenses, not rejected by the requester)**
- Reject option (b), the custom PHP admin.
- Remove "build on the shared host" from the plan.
- One base theme with switchable looks, not several themes. The look is one setting in a separate small settings file.
- GitHub's build service runs outside Iran and does all Telegram work.
- Media stays outside the repository. All fonts and scripts are self-hosted.
- Pin Hugo, the theme, the admin panel and every GitHub Action to fixed versions.
- Telegram posting happens only after a verified upload and is recorded so it is never repeated.

**Decided by the requester after the review (2026-11-04)**
- **Public host:** the cPanel host outside Iran is the only public site in v1. The host inside Iran is not used in v1.
- **Network access:** the friend reaches GitHub from outside Iran. The Git-based plan is therefore workable, and option (a) is chosen.
- **Writing path:** Obsidian is the only place posts are written. The site is built only from one fixed folder of the GitHub repository. Everything outside that folder is never built or published.
- **Admin panel:** follows from the writing path. The panel (Sveltia or Decap) does not edit posts. It handles site settings, the about page and the look switch. (Assumed from the answer above; the requester did not reject it.)
- **Definition of done:** the requester accepts the measurable targets in Analysis section 3: mobile page-speed score of 90 or higher, structured-data test passes, language links check out, pages work with JavaScript turned off, plus the aprd.ir feature checklist.
- **Looks:** two looks of one base theme in v1 are acceptable. Several independent themes are dropped from v1.

**Decisions still open, and the question that settles each**

| Decision | Question that settles it | Who answers |
| --- | --- | --- |
| Writing device | "Do you write on desktop, phone or both?" (decides whether obsidian-git on mobile is relied on) | Friend |
| Main domain | "Which domain is the main one?" | Requester |
| Admin-panel scope | "Which tasks will you do yourself in a normal month?" | Friend |
| Panel choice | Sveltia or Decap, after the friend types a mixed Persian/English settings edit in each | Friend, then a test |
| Telegram rules | "Send one example message. Do English posts go to the channel? One message per language or one per pair?" | Friend |
| Wikilinks | "Can Obsidian be set to standard Markdown links, or must wikilinks stay?" | Friend and maintainer |
| Comments | "Do you want reader comments? Must readers comment without an account?" | Friend |
| Podcast and video | "Real podcast feed, or audio inside posts? YouTube, Aparat or files?" | Friend |
| Dates and addresses | "Jalali or Gregorian dates? Latin slugs accepted?" | Friend |
| Repository visibility | "Public (unlimited build minutes, drafts visible) or private?" | Requester |
| Ownership | "Who owns the GitHub account, bot, channel, domain and hosting after handover?" | Requester and friend |
| Scheduled posts | "Do you need posts to go live at a set future time?" | Friend |
| Upload method | Does FTPS from GitHub to the chosen host work, and how long does a 5,000-file upload take? Is a zip-and-extract script better? | Test |

**Suggested staging**

*Stage 0: tests and decisions (about 3 days)*
1. Host checks on the host outside Iran: PHP info page, zip extraction, FTPS from a GitHub build machine with a 5,000-file dummy site, and the time that upload takes.
2. From the friend's own network and device: GitHub login, an obsidian-git push limited to the blog folder, and a Sveltia login.
3. The friend edits the site settings and about page in Sveltia and in Decap, in Persian, then picks one.
4. Render a Persian post with Blowfish or Congo in right-to-left on a pinned Hugo version.
5. Run 10 real notes from the friend through link handling and Hugo.
6. Measure output size and file count with 200 posts in both languages.

*Stage 1: v1 (option a)*
1. Field list and folder layout, with test files: a linked fa/en pair, every Obsidian syntax case, one podcast episode.
2. A bare end-to-end path: repository, build, Hugo with a minimal theme, upload, check after upload, failure alerts.
3. Obsidian link handling.
4. Base theme: right-to-left/left-to-right, translations, search-engine data and structured data, Jalali dates, self-hosted assets, code and math.
5. Telegram poster with its record, and "published" or "failed" messages.
6. Admin panel limited to settings, the about page and the look switch, with its login method.
7. Search, audio player, video embeds, contents tables.
8. A second look (stop at two).
9. Handover: runbook, list of passwords and keys with expiry dates, rollback action, uptime monitor, a short training session for the friend.

*Stage 2: later*
- Different home-page layouts per look.
- Related posts (built into Hugo, cheap to turn on).
- A full podcast feed for directories.
- Comments.
- Scheduled posts with a timed rebuild.
- A mirror inside Iran that is not indexed.
- Using the admin panel as a second writing path for full posts.
- Editing Telegram posts when a post is edited.
