<?php

use App\Models\Runs\Run;
use App\Nodes\Integrations\GoogleDrive\GoogleDriveDeleteFileNode;
use App\Nodes\Integrations\GoogleDrive\GoogleDriveGetFileNode;
use App\Nodes\Integrations\GoogleDrive\GoogleDriveListFilesNode;
use App\Services\Workflows\NodeRegistry;
use Illuminate\Support\Facades\Http;

it('registers every google drive node under its own type string', function () {
    $registry = app(NodeRegistry::class);

    $types = [
        'google_drive_list_files' => GoogleDriveListFilesNode::class,
        'google_drive_get_file' => GoogleDriveGetFileNode::class,
        'google_drive_delete_file' => GoogleDriveDeleteFileNode::class,
    ];

    foreach ($types as $type => $class) {
        expect($registry->has($type))->toBeTrue();
        $node = $registry->resolve($type);
        expect($node)->toBeInstanceOf($class);
        expect($node->category())->toBe('google_drive');
    }
});

it('lists files with the query and page_size as query params', function () {
    Http::fake(['www.googleapis.com/drive/*' => Http::response(['files' => []])]);

    $node = new GoogleDriveListFilesNode;
    $run = Run::factory()->create();

    $node->execute($run, ['access_token' => 'ya29-test', 'query' => "name contains 'report'", 'page_size' => 5], []);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/drive/v3/files')
            && $request['q'] === "name contains 'report'"
            && $request['pageSize'] == 5;
    });
});

it('gets a file by id', function () {
    Http::fake(['www.googleapis.com/drive/*' => Http::response(['id' => 'f1', 'name' => 'report.pdf'])]);

    $node = new GoogleDriveGetFileNode;
    $run = Run::factory()->create();

    $output = $node->execute($run, ['access_token' => 'ya29-test', 'file_id' => 'f1'], []);

    expect($output)->toBe(['id' => 'f1', 'name' => 'report.pdf']);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/drive/v3/files/f1'));
});

it('deletes a file by id', function () {
    Http::fake(['www.googleapis.com/drive/*' => Http::response('', 204)]);

    $node = new GoogleDriveDeleteFileNode;
    $run = Run::factory()->create();

    $output = $node->execute($run, ['access_token' => 'ya29-test', 'file_id' => 'f1'], []);

    expect($output)->toBe([]);
    Http::assertSent(function ($request) {
        return $request->method() === 'DELETE' && str_contains($request->url(), '/drive/v3/files/f1');
    });
});

it('throws when drive answers a non-2xx status', function () {
    Http::fake(['www.googleapis.com/drive/*' => Http::response(['error' => ['message' => 'File not found']], 404)]);

    $node = new GoogleDriveGetFileNode;
    $run = Run::factory()->create();

    expect(fn () => $node->execute($run, ['access_token' => 'ya29-test', 'file_id' => 'missing'], []))
        ->toThrow(RuntimeException::class, 'File not found');
});

it('throws when access_token is missing', function () {
    $node = new GoogleDriveListFilesNode;
    $run = Run::factory()->create();

    expect(fn () => $node->execute($run, [], []))->toThrow(RuntimeException::class, 'access_token');
});
