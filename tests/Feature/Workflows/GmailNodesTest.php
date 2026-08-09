<?php

use App\Models\Runs\Run;
use App\Nodes\Integrations\Gmail\GmailAddLabelNode;
use App\Nodes\Integrations\Gmail\GmailCreateDraftNode;
use App\Nodes\Integrations\Gmail\GmailDeleteMessageNode;
use App\Nodes\Integrations\Gmail\GmailGetMessageNode;
use App\Nodes\Integrations\Gmail\GmailListLabelsNode;
use App\Nodes\Integrations\Gmail\GmailListMessagesNode;
use App\Nodes\Integrations\Gmail\GmailModifyMessageNode;
use App\Nodes\Integrations\Gmail\GmailReplyToMessageNode;
use App\Nodes\Integrations\Gmail\GmailSendEmailNode;
use App\Services\Workflows\NodeRegistry;
use Illuminate\Support\Facades\Http;

it('registers every gmail node under its own type string', function () {
    $registry = app(NodeRegistry::class);

    $types = [
        'gmail_send_email' => GmailSendEmailNode::class,
        'gmail_list_messages' => GmailListMessagesNode::class,
        'gmail_get_message' => GmailGetMessageNode::class,
        'gmail_reply_to_message' => GmailReplyToMessageNode::class,
        'gmail_create_draft' => GmailCreateDraftNode::class,
        'gmail_delete_message' => GmailDeleteMessageNode::class,
        'gmail_modify_message' => GmailModifyMessageNode::class,
        'gmail_add_label' => GmailAddLabelNode::class,
        'gmail_list_labels' => GmailListLabelsNode::class,
    ];

    foreach ($types as $type => $class) {
        expect($registry->has($type))->toBeTrue();
        $node = $registry->resolve($type);
        expect($node)->toBeInstanceOf($class);
        expect($node->category())->toBe('gmail');
    }
});

it('sends an email with a base64url-encoded raw message', function () {
    Http::fake(['gmail.googleapis.com/*' => Http::response(['id' => 'm1', 'threadId' => 't1'])]);

    $node = new GmailSendEmailNode;
    $run = Run::factory()->create();

    $output = $node->execute($run, [
        'access_token' => 'ya29-test',
        'to' => 'a@example.com',
        'subject' => 'Hi',
        'body' => 'Hello there',
    ], []);

    expect($output)->toBe(['id' => 'm1', 'threadId' => 't1']);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send'
            && $request->hasHeader('Authorization', 'Bearer ya29-test')
            && ! str_contains($request['raw'], '+')
            && ! str_contains($request['raw'], '/');
    });
});

it('throws when gmail answers a non-2xx status', function () {
    Http::fake(['gmail.googleapis.com/*' => Http::response(['error' => ['message' => 'Invalid Credentials']], 401)]);

    $node = new GmailSendEmailNode;
    $run = Run::factory()->create();

    expect(fn () => $node->execute($run, [
        'access_token' => 'ya29-test',
        'to' => 'a@example.com',
        'subject' => 'Hi',
        'body' => 'Hello there',
    ], []))->toThrow(RuntimeException::class, 'Invalid Credentials');
});

it('throws when access_token is missing', function () {
    $node = new GmailListLabelsNode;
    $run = Run::factory()->create();

    expect(fn () => $node->execute($run, [], []))->toThrow(RuntimeException::class, 'access_token');
});

it('lists messages with the query and max_results as query params', function () {
    Http::fake(['gmail.googleapis.com/*' => Http::response(['messages' => []])]);

    $node = new GmailListMessagesNode;
    $run = Run::factory()->create();

    $node->execute($run, ['access_token' => 'ya29-test', 'query' => 'is:unread', 'max_results' => 5], []);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/users/me/messages')
            && $request['q'] === 'is:unread'
            && $request['maxResults'] == 5;
    });
});

it('gets a message by id', function () {
    Http::fake(['gmail.googleapis.com/*' => Http::response(['id' => 'm1', 'snippet' => 'hi'])]);

    $node = new GmailGetMessageNode;
    $run = Run::factory()->create();

    $output = $node->execute($run, ['access_token' => 'ya29-test', 'message_id' => 'm1'], []);

    expect($output)->toBe(['id' => 'm1', 'snippet' => 'hi']);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/users/me/messages/m1'));
});

it('replies to a message within the same thread', function () {
    Http::fake(['gmail.googleapis.com/*' => Http::response(['id' => 'm2', 'threadId' => 't1'])]);

    $node = new GmailReplyToMessageNode;
    $run = Run::factory()->create();

    $node->execute($run, [
        'access_token' => 'ya29-test',
        'to' => 'a@example.com',
        'message_id' => 'm1',
        'thread_id' => 't1',
        'body' => 'reply body',
    ], []);

    Http::assertSent(fn ($request) => $request['threadId'] === 't1');
});

it('creates a draft', function () {
    Http::fake(['gmail.googleapis.com/*' => Http::response(['id' => 'd1'])]);

    $node = new GmailCreateDraftNode;
    $run = Run::factory()->create();

    $output = $node->execute($run, [
        'access_token' => 'ya29-test',
        'to' => 'a@example.com',
        'subject' => 'Draft',
        'body' => 'draft body',
    ], []);

    expect($output)->toBe(['id' => 'd1']);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/users/me/drafts') && isset($request['message']['raw']));
});

it('deletes (trashes) a message', function () {
    Http::fake(['gmail.googleapis.com/*' => Http::response(['id' => 'm1'])]);

    $node = new GmailDeleteMessageNode;
    $run = Run::factory()->create();

    $output = $node->execute($run, ['access_token' => 'ya29-test', 'message_id' => 'm1'], []);

    expect($output)->toBe(['trashed' => true, 'message_id' => 'm1']);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/users/me/messages/m1/trash'));
});

it('modifies labels on a message', function () {
    Http::fake(['gmail.googleapis.com/*' => Http::response(['id' => 'm1'])]);

    $node = new GmailModifyMessageNode;
    $run = Run::factory()->create();

    $node->execute($run, [
        'access_token' => 'ya29-test',
        'message_id' => 'm1',
        'add_label_ids' => ['STARRED'],
        'remove_label_ids' => ['UNREAD'],
    ], []);

    Http::assertSent(fn ($request) => $request['addLabelIds'] === ['STARRED'] && $request['removeLabelIds'] === ['UNREAD']);
});

it('adds a label to a message', function () {
    Http::fake(['gmail.googleapis.com/*' => Http::response(['id' => 'm1'])]);

    $node = new GmailAddLabelNode;
    $run = Run::factory()->create();

    $node->execute($run, ['access_token' => 'ya29-test', 'message_id' => 'm1', 'label_ids' => ['IMPORTANT']], []);

    Http::assertSent(fn ($request) => $request['addLabelIds'] === ['IMPORTANT'] && $request['removeLabelIds'] === []);
});

it('lists labels', function () {
    Http::fake(['gmail.googleapis.com/*' => Http::response(['labels' => [['id' => 'INBOX']]])]);

    $node = new GmailListLabelsNode;
    $run = Run::factory()->create();

    $output = $node->execute($run, ['access_token' => 'ya29-test'], []);

    expect($output['labels'])->toBe([['id' => 'INBOX']]);
});
