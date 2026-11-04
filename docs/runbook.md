# Runbook

How to set up, run and repair the blog. Written for the maintainer.

## One-time setup

### 1. Repositories

1. Create a GitHub organisation (or use the friend's account) and two repositories:
   - **site**: this repository. It can be public.
   - **content**: the friend's Obsidian vault. Keep it **private**. Start it from `example-vault/` minus the sample posts.
2. Add the maintainer as admin on both.

### 2. Tokens

| Token | Where it is created | Permissions | Stored as |
| --- | --- | --- | --- |
| Content read token | Fine-grained token, resource: content repo | Contents: read | site repo secret `CONTENT_TOKEN` |
| Dispatch token | Fine-grained token, resource: site repo | Contents: read and write | content repo secret `SITE_DISPATCH_TOKEN` |
| Panel login token | Friend's account, resource: site repo | Contents: read and write | Pasted once into the panel |
| obsidian-git token | Friend's account, resource: content repo | Contents: read and write | obsidian-git settings on each device |

Fine-grained tokens expire (at most one year). Write every expiry date into the password manager with a reminder two weeks before.

### 3. Site repository settings

Settings → Secrets and variables → Actions:

- Variables: `CONTENT_REPO` (`owner/content`), `FTP_SERVER_DIR` (usually `public_html/`), and `PRODUCTION` = `true` once everything below is ready. Until then only the staging copy on GitHub Pages is built.
- Secrets: `CONTENT_TOKEN`, `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`, `TELEGRAM_BOT_TOKEN`, `TELEGRAM_CHAT_ID`, `NOTIFY_CHAT_ID`.

The domain, titles, menu and links are set in the admin panel (Settings → Site). The workflows fill in the panel's repository and address automatically.

### 4. cPanel host (outside Iran)

1. Create an FTP account limited to the site folder. Make sure the host supports explicit FTPS (FTP over TLS).
2. Turn on AutoSSL for the domain.
3. Run the deploy workflow once by hand (Actions → Deploy → Run workflow, Telegram off) and open the site.

### 5. Telegram

1. Create the bot with @BotFather and copy its token.
2. Add the bot to the channel as an admin with "Post messages".
3. `TELEGRAM_CHAT_ID` is the channel's `@username`, or its `-100…` id for a private channel.
4. Create a private group with the maintainer (and the friend if they want "published" messages), add the bot, and put the group's id in `NOTIFY_CHAT_ID`.
5. **Before the first real deploy**, if the vault already holds old posts, mark them as sent so the channel is not flooded:

   ```bash
   make content VAULT=../content
   PYTHONPATH=tools python3 -m blogtools.telegram --base-url https://domain --baseline
   git add state/telegram-sent.json && git commit -m "state: baseline"
   ```

### 6. The friend's Obsidian

1. Clone the content repository on each device and open it as a vault.
2. Install the community plugin **obsidian-git**. Settings: "Pull on startup" on; "Auto commit-and-sync interval" 0 (off), so the friend publishes on purpose with "Commit and sync"; "Pull before push" on.
3. The vault's `.obsidian/app.json` already sets attachments to `attachments/`, new notes to `posts/`, and the templates folder.
4. Read `راهنمای انتشار.md` in the vault together once.

## Daily operation

Nothing to do. Each push from Obsidian triggers a deploy. A daily run at 09:00 Tehran publishes posts whose date has arrived.

## Publishing from this computer (without GitHub Actions)

`make deploy` does on this computer what the deploy workflow does on GitHub: checks settings and notes, builds, uploads changed files to cPanel over FTPS, checks that the live site shows the new build, posts new articles to Telegram, and sends the maintainer notice.

1. `cp .env.example .env` and fill in the FTP account (cPanel → FTP Accounts) and, optionally, the Telegram values. `.env` is never committed.
2. Set the domain in the admin panel (Settings → Site → Site address), or in `site/data/settings.yaml`.
3. `make deploy-check` builds and lists what would be uploaded, without uploading.
4. `make deploy` publishes. The first run uploads everything; later runs upload only what changed.
5. If Telegram posts were sent, commit and push `state/telegram-sent.json`, so another computer does not send them again.

The tool keeps its list of uploaded files on the host in `.deploy-state.json` (blocked from the web by `.htaccess`). It never deletes files it did not upload. `python3 -m blogtools.deploy --full` uploads everything again, for example after files were changed by hand on the host.

Telegram from this computer needs access to api.telegram.org. If that is blocked, run with `--no-telegram` and send later.

## Staging

`pages.yml` builds a copy on GitHub Pages, hidden from search engines and never posting to Telegram. It uses the friend's vault once `CONTENT_REPO` is set, otherwise `example-vault/`. It runs on every push to `main` and on every content update. GitHub Pages needs a public repository or a paid plan (Settings → Pages → Source: GitHub Actions).

## Admin panel

`https://domain/admin/` (or the staging address + `/admin/`). There is no separate admin username and password: the panel signs in to GitHub, and GitHub decides who may change the site. Anyone with write access to the site repository can sign in by pasting a fine-grained token (Contents: read and write on this repository). For a local test without any login, run `make serve`, open `http://localhost:1313/admin/` in Chrome or Edge, choose "Work with Local Repository" and pick this folder. The panel changes `site/data/settings.yaml` and the about pages, which starts a deploy. A look change rewrites every page, so the upload takes longer than usual.

## When something fails

The private chat gets "❌" with the stage and a link to the run.

| Stage | Usual cause | Fix |
| --- | --- | --- |
| setup | A variable or token is missing or expired | Renew the token; check repository variables |
| content | A note breaks the rules (bad `slug`, `date`, duplicate `slug`, missing attachment, `aliases`) | The run log names the file and the problem; fix the note in Obsidian and push again |
| build | Template or Hugo error | Reproduce with `make build`; often a Hugo upgrade |
| deploy | FTP password changed, host down, quota full | Check cPanel; update `FTP_*` secrets |
| verify | Upload finished but the site shows an old build | Host cache or wrong `FTP_SERVER_DIR`; open `/build.json` |
| telegram | Bot removed from the channel, token revoked, bad formatting | Fix and re-run; posts already sent are not repeated |

## Roll back

1. Actions → Deploy → pick the last good run's commit, or revert the bad commit in the site repository.
2. Run the workflow by hand with **Telegram off**.

To undo a bad post, set `publish: false` on the note and push. The page disappears on the next deploy; the Telegram message stays (delete it in Telegram by hand).

## Changing an address after publishing

Avoid it: Telegram links are permanent. If it must happen, keep the `id`, change the `slug`, and add a line to `site/static/.htaccess`:

```
RewriteRule ^posts/old-slug/?$ /posts/new-slug/ [L,R=301]
```

## Upgrades

- **Hugo:** change `.hugo-version` on a branch, run `make test` with the same local version, compare the home page and one post in both languages and both looks, then merge.
- **Sveltia CMS:** change the version in `site/static/admin/index.html`, test signing in and saving settings.
- **GitHub Actions:** keep the pinned major versions; update them together.

## Backups

Both repositories are the backup. Keep a monthly `git clone --mirror` of each outside GitHub in case the account is restricted.
