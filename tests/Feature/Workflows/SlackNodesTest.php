<?php

use App\Models\Runs\Run;
use App\Nodes\Integrations\Slack\SlackCreateChannelNode;
use App\Nodes\Integrations\Slack\SlackGetChannelHistoryNode;
use App\Nodes\Integrations\Slack\SlackInviteToChannelNode;
use App\Nodes\Integrations\Slack\SlackListChannelsNode;
use App\Nodes\Integrations\Slack\SlackListUsersNode;
use App\Nodes\Integrations\Slack\SlackPostMessageNode;
use App\Nodes\Integrations\Slack\SlackUploadFileNode;
use App\Services\Workflows\NodeRegistry;
use Illuminate\Support\Facades\Http;

it('registers every slack node under its own type string', function () {
    $registry = app(NodeRegistry::class);

    $types = [
        'slack_post_message' => SlackPostMessageNode::class,
        'slack_list_channels' => SlackListChannelsNode::class,
        'slack_create_channel' => SlackCreateChannelNode::class,
        'slack_invite_to_channel' => SlackInviteToChannelNode::class,
        'slack_get_channel_history' => SlackGetChannelHistoryNode::class,
        'slack_upload_file' => SlackUploadFileNode::class,
        'slack_list_users' => SlackListUsersNode::class,
    ];

    foreach ($types as $type => $class) {
        expect($registry->has($type))->toBeTrue();
        $node = $registry->resolve($type);
        expect($node)->toBeInstanceOf($class);
        expect($node->category())->toBe('slack');
    }
});

it('posts a message with the bearer token and required fields', function () {
    Http::fake(['slack.com/api/chat.postMessage' => Http::response(['ok' => true, 'ts' => '123.456', 'channel' => 'C1'])]);

    $node = new SlackPostMessageNode;
    $run = Run::factory()->create();

    $output = $node->execute($run, [
        'access_token' => 'xoxb-test',
        'channel' => 'C1',
        'text' => 'hello',
    ], []);

    expect($output)->toBe(['ok' => true, 'ts' => '123.456', 'channel' => 'C1']);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://slack.com/api/chat.postMessage'
            && $request->hasHeader('Authorization', 'Bearer xoxb-test')
            && $request['channel'] === 'C1'
            && $request['text'] === 'hello';
    });
});

it('throws when slack answers ok:false, even with an HTTP 200', function () {
    Http::fake(['slack.com/api/chat.postMessage' => Http::response(['ok' => false, 'error' => 'channel_not_found'])]);

    $node = new SlackPostMessageNode;
    $run = Run::factory()->create();

    expect(fn () => $node->execute($run, ['access_token' => 'xoxb-test', 'channel' => 'C1', 'text' => 'hi'], []))
        ->toThrow(RuntimeException::class, 'channel_not_found');
});

it('throws when access_token is missing', function () {
    $node = new SlackPostMessageNode;
    $run = Run::factory()->create();

    expect(fn () => $node->execute($run, ['channel' => 'C1', 'text' => 'hi'], []))
        ->toThrow(RuntimeException::class, 'access_token');
});

it('lists channels with optional filters as query params', function () {
    Http::fake(['slack.com/api/conversations.list*' => Http::response(['ok' => true, 'channels' => []])]);

    $node = new SlackListChannelsNode;
    $run = Run::factory()->create();

    $node->execute($run, ['access_token' => 'xoxb-test', 'types' => 'public_channel', 'limit' => 50], []);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'conversations.list')
            && $request['types'] === 'public_channel'
            && $request['limit'] == 50;
    });
});

it('creates a channel', function () {
    Http::fake(['slack.com/api/conversations.create' => Http::response(['ok' => true, 'channel' => ['id' => 'C2']])]);

    $node = new SlackCreateChannelNode;
    $run = Run::factory()->create();

    $output = $node->execute($run, ['access_token' => 'xoxb-test', 'name' => 'new-channel'], []);

    expect($output['channel']['id'])->toBe('C2');
});

it('invites users to a channel', function () {
    Http::fake(['slack.com/api/conversations.invite' => Http::response(['ok' => true, 'channel' => ['id' => 'C1']])]);

    $node = new SlackInviteToChannelNode;
    $run = Run::factory()->create();

    $node->execute($run, ['access_token' => 'xoxb-test', 'channel' => 'C1', 'users' => 'U1,U2'], []);

    Http::assertSent(fn ($request) => $request['users'] === 'U1,U2');
});

it('gets channel history', function () {
    Http::fake(['slack.com/api/conversations.history*' => Http::response(['ok' => true, 'messages' => [['text' => 'hi']]])]);

    $node = new SlackGetChannelHistoryNode;
    $run = Run::factory()->create();

    $output = $node->execute($run, ['access_token' => 'xoxb-test', 'channel' => 'C1', 'limit' => 10], []);

    expect($output['messages'])->toBe([['text' => 'hi']]);
});

it('uploads a file', function () {
    Http::fake(['slack.com/api/files.upload' => Http::response(['ok' => true, 'file' => ['id' => 'F1']])]);

    $node = new SlackUploadFileNode;
    $run = Run::factory()->create();

    $output = $node->execute($run, [
        'access_token' => 'xoxb-test',
        'channels' => 'C1',
        'content' => 'file body text',
        'filename' => 'notes.txt',
    ], []);

    expect($output['file']['id'])->toBe('F1');
});

it('lists users', function () {
    Http::fake(['slack.com/api/users.list*' => Http::response(['ok' => true, 'members' => [['id' => 'U1']]])]);

    $node = new SlackListUsersNode;
    $run = Run::factory()->create();

    $output = $node->execute($run, ['access_token' => 'xoxb-test'], []);

    expect($output['members'])->toBe([['id' => 'U1']]);
});
