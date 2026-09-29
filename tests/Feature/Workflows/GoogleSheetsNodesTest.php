<?php

use App\Models\Runs\Run;
use App\Nodes\Integrations\GoogleSheets\GoogleSheetsAppendValuesNode;
use App\Nodes\Integrations\GoogleSheets\GoogleSheetsGetValuesNode;
use App\Nodes\Integrations\GoogleSheets\GoogleSheetsUpdateValuesNode;
use App\Services\Workflows\NodeRegistry;
use Illuminate\Support\Facades\Http;

it('registers every google sheets node under its own type string', function () {
    $registry = app(NodeRegistry::class);

    $types = [
        'google_sheets_get_values' => GoogleSheetsGetValuesNode::class,
        'google_sheets_append_values' => GoogleSheetsAppendValuesNode::class,
        'google_sheets_update_values' => GoogleSheetsUpdateValuesNode::class,
    ];

    foreach ($types as $type => $class) {
        expect($registry->has($type))->toBeTrue();
        $node = $registry->resolve($type);
        expect($node)->toBeInstanceOf($class);
        expect($node->category())->toBe('google_sheets');
    }
});

it('gets values for a range', function () {
    Http::fake(['sheets.googleapis.com/*' => Http::response(['values' => [['a', 'b']]])]);

    $node = new GoogleSheetsGetValuesNode;
    $run = Run::factory()->create();

    $output = $node->execute($run, [
        'access_token' => 'ya29-test',
        'spreadsheet_id' => 'sheet1',
        'range' => 'Sheet1!A1:B1',
    ], []);

    expect($output)->toBe(['values' => [['a', 'b']]]);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/v4/spreadsheets/sheet1/values/Sheet1!A1:B1'));
});

it('appends a row of values', function () {
    Http::fake(['sheets.googleapis.com/*' => Http::response(['updates' => ['updatedRows' => 1]])]);

    $node = new GoogleSheetsAppendValuesNode;
    $run = Run::factory()->create();

    $node->execute($run, [
        'access_token' => 'ya29-test',
        'spreadsheet_id' => 'sheet1',
        'range' => 'Sheet1!A:B',
        'values' => ['a', 'b'],
    ], []);

    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && str_contains($request->url(), '/values/Sheet1!A:B:append')
            && str_contains($request->url(), 'valueInputOption=USER_ENTERED')
            && $request['values'] === [['a', 'b']];
    });
});

it('overwrites values for a range', function () {
    Http::fake(['sheets.googleapis.com/*' => Http::response(['updatedCells' => 2])]);

    $node = new GoogleSheetsUpdateValuesNode;
    $run = Run::factory()->create();

    $node->execute($run, [
        'access_token' => 'ya29-test',
        'spreadsheet_id' => 'sheet1',
        'range' => 'Sheet1!A1:B1',
        'values' => ['x', 'y'],
    ], []);

    Http::assertSent(function ($request) {
        return $request->method() === 'PUT'
            && str_contains($request->url(), '/values/Sheet1!A1:B1')
            && $request['values'] === [['x', 'y']];
    });
});

it('throws when sheets answers a non-2xx status', function () {
    Http::fake(['sheets.googleapis.com/*' => Http::response(['error' => ['message' => 'Requested entity was not found.']], 404)]);

    $node = new GoogleSheetsGetValuesNode;
    $run = Run::factory()->create();

    expect(fn () => $node->execute($run, [
        'access_token' => 'ya29-test', 'spreadsheet_id' => 'missing', 'range' => 'A1:B1',
    ], []))->toThrow(RuntimeException::class, 'Requested entity was not found.');
});

it('throws when access_token is missing', function () {
    $node = new GoogleSheetsGetValuesNode;
    $run = Run::factory()->create();

    expect(fn () => $node->execute($run, ['spreadsheet_id' => 's1', 'range' => 'A1'], []))
        ->toThrow(RuntimeException::class, 'access_token');
});
