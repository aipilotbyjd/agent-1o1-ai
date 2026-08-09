<?php

use App\Models\Runs\Run;
use App\Nodes\Integrations\GitHub\GitHubCreateCommentNode;
use App\Nodes\Integrations\GitHub\GitHubCreateIssueNode;
use App\Nodes\Integrations\GitHub\GitHubCreatePullRequestNode;
use App\Nodes\Integrations\GitHub\GitHubGetRepoNode;
use App\Nodes\Integrations\GitHub\GitHubListCommitsNode;
use App\Nodes\Integrations\GitHub\GitHubListIssuesNode;
use App\Nodes\Integrations\GitHub\GitHubListPullRequestsNode;
use App\Nodes\Integrations\GitHub\GitHubListReposNode;
use App\Services\Workflows\NodeRegistry;
use Illuminate\Support\Facades\Http;

it('registers every github node under its own type string', function () {
    $registry = app(NodeRegistry::class);

    $types = [
        'github_get_repo' => GitHubGetRepoNode::class,
        'github_list_repos' => GitHubListReposNode::class,
        'github_list_issues' => GitHubListIssuesNode::class,
        'github_create_issue' => GitHubCreateIssueNode::class,
        'github_create_comment' => GitHubCreateCommentNode::class,
        'github_list_pull_requests' => GitHubListPullRequestsNode::class,
        'github_create_pull_request' => GitHubCreatePullRequestNode::class,
        'github_list_commits' => GitHubListCommitsNode::class,
    ];

    foreach ($types as $type => $class) {
        expect($registry->has($type))->toBeTrue();
        $node = $registry->resolve($type);
        expect($node)->toBeInstanceOf($class);
        expect($node->category())->toBe('github');
    }
});

it('gets a repository with the bearer token and github accept header', function () {
    Http::fake(['api.github.com/repos/acme/widgets' => Http::response(['id' => 1, 'full_name' => 'acme/widgets'])]);

    $node = new GitHubGetRepoNode;
    $run = Run::factory()->create();

    $output = $node->execute($run, ['access_token' => 'gh-test', 'repo' => 'acme/widgets'], []);

    expect($output)->toBe(['id' => 1, 'full_name' => 'acme/widgets']);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.github.com/repos/acme/widgets'
            && $request->hasHeader('Authorization', 'Bearer gh-test')
            && $request->hasHeader('Accept', 'application/vnd.github+json');
    });
});

it('throws when github answers a non-2xx status', function () {
    Http::fake(['api.github.com/*' => Http::response(['message' => 'Not Found'], 404)]);

    $node = new GitHubGetRepoNode;
    $run = Run::factory()->create();

    expect(fn () => $node->execute($run, ['access_token' => 'gh-test', 'repo' => 'acme/missing'], []))
        ->toThrow(RuntimeException::class, 'Not Found');
});

it('throws when access_token is missing', function () {
    $node = new GitHubGetRepoNode;
    $run = Run::factory()->create();

    expect(fn () => $node->execute($run, ['repo' => 'acme/widgets'], []))
        ->toThrow(RuntimeException::class, 'access_token');
});

it('lists repos for the authenticated user when no owner is given', function () {
    Http::fake(['api.github.com/user/repos*' => Http::response([['id' => 1]])]);

    $node = new GitHubListReposNode;
    $run = Run::factory()->create();

    $output = $node->execute($run, ['access_token' => 'gh-test'], []);

    expect($output['repos'])->toBe([['id' => 1]]);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/user/repos'));
});

it('lists repos for an organization when owner is given', function () {
    Http::fake(['api.github.com/orgs/acme/repos*' => Http::response([['id' => 2]])]);

    $node = new GitHubListReposNode;
    $run = Run::factory()->create();

    $node->execute($run, ['access_token' => 'gh-test', 'owner' => 'acme'], []);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/orgs/acme/repos'));
});

it('lists issues with a default open state', function () {
    Http::fake(['api.github.com/repos/acme/widgets/issues*' => Http::response([['number' => 1]])]);

    $node = new GitHubListIssuesNode;
    $run = Run::factory()->create();

    $output = $node->execute($run, ['access_token' => 'gh-test', 'repo' => 'acme/widgets'], []);

    expect($output['issues'])->toBe([['number' => 1]]);
    Http::assertSent(fn ($request) => $request['state'] === 'open');
});

it('creates an issue and maps the response', function () {
    Http::fake(['api.github.com/repos/acme/widgets/issues' => Http::response([
        'id' => 10, 'number' => 5, 'title' => 'Bug', 'html_url' => 'https://github.com/acme/widgets/issues/5',
    ])]);

    $node = new GitHubCreateIssueNode;
    $run = Run::factory()->create();

    $output = $node->execute($run, ['access_token' => 'gh-test', 'repo' => 'acme/widgets', 'title' => 'Bug'], []);

    expect($output)->toBe([
        'id' => 10, 'number' => 5, 'title' => 'Bug', 'url' => 'https://github.com/acme/widgets/issues/5',
    ]);
});

it('creates a comment on an issue', function () {
    Http::fake(['api.github.com/repos/acme/widgets/issues/5/comments' => Http::response([
        'id' => 99, 'html_url' => 'https://github.com/acme/widgets/issues/5#comment-99',
    ])]);

    $node = new GitHubCreateCommentNode;
    $run = Run::factory()->create();

    $output = $node->execute($run, [
        'access_token' => 'gh-test', 'repo' => 'acme/widgets', 'issue_number' => 5, 'body' => 'Looking into it.',
    ], []);

    expect($output)->toBe(['id' => 99, 'url' => 'https://github.com/acme/widgets/issues/5#comment-99']);
    Http::assertSent(fn ($request) => $request['body'] === 'Looking into it.');
});

it('lists pull requests with a default open state', function () {
    Http::fake(['api.github.com/repos/acme/widgets/pulls*' => Http::response([['number' => 3]])]);

    $node = new GitHubListPullRequestsNode;
    $run = Run::factory()->create();

    $output = $node->execute($run, ['access_token' => 'gh-test', 'repo' => 'acme/widgets'], []);

    expect($output['pull_requests'])->toBe([['number' => 3]]);
});

it('creates a pull request and maps the response', function () {
    Http::fake(['api.github.com/repos/acme/widgets/pulls' => Http::response([
        'id' => 11, 'number' => 7, 'title' => 'Add feature', 'html_url' => 'https://github.com/acme/widgets/pull/7',
    ])]);

    $node = new GitHubCreatePullRequestNode;
    $run = Run::factory()->create();

    $output = $node->execute($run, [
        'access_token' => 'gh-test', 'repo' => 'acme/widgets', 'title' => 'Add feature', 'head' => 'feature', 'base' => 'main',
    ], []);

    expect($output)->toBe([
        'id' => 11, 'number' => 7, 'title' => 'Add feature', 'url' => 'https://github.com/acme/widgets/pull/7',
    ]);
});

it('lists commits and omits unset optional filters', function () {
    Http::fake(['api.github.com/repos/acme/widgets/commits*' => Http::response([['sha' => 'abc123']])]);

    $node = new GitHubListCommitsNode;
    $run = Run::factory()->create();

    $output = $node->execute($run, ['access_token' => 'gh-test', 'repo' => 'acme/widgets'], []);

    expect($output['commits'])->toBe([['sha' => 'abc123']]);
    Http::assertSent(function ($request) {
        return ! isset($request['sha']) && ! isset($request['path']) && $request['per_page'] == 30;
    });
});
