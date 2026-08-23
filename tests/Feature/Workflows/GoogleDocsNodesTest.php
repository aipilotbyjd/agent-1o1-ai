<?php

use App\Models\Runs\Run;
use App\Nodes\Integrations\GoogleDocs\GoogleDocsAppendTextNode;
use App\Nodes\Integrations\GoogleDocs\GoogleDocsCreateDocumentNode;
use App\Nodes\Integrations\GoogleDocs\GoogleDocsGetDocumentNode;
use App\Services\Workflows\NodeRegistry;
use Illuminate\Support\Facades\Http;

it('registers every google docs node under its own type string', function () {
    $registry = app(NodeRegistry::class);

    $types = [
        'google_docs_create_document' => GoogleDocsCreateDocumentNode::class,
        'google_docs_get_document' => GoogleDocsGetDocumentNode::class,
        'google_docs_append_text' => GoogleDocsAppendTextNode::class,
    ];

    foreach ($types as $type => $class) {
        expect($registry->has($type))->toBeTrue();
        $node = $registry->resolve($type);
        expect($node)->toBeInstanceOf($class);
        expect($node->category())->toBe('google_docs');
    }
});

it('creates a document with a title', function () {
    Http::fake(['docs.googleapis.com/*' => Http::response(['documentId' => 'd1', 'title' => 'My Doc'])]);

    $node = new GoogleDocsCreateDocumentNode;
    $run = Run::factory()->create();

    $output = $node->execute($run, ['access_token' => 'ya29-test', 'title' => 'My Doc'], []);

    expect($output)->toBe(['documentId' => 'd1', 'title' => 'My Doc']);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/v1/documents') && $request['title'] === 'My Doc');
});

it('gets a document by id', function () {
    Http::fake(['docs.googleapis.com/*' => Http::response(['documentId' => 'd1'])]);

    $node = new GoogleDocsGetDocumentNode;
    $run = Run::factory()->create();

    $output = $node->execute($run, ['access_token' => 'ya29-test', 'document_id' => 'd1'], []);

    expect($output)->toBe(['documentId' => 'd1']);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/v1/documents/d1'));
});

it('appends text via a batchUpdate insertText request', function () {
    Http::fake(['docs.googleapis.com/*' => Http::response(['documentId' => 'd1'])]);

    $node = new GoogleDocsAppendTextNode;
    $run = Run::factory()->create();

    $node->execute($run, ['access_token' => 'ya29-test', 'document_id' => 'd1', 'text' => 'Hello'], []);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/v1/documents/d1:batchUpdate')
            && $request['requests'][0]['insertText']['text'] === 'Hello';
    });
});

it('throws when docs answers a non-2xx status', function () {
    Http::fake(['docs.googleapis.com/*' => Http::response(['error' => ['message' => 'Requested entity was not found.']], 404)]);

    $node = new GoogleDocsGetDocumentNode;
    $run = Run::factory()->create();

    expect(fn () => $node->execute($run, ['access_token' => 'ya29-test', 'document_id' => 'missing'], []))
        ->toThrow(RuntimeException::class, 'Requested entity was not found.');
});

it('throws when access_token is missing', function () {
    $node = new GoogleDocsGetDocumentNode;
    $run = Run::factory()->create();

    expect(fn () => $node->execute($run, ['document_id' => 'd1'], []))->toThrow(RuntimeException::class, 'access_token');
});
