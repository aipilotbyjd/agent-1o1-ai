<?php

namespace App\Services\Agents\Skills;

use App\Models\Agents\SkillSource;
use App\Services\Connectors\ConnectorTokens;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PharData;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

/**
 * Reads a skill source's repository from GitHub: the branch's head commit
 * (one call, so an unchanged repository costs nothing more), and the files
 * at that commit as a single tarball — one request however many skills,
 * which keeps an anonymous public-repository sync inside GitHub's 60
 * requests an hour. The archive is read in memory; nothing is extracted.
 */
class GitHubSkillRepository
{
    private const string BASE_URL = 'https://api.github.com';

    /** Largest archive downloaded, in bytes. */
    public const int MAX_ARCHIVE_BYTES = 25 * 1024 * 1024;

    /** Largest single file read from it, in bytes; bigger ones are skipped. */
    public const int MAX_FILE_BYTES = 256 * 1024;

    public function __construct(private readonly ConnectorTokens $tokens) {}

    /**
     * The branch to sync — the source's own, or the repository's default.
     */
    public function branch(SkillSource $source): string
    {
        if ($source->branch !== null && $source->branch !== '') {
            return $source->branch;
        }

        return (string) $this->get($source, "/repos/{$source->repo}")->json('default_branch');
    }

    /**
     * The commit at the head of `$branch`.
     */
    public function headCommit(SkillSource $source, string $branch): string
    {
        $sha = $this->get($source, "/repos/{$source->repo}/branches/".rawurlencode($branch))->json('commit.sha');

        return is_string($sha) && $sha !== '' ? $sha : throw new RuntimeException("GitHub returned no commit for branch \"{$branch}\".");
    }

    /**
     * Every file at `$sha` up to `MAX_FILE_BYTES`, keyed by its path from the
     * repository root.
     *
     * @return array<string, string>
     */
    public function files(SkillSource $source, string $sha): array
    {
        $archive = tempnam(sys_get_temp_dir(), 'skills-').'.tar.gz';

        try {
            $body = $this->request($source)
                ->timeout(120)
                ->get(self::BASE_URL."/repos/{$source->repo}/tarball/{$sha}")
                ->throw(fn (Response $response) => throw $this->failure($source, $response))
                ->body();

            if (strlen($body) > self::MAX_ARCHIVE_BYTES) {
                throw new RuntimeException('The repository is larger than '.(self::MAX_ARCHIVE_BYTES / 1024 / 1024).' MB. Keep skills in a smaller repository.');
            }

            file_put_contents($archive, $body);
            unset($body);

            return $this->read($archive);
        } finally {
            @unlink($archive);
            @unlink(Str::beforeLast($archive, '.tar.gz'));
        }
    }

    /**
     * GitHub's tarball wraps everything in one `{owner}-{repo}-{sha}/`
     * folder, which is dropped from the paths.
     *
     * @return array<string, string>
     */
    private function read(string $archive): array
    {
        try {
            $tar = new PharData($archive);
        } catch (Throwable $e) {
            throw new RuntimeException('GitHub returned an archive that could not be read.', previous: $e);
        }

        $prefix = 'phar://'.$tar->getPath().'/';
        $files = [];

        $entries = new RecursiveIteratorIterator($tar, RecursiveIteratorIterator::LEAVES_ONLY);
        $entries->setMaxDepth(32);

        foreach ($entries as $entry) {
            if (! $entry->isFile() || $entry->getSize() > self::MAX_FILE_BYTES) {
                continue;
            }

            $path = Str::after(Str::after($entry->getPathname(), $prefix), '/');

            if ($path !== '') {
                $files[$path] = (string) file_get_contents($entry->getPathname());
            }
        }

        unset($entries, $tar);

        return $files;
    }

    private function get(SkillSource $source, string $endpoint): Response
    {
        return $this->request($source)
            ->get(self::BASE_URL.$endpoint)
            ->throw(fn (Response $response) => throw $this->failure($source, $response));
    }

    private function request(SkillSource $source): PendingRequest
    {
        $request = Http::withHeaders(['Accept' => 'application/vnd.github+json', 'X-GitHub-Api-Version' => '2022-11-28'])->timeout(30);

        return $source->credential === null ? $request : $request->withToken($this->tokens->accessToken($source->credential));
    }

    /**
     * What went wrong, in words someone can act on.
     */
    private function failure(SkillSource $source, Response $response): RuntimeException
    {
        $message = match (true) {
            $response->status() === 404 => $source->credential === null
                ? "Couldn't find {$source->repo} or its branch. A private repository needs a connected GitHub account."
                : "Couldn't find {$source->repo} or its branch, or the connected GitHub account can't see it.",
            $response->status() === 401 => 'GitHub rejected the connected account. Reconnect GitHub in Apps.',
            $response->status() === 403 && $response->header('X-RateLimit-Remaining') === '0' => 'GitHub\'s rate limit was reached. It will sync again later'.($source->credential === null ? '; connect a GitHub account for a higher limit.' : '.'),
            default => 'GitHub error: '.Str::limit((string) ($response->json('message') ?? $response->body()), 200),
        };

        return new RuntimeException($message);
    }
}
