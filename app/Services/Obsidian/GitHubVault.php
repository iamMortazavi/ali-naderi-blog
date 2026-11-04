<?php

namespace App\Services\Obsidian;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * A vault kept in a GitHub repository (pushed from Obsidian with obsidian-git).
 * Uses the GitHub API over HTTPS, so the host needs no git and no SSH. Downloaded files are kept
 * by their Git blob id under storage/app/vault-cache, so unchanged files are never downloaded twice.
 * Files are streamed straight to that cache (raw contents API), never held in memory as base64.
 */
class GitHubVault implements Vault
{
    private const API = 'https://api.github.com/repos/';

    /** @var array<string,string>|null vault path → blob sha */
    private ?array $tree = null;

    /** @var array{sha:string,tree:string}|null */
    private ?array $commit = null;

    public function __construct(
        private string $repo,
        private string $branch,
        private ?string $token,
    ) {
        if (! preg_match('#^[\w.-]+/[\w.-]+$#', $repo)) {
            throw new RuntimeException('Invalid GitHub repository name: '.json_encode($repo, JSON_UNESCAPED_UNICODE).' (expected owner/name)');
        }
    }

    public function files(): array
    {
        if ($this->tree === null) {
            $tree = $this->get('git/trees/'.$this->commit()['tree'], ['recursive' => 1])->json();
            if (! empty($tree['truncated'])) {
                throw new RuntimeException('The vault has too many files for one GitHub API call.');
            }
            $files = [];
            foreach ($tree['tree'] ?? [] as $item) {
                $hidden = collect(explode('/', $item['path']))->contains(fn ($part) => str_starts_with($part, '.'));
                if (($item['type'] ?? null) === 'blob' && ! $hidden) {
                    $files[$item['path']] = $item['sha'];
                }
            }
            ksort($files);
            $this->tree = $files;
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
        if ($disk->exists($cache)) {
            return $disk->path($cache);
        }

        $disk->makeDirectory(dirname($cache));
        $target = $disk->path($cache);
        $part = $target.'.'.bin2hex(random_bytes(4)).'.part';
        $segments = implode('/', array_map('rawurlencode', explode('/', $path)));
        try {
            $response = $this->request()
                ->accept('application/vnd.github.raw')
                ->timeout(120)
                ->sink($part)
                ->get(self::API."{$this->repo}/contents/{$segments}", ['ref' => $this->commit()['sha']]);
            $this->check($response, "download {$path}");
            if (! is_file($part) || ! rename($part, $target)) {
                throw new RuntimeException("Could not save {$path} in the vault cache.");
            }
        } catch (Throwable $e) {
            throw $this->safe($e);
        } finally {
            if (is_file($part)) {
                @unlink($part);
            }
        }

        return $target;
    }

    public function revision(): ?string
    {
        return substr($this->commit()['sha'], 0, 12);
    }

    public function name(): string
    {
        return 'github';
    }

    /** @return array{sha:string,tree:string} the branch's latest commit (one API call, made once) */
    private function commit(): array
    {
        if ($this->commit === null) {
            $commit = $this->get('commits/'.rawurlencode($this->branch))->json();
            if (! isset($commit['sha'], $commit['commit']['tree']['sha'])) {
                throw new RuntimeException("GitHub did not return the latest commit of branch {$this->branch}.");
            }
            $this->commit = ['sha' => $commit['sha'], 'tree' => $commit['commit']['tree']['sha']];
        }

        return $this->commit;
    }

    private function request(): PendingRequest
    {
        $request = Http::withHeaders(['X-GitHub-Api-Version' => '2022-11-28']);

        return $this->token ? $request->withToken($this->token) : $request;
    }

    private function get(string $path, array $query = []): Response
    {
        try {
            $response = $this->request()->acceptJson()->timeout(30)->retry(2, 1000, throw: false)
                ->get(self::API."{$this->repo}/{$path}", $query);
            $this->check($response, $path);

            return $response;
        } catch (Throwable $e) {
            throw $this->safe($e);
        }
    }

    private function check(Response $response, string $what): void
    {
        if ($response->successful()) {
            return;
        }
        $message = $response->header('Content-Type') && str_contains($response->header('Content-Type'), 'json')
            ? (string) ($response->json('message') ?? '') : '';
        $hint = match ($response->status()) {
            401 => ' (the token in VAULT_GITHUB_TOKEN is invalid or expired)',
            403, 404 => ' (check the repository name, the branch and that the token can read the repository)',
            default => '',
        };
        throw new RuntimeException(trim("GitHub API {$what}: HTTP {$response->status()} {$message}").$hint);
    }

    /** The same error without the token in it, whatever produced it. */
    private function safe(Throwable $e): RuntimeException
    {
        $message = $e->getMessage();
        if ($this->token) {
            $message = str_replace($this->token, '***', $message);
        }

        return new RuntimeException($message);
    }
}
