<?php

namespace App\Services\Agents\Skills;

use App\Enums\Agents\SkillSourceStatus;
use App\Jobs\Agents\SyncSkillSourceJob;
use App\Models\Agents\SkillSource;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Agents\Knowledge\KnowledgeSources;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Previewing, connecting, re-syncing and disconnecting GitHub repositories
 * as skill sources. `repo` may be `owner/name` or any GitHub link to the
 * repository — a `/tree/{branch}/{folder}` link also sets the branch and
 * folder, unless they're given separately.
 */
class SkillSources
{
    /** Most repositories one workspace may sync skills from. */
    public const int MAX_PER_WORKSPACE = 20;

    public function __construct(
        private readonly KnowledgeSources $accounts,
        private readonly GitHubSkillRepository $repository,
        private readonly SkillPackageParser $parser,
    ) {}

    /**
     * Reads the repository without saving anything, so the member sees which
     * skills it holds before importing them.
     *
     * @param  array{repo?: string, branch?: string|null, path?: string|null, credential_id?: string|null}  $data
     * @return array{repo: string, branch: string, path: string|null, commit_sha: string, skills: list<array{path: string, name: string, description: string|null}>}
     *
     * @throws \RuntimeException when GitHub can't be read
     */
    public function preview(Workspace $workspace, User $user, array $data): array
    {
        $source = $this->unsaved($workspace, $user, $data);

        $branch = $this->repository->branch($source);
        $sha = $this->repository->headCommit($source, $branch);
        $packages = $this->parser->parse($this->repository->files($source, $sha), $source->path);

        return [
            'repo' => $source->repo,
            'branch' => $branch,
            'path' => $source->path,
            'commit_sha' => $sha,
            'skills' => array_map(fn (SkillPackage $package): array => [
                'path' => $package->path,
                'name' => $package->name,
                'description' => $package->description,
            ], $packages),
        ];
    }

    /**
     * Without a `credential_id`, reads with the member's GitHub account if
     * they have one, else anonymously — enough for a public repository.
     *
     * @param  array{repo?: string, branch?: string|null, path?: string|null, credential_id?: string|null, is_shared?: bool}  $data
     */
    public function create(Workspace $workspace, User $user, array $data): SkillSource
    {
        $source = $this->unsaved($workspace, $user, $data);

        if ($workspace->skillSources()->count() >= self::MAX_PER_WORKSPACE) {
            throw ValidationException::withMessages(['repo' => 'This workspace has reached its limit of skill repositories.']);
        }

        if ($workspace->skillSources()->whereRaw('lower(repo) = ?', [strtolower($source->repo)])->where('path', $source->path)->exists()) {
            throw ValidationException::withMessages(['repo' => 'Skills are already synced from this repository folder.']);
        }

        $source->forceFill(['is_shared' => (bool) ($data['is_shared'] ?? true)])->save();

        $this->queueSync($source, force: true);

        return $source;
    }

    public function queueSync(SkillSource $source, bool $force = false): void
    {
        $source->forceFill(['status' => SkillSourceStatus::Pending])->save();

        SyncSkillSourceJob::dispatch($source, $force);
    }

    /**
     * Stops syncing. Kept skills become ordinary skills, editable in the app;
     * otherwise they're removed with the source.
     */
    public function disconnect(SkillSource $source, bool $keepSkills): void
    {
        DB::transaction(function () use ($source, $keepSkills): void {
            if ($keepSkills) {
                $source->skills()->withTrashed()->update(['skill_source_id' => null, 'source_path' => null]);
            } else {
                $source->skills()->delete();
            }

            $source->delete();
        });
    }

    /**
     * The source described by `$data`, validated but not saved.
     *
     * @param  array<string, mixed>  $data
     */
    private function unsaved(Workspace $workspace, User $user, array $data): SkillSource
    {
        $location = $this->location((string) ($data['repo'] ?? ''));

        $validated = Validator::make([
            ...$data,
            'repo' => $location['repo'],
            'branch' => filled($data['branch'] ?? null) ? $data['branch'] : $location['branch'],
            'path' => filled($data['path'] ?? null) ? $data['path'] : $location['path'],
        ], [
            'repo' => ['required', 'string', 'max:200', 'regex:/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/'],
            'branch' => ['nullable', 'string', 'max:255', 'regex:/^[^\s~^:?*\[\\\\]+$/'],
            'path' => ['nullable', 'string', 'max:500', 'not_regex:/(^|\/)\.\.(\/|$)/'],
            'credential_id' => ['nullable', 'string'],
        ], [
            'repo.regex' => 'Enter a GitHub repository as owner/name, or paste its link.',
        ])->validate();

        $credential = filled($validated['credential_id'] ?? null)
            ? $this->accounts->credential($workspace, $user, 'github', $validated['credential_id'])
            : $this->accounts->accounts($workspace, $user, 'github')->first();

        $source = $workspace->skillSources()->make([
            'created_by' => $user->id,
            'connector_credential_id' => $credential?->id,
            'repo' => $validated['repo'],
            'branch' => filled($validated['branch'] ?? null) ? trim($validated['branch']) : null,
            'path' => $this->path($validated['path'] ?? null),
        ]);

        return $source->setRelation('credential', $credential);
    }

    /**
     * Reads `owner/name` out of whatever was entered: `owner/name`, a
     * github.com link (with or without `.git`), or a `/tree/` or `/blob/`
     * link, which also names a branch and folder. A branch with a slash in
     * its name can't be told apart from a folder in a link — give it
     * separately.
     *
     * @return array{repo: string, branch: string|null, path: string|null}
     */
    private function location(string $input): array
    {
        $input = trim($input);
        $pattern = '#^(?:(?:https?://)?(?:www\.)?github\.com/|git@github\.com:)?([^/\s]+)/([^/\s?\#]+?)(?:\.git)?(?:/(?:tree|blob)/([^/\s?\#]+)(?:/([^?\#]*))?)?/?(?:[?\#].*)?$#i';

        if (! preg_match($pattern, $input, $match)) {
            return ['repo' => $input, 'branch' => null, 'path' => null];
        }

        $path = isset($match[4]) ? rawurldecode($match[4]) : null;

        // A link to a file (usually a SKILL.md) points at its folder.
        if ($path !== null && preg_match('#(^|/)[^/]+\.[A-Za-z0-9]+$#', $path)) {
            $path = dirname($path) === '.' ? null : dirname($path);
        }

        return [
            'repo' => "{$match[1]}/{$match[2]}",
            'branch' => isset($match[3]) && $match[3] !== '' ? rawurldecode($match[3]) : null,
            'path' => $path,
        ];
    }

    private function path(?string $path): ?string
    {
        $path = trim((string) $path, " /\t");

        return $path === '' ? null : $path;
    }
}
