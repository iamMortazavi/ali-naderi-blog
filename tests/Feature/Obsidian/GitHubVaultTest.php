<?php

namespace Tests\Feature\Obsidian;

use App\Services\Obsidian\GitHubVault;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use RuntimeException;
use Tests\TestCase;

class GitHubVaultTest extends TestCase
{
    private const TOKEN = 'github_pat_SECRET123';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Sleep::fake();
    }

    private function fakeRepo(bool $truncated = false, $contents = null): void
    {
        Http::fake([
            'api.github.com/repos/me/vault/commits/main' => Http::response([
                'sha' => 'abcdef1234567890abcdef1234567890abcdef12',
                'commit' => ['tree' => ['sha' => 'tree1']],
            ]),
            'api.github.com/repos/me/vault/git/trees/tree1*' => Http::response([
                'truncated' => $truncated,
                'tree' => [
                    ['path' => 'posts', 'type' => 'tree', 'sha' => 't1'],
                    ['path' => 'posts/b.md', 'type' => 'blob', 'sha' => 'bb22'],
                    ['path' => 'posts/a.md', 'type' => 'blob', 'sha' => 'aa11'],
                    ['path' => '.obsidian/app.json', 'type' => 'blob', 'sha' => 'cc33'],
                    ['path' => 'posts/.trash/old.md', 'type' => 'blob', 'sha' => 'dd44'],
                ],
            ]),
            'api.github.com/repos/me/vault/contents/*' => $contents
                ?? Http::response("---\nslug: a\n---\nسلام", 200, ['Content-Type' => 'application/vnd.github.raw']),
        ]);
    }

    private function vault(?string $token = self::TOKEN): GitHubVault
    {
        return new GitHubVault('me/vault', 'main', $token);
    }

    public function test_lists_visible_files_with_their_blob_ids(): void
    {
        $this->fakeRepo();
        $vault = $this->vault();

        $this->assertSame(['posts/a.md' => 'aa11', 'posts/b.md' => 'bb22'], $vault->files());
        $this->assertSame('abcdef123456', $vault->revision());
        Http::assertSentCount(2);
    }

    public function test_revision_needs_only_the_commit(): void
    {
        $this->fakeRepo();

        $this->assertSame('abcdef123456', $this->vault()->revision());
        Http::assertSentCount(1);
    }

    public function test_a_truncated_tree_is_an_error(): void
    {
        $this->fakeRepo(truncated: true);

        $this->expectExceptionMessage('too many files');
        $this->vault()->files();
    }

    public function test_files_are_downloaded_raw_once_and_cached_by_blob_id(): void
    {
        $this->fakeRepo();
        $vault = $this->vault();

        $this->assertSame("---\nslug: a\n---\nسلام", $vault->read('posts/a.md'));
        $this->assertSame("---\nslug: a\n---\nسلام", $vault->read('posts/a.md'));
        Storage::disk('local')->assertExists('vault-cache/aa/aa11');

        $downloads = Http::recorded(fn (Request $r) => str_contains($r->url(), '/contents/'));
        $this->assertCount(1, $downloads);
        [$request] = $downloads->first();
        $this->assertTrue($request->hasHeader('Accept', 'application/vnd.github.raw'));
        $this->assertStringContainsString('/contents/posts/a.md?ref=abcdef1234567890abcdef1234567890abcdef12', $request->url());

        // A new vault object (next sync run) uses the cache too.
        $this->vault()->localCopy('posts/a.md');
        $this->assertCount(1, Http::recorded(fn (Request $r) => str_contains($r->url(), '/contents/')));
    }

    public function test_the_token_is_sent_as_a_bearer_header(): void
    {
        $this->fakeRepo();
        $this->vault()->files();

        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer '.self::TOKEN)
            && ! str_contains($r->url(), self::TOKEN));
    }

    public function test_no_token_means_no_authorization_header(): void
    {
        $this->fakeRepo();
        $this->vault(null)->files();

        Http::assertSent(fn (Request $r) => ! $r->hasHeader('Authorization'));
    }

    public function test_an_invalid_repository_name_is_refused(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid GitHub repository name');
        new GitHubVault('https://github.com/me/vault', 'main', null);
    }

    public function test_the_token_never_appears_in_error_messages(): void
    {
        Http::fake(fn () => Http::failedConnection('cURL error 28 for https://x-access-token:'.self::TOKEN.'@api.github.com'));

        try {
            $this->vault()->files();
            $this->fail('no exception');
        } catch (RuntimeException $e) {
            $this->assertStringNotContainsString(self::TOKEN, $e->getMessage());
            $this->assertStringContainsString('***', $e->getMessage());
            $this->assertNull($e->getPrevious());
        }
    }

    public function test_http_errors_explain_what_to_check(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Not Found'], 404)]);

        $this->expectExceptionMessage('HTTP 404 Not Found');
        $this->vault()->revision();
    }

    public function test_a_failed_download_leaves_nothing_in_the_cache(): void
    {
        $this->fakeRepo(contents: Http::response(['message' => 'Server Error'], 500));
        $vault = $this->vault();

        try {
            $vault->read('posts/b.md');
            $this->fail('no exception');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('HTTP 500', $e->getMessage());
        }
        $this->assertSame([], Storage::disk('local')->allFiles('vault-cache'));
    }
}
