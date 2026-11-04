<?php

namespace App\Services\Obsidian;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * A vault kept in a GitHub repository (pushed from Obsidian with obsidian-git).
 * Uses the GitHub API over HTTPS, so the host needs no git and no SSH. Downloaded files are kept
 * by their Git blob id under storage/app/vault-cache, so unchanged files are never downloaded twice.
 */
class GitHubVault implements Vault
{
    private ?array $tree = null;

    private ?string $commit = null;

    public function __construct(
        private string $repo,
        private string $branch,
        private ?string $token,
    ) {
        if (! preg_match('#^[\w.-]+/[\w.-]+$#', $repo)) {
            throw new RuntimeException("Invalid GitHub repository name: {$repo}");
        }
    }

    private function api(string $path, array $query = [])
    {
        $request = Http::acceptJson()->timeout(30)->retry(2, 1000, throw: false)
            ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28']);
        if ($this->token) {
            $request = $request->withToken($this->token);
        }
        $response = $request->get("https://api.github.com/repos/{$this->repo}/{$path}", $query);
        if ($response->failed()) {
            throw new RuntimeException("GitHub API {$path}: HTTP {$response->status()} ".($response->json('message') ?? ''));
        }

        return $response;
    }

    public function files(): array
    {
        if ($this->tree === null) {
            $commit = $this->api('commits/'.rawurlencode($this->branch))->json();
            $this->commit = $commit['sha'];
            $tree = $this->api('git/trees/'.$commit['commit']['tree']['sha'], ['recursive' => 1])->json();
            if (! empty($tree['truncated'])) {
                throw new RuntimeException('The vault has too many files for one GitHub API call.');
            }
            $this->tree = [];
            foreach ($tree['tree'] as $item) {
                $hidden = collect(explode('/', $item['path']))->contains(fn ($part) => str_starts_with($part, '.'));
                if ($item['type'] === 'blob' && ! $hidden) {
                    $this->tree[$item['path']] = $item['sha'];
                }
            }
            ksort($this->tree);
        }

        return $this->tree;
    }

    public function read(string $path): string
    {
        return file_get_contents($this->localCopy($path));
    }

    public function localCopy(string $path): string
    {
        $sha = $this->files()[$path] ?? throw new RuntimeException("Not in the vault: {$path}");
        $cache = 'vault-cache/'.substr($sha, 0, 2).'/'.$sha;
        $disk = Storage::disk('local');
        if (! $disk->exists($cache)) {
            $blob = $this->api("git/blobs/{$sha}")->json();
            $disk->put($cache, base64_decode($blob['content']));
        }

        return $disk->path($cache);
    }

    public function revision(): ?string
    {
        $this->files();

        return $this->commit ? substr($this->commit, 0, 12) : null;
    }

    public function name(): string
    {
        return 'github';
    }
}
