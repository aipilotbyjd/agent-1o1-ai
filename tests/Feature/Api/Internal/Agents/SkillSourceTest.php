<?php

use App\Ai\Tools\UpdateSkillTool;
use App\Enums\Agents\SkillSourceStatus;
use App\Enums\Connectors\ConnectorCredentialScope;
use App\Enums\Workspaces\Role;
use App\Models\Agents\Agent;
use App\Models\Agents\Skill;
use App\Models\Agents\SkillSource;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\User;
use App\Services\Agents\Skills\SkillSync;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;
use Laravel\Passport\Passport;

/**
 * A gzipped tarball shaped like GitHub's: everything inside one
 * `{owner}-{repo}-{sha}/` folder.
 *
 * @param  array<string, string>  $files
 */
function skillRepoTarball(array $files): string
{
    $dir = sys_get_temp_dir().'/skill-repo-'.uniqid();
    mkdir($dir);
    $tar = new PharData("{$dir}/repo.tar");

    foreach ($files as $path => $content) {
        $tar->addFromString("acme-skills-0123abc/{$path}", $content);
    }

    $tar->compress(Phar::GZ);
    $body = (string) file_get_contents("{$dir}/repo.tar.gz");
    unset($tar);
    array_map(unlink(...), glob("{$dir}/*"));
    rmdir($dir);

    return $body;
}

/**
 * Fakes GitHub for `acme/skills`: the default branch `main`, then each
 * commit in turn with its files.
 *
 * @param  array<string, array<string, string>>  $commits  files keyed by commit sha
 */
function fakeSkillRepo(array $commits): void
{
    $heads = Http::sequence();
    $tarballs = [];

    foreach ($commits as $sha => $files) {
        $heads->push(['name' => 'main', 'commit' => ['sha' => $sha]]);
        $tarballs["https://api.github.com/repos/acme/skills/tarball/{$sha}"] = Http::response(skillRepoTarball($files));
    }

    $heads->whenEmpty(Http::response(['name' => 'main', 'commit' => ['sha' => array_key_last($commits)]]));

    Http::fake([
        'https://api.github.com/repos/acme/skills/branches/main' => $heads,
        ...$tarballs,
        'https://api.github.com/repos/acme/skills' => Http::response(['default_branch' => 'main']),
    ]);
}

const SKILL_MD = <<<'MD'
---
name: weekly-mrr-report
description: >
  Builds the weekly MRR report.
  Load it for revenue updates.
---
# Weekly MRR report

1. Pull subscriptions.
2. Fill `templates/report.md`.
MD;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    $this->url = "/api/v1/workspaces/{$this->workspace->id}/skill-sources";
});

it('imports every SKILL.md folder as a skill with its references and scripts', function () {
    fakeSkillRepo(['sha1' => [
        'README.md' => 'Not a skill.',
        'skills/mrr/SKILL.md' => SKILL_MD,
        'skills/mrr/templates/report.md' => '## MRR: {total}',
        'skills/mrr/scripts/compute.py' => 'print(1)',
        'skills/mrr/logo.png' => "\x89PNG\x00\x01",
        'skills/mrr/nested/SKILL.md' => "---\nname: nested-skill\ndescription: 'Its own skill'\n---\nDo the nested thing.",
        'skills/mrr/nested/notes.md' => 'Nested notes',
        'skills/triage/SKILL.md' => 'Triage tickets by severity.',
    ]]);
    Passport::actingAs($this->owner);

    $this->postJson($this->url, ['repo' => 'acme/skills', 'path' => '/skills/'])
        ->assertCreated()
        ->assertJsonPath('data.source.path', 'skills');

    $source = SkillSource::query()->sole();
    expect($source)->status->toBe(SkillSourceStatus::Ready)->last_commit_sha->toBe('sha1')->skills_count->toBe(3)->connector_credential_id->toBeNull();

    $mrr = $source->skills()->where('source_path', 'skills/mrr')->sole();
    expect($mrr)
        ->name->toBe('weekly-mrr-report')
        ->description->toBe('Builds the weekly MRR report. Load it for revenue updates.')
        ->instructions->toStartWith('# Weekly MRR report')
        ->is_shared->toBeTrue()
        ->created_by->toBe($this->owner->id)
        ->and($mrr->references->pluck('content', 'title')->all())->toBe(['templates/report.md' => '## MRR: {total}'])
        ->and($mrr->scripts()->sole()->only(['name', 'language', 'code']))->toBe(['name' => 'scripts/compute.py', 'language' => 'python', 'code' => 'print(1)']);

    expect($source->skills()->where('source_path', 'skills/mrr/nested')->sole())
        ->name->toBe('nested-skill')->description->toBe('Its own skill')
        ->and($source->skills()->where('source_path', 'skills/mrr/nested')->sole()->references->pluck('title')->all())->toBe(['notes.md']);

    // No frontmatter: named after its folder.
    expect($source->skills()->where('source_path', 'skills/triage')->sole())->name->toBe('triage')->instructions->toBe('Triage tickets by severity.');

    $this->getJson("/api/v1/workspaces/{$this->workspace->id}/skills/{$mrr->id}")
        ->assertJsonPath('data.skill.skill_source_id', $source->id)
        ->assertJsonPath('data.skill.source_url', 'https://github.com/acme/skills/tree/HEAD/skills/mrr');
});

it('re-imports only when the branch moves, then updates, removes and restores skills to match', function () {
    fakeSkillRepo([
        'sha1' => ['a/SKILL.md' => 'Version one.', 'b/SKILL.md' => 'B skill.'],
        'sha2' => ['a/SKILL.md' => 'Version two.', 'a/ref.md' => 'New reference'],
        'sha3' => ['a/SKILL.md' => 'Version two.', 'a/ref.md' => 'New reference', 'b/SKILL.md' => 'B is back.'],
    ]);
    $source = SkillSource::factory()->create(['workspace_id' => $this->workspace->id, 'created_by' => $this->owner->id, 'branch' => null]);
    $sync = app(SkillSync::class);

    $sync->sync($source);
    $a = $source->skills()->where('source_path', 'a')->sole();
    $b = $source->skills()->where('source_path', 'b')->sole();
    $agent = Agent::factory()->create(['workspace_id' => $this->workspace->id]);
    $agent->skills()->attach([$a->id, $b->id]);

    $sync->sync($source->refresh());
    expect($a->refresh())->instructions->toBe('Version two.')->version->toBe(2)
        ->and($a->references()->sole()->title)->toBe('ref.md')
        ->and(Skill::withTrashed()->find($b->id)->trashed())->toBeTrue()
        ->and($source->refresh()->skills_count)->toBe(1);

    $sync->sync($source->refresh());
    expect(Skill::query()->find($b->id))->not->toBeNull()->instructions->toBe('B is back.')
        ->and($a->refresh()->version)->toBe(2)
        ->and($agent->skills()->pluck('skills.id')->sort()->values()->all())->toBe(collect([$a->id, $b->id])->sort()->values()->all());

    // The branch hasn't moved: only its head is checked, nothing is downloaded.
    $tarballsBefore = count(Http::recorded(fn (HttpRequest $request): bool => str_contains($request->url(), '/tarball/')));
    $sync->sync($source->refresh());

    expect(Http::recorded(fn (HttpRequest $request): bool => str_contains($request->url(), '/tarball/')))->toHaveCount($tarballsBefore)
        ->and($source->refresh()->status)->toBe(SkillSourceStatus::Ready);
});

it('reads with a connected GitHub account and keeps the skills when a sync fails', function () {
    $connector = Connector::query()->where('key', 'github')->first() ?? Connector::factory()->create(['key' => 'github', 'name' => 'GitHub']);
    $credential = ConnectorCredential::factory()->forWorkspace($this->workspace)->forConnector($connector)->create([
        'scope' => ConnectorCredentialScope::Personal, 'created_by' => $this->owner->id, 'name' => 'My GitHub', 'data' => ['access_token' => 'gh-token'],
    ]);
    Http::fake([
        'https://api.github.com/repos/acme/skills/branches/main' => Http::sequence()
            ->push(['commit' => ['sha' => 'sha1']])
            ->push(['commit' => ['sha' => 'sha2']]),
        'https://api.github.com/repos/acme/skills/tarball/sha1' => Http::response(skillRepoTarball(['SKILL.md' => "---\nname: Root skill\n---\nAt the root."])),
        'https://api.github.com/repos/acme/skills/tarball/sha2' => Http::response(['message' => 'Not Found'], 404),
    ]);
    Passport::actingAs($this->owner);

    $this->postJson($this->url, ['repo' => 'acme/skills', 'branch' => 'main'])
        ->assertCreated()
        ->assertJsonPath('data.source.account', 'My GitHub');

    Http::assertSent(fn (HttpRequest $request): bool => $request->hasHeader('Authorization', 'Bearer gh-token'));
    $source = SkillSource::query()->sole();
    expect($source->connector_credential_id)->toBe($credential->id)->and($source->skills()->sole()->name)->toBe('Root skill');

    $this->postJson("{$this->url}/{$source->id}/sync")->assertOk();

    expect($source->refresh())->status->toBe(SkillSourceStatus::Failed)
        ->last_error->toContain("can't see it")
        ->and($source->skills()->count())->toBe(1);
});

it('keeps synced skills read-only except for how they look and who sees them', function () {
    fakeSkillRepo(['sha1' => ['s/SKILL.md' => 'Synced.']]);
    $source = SkillSource::factory()->create(['workspace_id' => $this->workspace->id, 'created_by' => $this->owner->id]);
    app(SkillSync::class)->sync($source);
    $skill = $source->skills()->sole();
    $skillUrl = "/api/v1/workspaces/{$this->workspace->id}/skills/{$skill->id}";
    Passport::actingAs($this->owner);

    $this->patchJson($skillUrl, ['instructions' => 'Changed here.'])->assertUnprocessable();
    $this->patchJson($skillUrl, ['icon' => 'Zap', 'color' => '#10A37F', 'is_shared' => false])->assertOk();
    $this->deleteJson($skillUrl)->assertUnprocessable();
    $this->postJson("{$skillUrl}/references", ['title' => 'X', 'content' => 'Y'])->assertUnprocessable();
    $this->postJson("{$skillUrl}/scripts", ['name' => 'x', 'language' => 'bash', 'code' => 'echo'])->assertUnprocessable();

    expect($skill->refresh())->instructions->toBe('Synced.')->icon->toBe('Zap')->is_shared->toBeFalse();

    $agent = Agent::factory()->create(['workspace_id' => $this->workspace->id, 'created_by' => $this->owner->id]);
    $agent->skills()->attach($skill->id);
    $result = (string) (new UpdateSkillTool($agent, $this->owner->id))->handle(new Request(['skill' => $skill->name, 'instructions' => 'Agent edit.']));

    expect($result)->toContain('synced from a GitHub repository')
        ->and($skill->refresh()->instructions)->toBe('Synced.');
});

it('disconnects keeping the skills as editable ones, or removing them', function () {
    fakeSkillRepo(['sha1' => ['s/SKILL.md' => 'Synced.']]);
    Passport::actingAs($this->owner);

    $kept = SkillSource::factory()->create(['workspace_id' => $this->workspace->id, 'created_by' => $this->owner->id]);
    app(SkillSync::class)->sync($kept);
    $keptSkill = $kept->skills()->sole();

    $this->deleteJson("{$this->url}/{$kept->id}?keep_skills=1")->assertNoContent();
    expect($keptSkill->refresh())->isSynced()->toBeFalse()->source_path->toBeNull()
        ->and(SkillSource::query()->count())->toBe(0);
    $this->patchJson("/api/v1/workspaces/{$this->workspace->id}/skills/{$keptSkill->id}", ['instructions' => 'Now mine.'])->assertOk();

    $removed = SkillSource::factory()->create(['workspace_id' => $this->workspace->id, 'created_by' => $this->owner->id, 'path' => 's']);
    app(SkillSync::class)->sync($removed);
    $removedSkill = $removed->skills()->sole();

    $this->deleteJson("{$this->url}/{$removed->id}")->assertNoContent();
    expect(Skill::query()->find($removedSkill->id))->toBeNull()
        ->and(Skill::query()->find($keptSkill->id))->not->toBeNull();
});

it('validates the repository and rejects connecting the same folder twice', function () {
    fakeSkillRepo(['sha1' => ['SKILL.md' => 'Root.']]);
    Passport::actingAs($this->owner);

    $this->postJson($this->url, ['repo' => 'not a repo'])->assertUnprocessable()->assertJsonValidationErrors('repo');
    $this->postJson($this->url, ['repo' => 'acme/skills', 'path' => '../secrets'])->assertUnprocessable()->assertJsonValidationErrors('path');

    $this->postJson($this->url, ['repo' => 'acme/skills'])->assertCreated();
    $this->postJson($this->url, ['repo' => 'Acme/Skills'])->assertUnprocessable()->assertJsonValidationErrors('repo');
});

it('lets only skill managers connect, sync and disconnect repositories', function () {
    $viewer = User::factory()->create();
    $this->workspace->members()->create(['user_id' => $viewer->id, 'role' => Role::Viewer, 'joined_at' => now()]);
    $source = SkillSource::factory()->create(['workspace_id' => $this->workspace->id]);
    Http::preventStrayRequests();
    Passport::actingAs($viewer);

    $this->getJson($this->url)->assertOk()->assertJsonCount(1, 'data.sources');
    $this->postJson($this->url, ['repo' => 'acme/skills'])->assertForbidden();
    $this->postJson("{$this->url}/{$source->id}/sync")->assertForbidden();
    $this->deleteJson("{$this->url}/{$source->id}")->assertForbidden();
});

it('previews the skills a repository holds without saving anything', function () {
    fakeSkillRepo(['sha1' => [
        'skills/mrr/SKILL.md' => SKILL_MD,
        'skills/triage/SKILL.md' => 'Triage tickets.',
        'other/SKILL.md' => 'Outside the folder.',
    ]]);
    Passport::actingAs($this->owner);

    $this->postJson("{$this->url}/preview", ['repo' => 'https://github.com/acme/skills/tree/main/skills'])
        ->assertOk()
        ->assertJsonPath('data.preview.repo', 'acme/skills')
        ->assertJsonPath('data.preview.branch', 'main')
        ->assertJsonPath('data.preview.path', 'skills')
        ->assertJsonPath('data.preview.commit_sha', 'sha1')
        ->assertJsonPath('data.preview.skills', [
            ['path' => 'skills/mrr', 'name' => 'weekly-mrr-report', 'description' => 'Builds the weekly MRR report. Load it for revenue updates.'],
            ['path' => 'skills/triage', 'name' => 'triage', 'description' => null],
        ]);

    expect(SkillSource::query()->count())->toBe(0)->and(Skill::query()->count())->toBe(0);
});

it('explains a repository it cannot read', function () {
    Http::fake(['https://api.github.com/*' => Http::response(['message' => 'Not Found'], 404)]);
    Passport::actingAs($this->owner);

    $this->postJson("{$this->url}/preview", ['repo' => 'acme/private'])
        ->assertUnprocessable()
        ->assertJsonPath('message', "Couldn't find acme/private or its branch. A private repository needs a connected GitHub account.");
});

it('reads the repository, branch and folder from a pasted GitHub link', function (string $input, string $repo, ?string $branch, ?string $path) {
    fakeSkillRepo(['sha1' => ['SKILL.md' => 'Root.']]);
    Passport::actingAs($this->owner);

    $this->postJson($this->url, ['repo' => $input])->assertCreated();

    expect(SkillSource::query()->sole()->only(['repo', 'branch', 'path']))->toBe(['repo' => $repo, 'branch' => $branch, 'path' => $path]);
})->with([
    'owner/name' => ['acme/skills', 'acme/skills', null, null],
    'repository link' => ['https://github.com/acme/skills', 'acme/skills', null, null],
    'clone link' => ['https://github.com/acme/skills.git', 'acme/skills', null, null],
    'ssh clone link' => ['git@github.com:acme/skills.git', 'acme/skills', null, null],
    'folder link' => ['github.com/acme/skills/tree/main/skills/sales', 'acme/skills', 'main', 'skills/sales'],
    'SKILL.md link' => ['https://github.com/acme/skills/blob/main/skills/sales/SKILL.md', 'acme/skills', 'main', 'skills/sales'],
]);
