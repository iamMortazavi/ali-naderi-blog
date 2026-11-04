<?php

namespace App\Services\Obsidian;

use App\Enums\PostSource;
use App\Enums\PostStatus;
use App\Enums\SyncStatus;
use App\Models\Post;
use App\Models\SyncRun;
use App\Support\SiteSettings;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Imports published notes from the Obsidian vault into the posts table. One way only: Obsidian → site.
 * This class reads the vault, checks the notes and writes the sync history; NoteImporter writes the posts.
 *
 * - Only notes inside the posts folder with "publish: true" are imported; everything else stays private.
 * - A note is matched to its post as described in NoteImporter, so renaming the file or changing the
 *   slug updates the same post.
 * - A note with errors (or one that cannot even be read) is skipped and reported; the other notes are
 *   still imported, and the post already on the site (if any) stays as it was.
 * - A post whose note was removed or set to "publish: false" is turned back into a draft, never deleted.
 *   If the posts folder has no notes at all, nothing is unpublished (a wrong folder or an empty checkout
 *   must not take the site offline) and the run fails.
 * - Posts written in the panel are never touched.
 * - When the vault's revision is the same as in the last run, that run succeeded and the sync settings
 *   did not change, nothing is done and no SyncRun is stored: run() returns that last run and
 *   skipped() is true. Vaults without a revision (a local folder that is not a clean git checkout)
 *   are always imported.
 */
class VaultSync
{
    private const SIGNATURE_KEY = 'blog:vault-sync-signature';

    private array $messages = [];

    private bool $skipped = false;

    /** @deprecated use VaultFactory::fromSettings() */
    public static function fromSettings(): ?Vault
    {
        return VaultFactory::fromSettings();
    }

    /** True when the last run() found nothing new and returned the previous run unchanged. */
    public function skipped(): bool
    {
        return $this->skipped;
    }

    /**
     * @param  string|null  $postsFolder  defaults to Settings → Obsidian sync → posts folder
     * @param  bool  $force  import even when the vault revision has not changed
     * @return SyncRun the new run, or the previous one when skipped() is true
     */
    public function run(Vault $vault, ?string $postsFolder = null, bool $force = false): SyncRun
    {
        $this->messages = [];
        $this->skipped = false;
        $postsFolder = trim($postsFolder ?? (string) SiteSettings::get('sync.posts_folder', 'posts'), '/');
        $counts = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'unpublished' => 0, 'skipped' => 0];

        $revision = null;
        $failure = null;
        try {
            $revision = $vault->revision();
        } catch (Throwable $e) {
            $failure = $e;
        }
        if (! $force && $revision !== null && ($last = $this->unchangedRun($vault, $revision, $postsFolder))) {
            $this->skipped = true;

            return $last;
        }

        $run = SyncRun::create([
            'source' => $vault->name(), 'status' => SyncStatus::Running, 'commit' => $revision, 'started_at' => now(),
        ]);

        try {
            if ($failure) {
                throw $failure;
            }
            $files = $vault->files();

            // Index the vault the way Obsidian resolves links: by file name, anywhere.
            $allNotes = [];
            $attachmentsByName = [];
            $notePaths = [];
            foreach ($files as $path => $fingerprint) {
                $name = mb_strtolower(basename($path));
                if (str_ends_with($name, '.md')) {
                    $allNotes[substr($name, 0, -3)] = true;
                    if (str_starts_with($path, $postsFolder.'/')) {
                        $notePaths[] = $path;
                    }
                } else {
                    $attachmentsByName[$name][] = $path;
                }
            }
            $importer = new NoteImporter($vault, $files, $notePaths);

            // Read and check the notes in the posts folder. One bad file never stops the others.
            $notes = [];
            $seen = [];
            foreach ($notePaths as $path) {
                try {
                    $note = new Note($path, $vault->read($path));
                } catch (Throwable $e) {
                    $this->error($path, ['note cannot be read: '.$e->getMessage()]);
                    $seen[] = $this->keep(fn () => $importer->findByPath($path));

                    continue;
                }
                if (! $note->publish && ! $note->errors) {
                    $counts['skipped']++;

                    continue;
                }
                $notes[] = $note;
            }
            $this->checkUnique($notes);

            $valid = array_values(array_filter($notes, fn (Note $n) => ! $n->errors));
            $byStem = [];
            $byKey = [];
            foreach ($valid as $n) {
                $byStem[$n->stem()][] = $n;
                $byKey[$n->key.':'.$n->locale] = $n;
            }

            foreach ($notes as $note) {
                if ($note->errors) {
                    $this->error($note->path, $note->errors);
                    $seen[] = $this->keep(fn () => $importer->existing($note)); // keep the version on the site

                    continue;
                }
                $converter = new LinkConverter($note, $byStem, $byKey, $allNotes, $attachmentsByName);
                try {
                    $counts[$importer->import($note, $converter)]++;
                } catch (Throwable $e) {
                    $this->error($note->path, [$e->getMessage()]);
                }
                $seen[] = $this->keep(fn () => $importer->existing($note));
                foreach (array_unique($converter->warnings) as $warning) {
                    $this->messages[] = ['level' => 'warning', 'file' => $note->path, 'text' => $warning];
                }
            }

            $counts['unpublished'] = $this->unpublishMissing($notePaths, array_filter($seen), $postsFolder);
            $status = collect($this->messages)->contains('level', 'error') ? SyncStatus::Failed : SyncStatus::Success;
        } catch (Throwable $e) {
            $this->messages[] = ['level' => 'error', 'file' => null, 'text' => $e->getMessage()];
            $status = SyncStatus::Failed;
        }

        $run->fill([
            'status' => $status,
            'created_count' => $counts['created'],
            'updated_count' => $counts['updated'],
            'unpublished_count' => $counts['unpublished'],
            'unchanged_count' => $counts['unchanged'],
            'skipped_count' => $counts['skipped'],
            'messages' => $this->messages,
            'finished_at' => now(),
        ])->save();

        if ($status === SyncStatus::Success && $revision !== null) {
            Cache::forever(self::SIGNATURE_KEY, $this->signature($vault, $revision, $postsFolder, $run->id));
        }

        return $run;
    }

    /** Posts whose note disappeared or became private go back to draft. */
    private function unpublishMissing(array $notePaths, array $seenIds, string $postsFolder): int
    {
        $gone = Post::query()->where('source', PostSource::Obsidian)->where('status', PostStatus::Published)
            ->whereNotIn('id', $seenIds)->get();
        if ($gone->isEmpty()) {
            return 0;
        }
        if ($notePaths === []) {
            $this->messages[] = ['level' => 'error', 'file' => null, 'text' => "the posts folder '{$postsFolder}/' has no notes; "
                .'nothing was unpublished. Check the posts folder in Settings → Obsidian sync'];

            return 0;
        }
        foreach ($gone as $post) {
            $post->update(['status' => PostStatus::Draft]);
            $this->messages[] = ['level' => 'info', 'file' => $post->source_path, 'text' => 'note removed or made private; post set to draft'];
        }

        return $gone->count();
    }

    /** The id of the post to keep online, swallowing lookup errors (the note already has an error). */
    private function keep(callable $find): ?int
    {
        try {
            return $find()?->id;
        } catch (Throwable) {
            return null;
        }
    }

    /** The last run, when it succeeded on this same revision with the same settings. */
    private function unchangedRun(Vault $vault, string $revision, string $postsFolder): ?SyncRun
    {
        $last = SyncRun::query()->latest('id')->first();
        if (! $last || $last->status !== SyncStatus::Success || $last->commit !== $revision) {
            return null;
        }

        return Cache::get(self::SIGNATURE_KEY) === $this->signature($vault, $revision, $postsFolder, $last->id) ? $last : null;
    }

    private function signature(Vault $vault, string $revision, string $postsFolder, int $runId): string
    {
        return hash('sha256', json_encode([$vault->name(), $revision, $postsFolder, SiteSettings::group('sync'), $runId]));
    }

    /** @param list<Note> $notes */
    private function checkUnique(array $notes): void
    {
        $slugs = [];
        $keys = [];
        foreach ($notes as $n) {
            if ($n->errors) {
                continue;
            }
            $slug = $n->locale.':'.$n->slug;
            $key = $n->locale.':'.$n->key;
            if (isset($slugs[$slug])) {
                $n->errors[] = "slug '{$n->slug}' is already used by {$slugs[$slug]}";
            } elseif (isset($keys[$key])) {
                $n->errors[] = "id '{$n->key}' is already used by {$keys[$key]}";
            }
            $slugs[$slug] ??= $n->path;
            $keys[$key] ??= $n->path;
        }
    }

    private function error(?string $file, array $errors): void
    {
        foreach ($errors as $text) {
            $this->messages[] = ['level' => 'error', 'file' => $file, 'text' => $text];
        }
    }
}
