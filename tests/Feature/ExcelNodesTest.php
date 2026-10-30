<?php

use App\Jobs\Triggers\FireTriggerEvent;
use App\Jobs\Triggers\PollTrigger;
use App\Jobs\Triggers\SyncTriggerSubscription;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Nodes\NodeCategory;
use App\Models\Runs\Run;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Nodes\Integrations\Excel\AbstractExcelNode;
use App\Nodes\Integrations\Excel\ExcelAddChartNode;
use App\Nodes\Integrations\Excel\ExcelAddRowNode;
use App\Nodes\Integrations\Excel\ExcelAddRowsNode;
use App\Nodes\Integrations\Excel\ExcelAddTableNode;
use App\Nodes\Integrations\Excel\ExcelAddTableRowNode;
use App\Nodes\Integrations\Excel\ExcelCallFunctionNode;
use App\Nodes\Integrations\Excel\ExcelClearColumnNode;
use App\Nodes\Integrations\Excel\ExcelCopyWorkbookNode;
use App\Nodes\Integrations\Excel\ExcelCreateWorkbookNode;
use App\Nodes\Integrations\Excel\ExcelDeleteRowNode;
use App\Nodes\Integrations\Excel\ExcelDownloadWorkbookNode;
use App\Nodes\Integrations\Excel\ExcelFindOrCreateRowNode;
use App\Nodes\Integrations\Excel\ExcelFindOrCreateWorksheetNode;
use App\Nodes\Integrations\Excel\ExcelFindRowNode;
use App\Nodes\Integrations\Excel\ExcelFindTableRowNode;
use App\Nodes\Integrations\Excel\ExcelGetCellNode;
use App\Nodes\Integrations\Excel\ExcelGetChartImageNode;
use App\Nodes\Integrations\Excel\ExcelGetColumnsNode;
use App\Nodes\Integrations\Excel\ExcelGetRangeNode;
use App\Nodes\Integrations\Excel\ExcelListNamesNode;
use App\Nodes\Integrations\Excel\ExcelListRowsNode;
use App\Nodes\Integrations\Excel\ExcelListWorksheetsNode;
use App\Nodes\Integrations\Excel\ExcelRefreshPivotTableNode;
use App\Nodes\Integrations\Excel\ExcelSearchWorkbooksNode;
use App\Nodes\Integrations\Excel\ExcelUpdateRangeNode;
use App\Nodes\Integrations\Excel\ExcelUpdateRowNode;
use App\Nodes\Integrations\Excel\ExcelUpdateTableRowNode;
use App\Services\Connectors\ConnectorCredentialTester;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use App\Services\Workflows\NodeOptions\Sources\ExcelOptions;
use App\Services\Workflows\NodeRegistry;
use App\Services\Workspaces\WorkspaceService;
use Database\Seeders\AppNodeCategorySeeder;
use Database\Seeders\ConnectorSeeder;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

function excelRun(): Run
{
    $run = Run::factory()->create();

    ConnectorCredential::factory()->create([
        'workspace_id' => $run->workspace_id,
        'connector_id' => (Connector::query()->firstWhere('key', 'excel') ?? Connector::factory()->create(['key' => 'excel', 'name' => 'Microsoft Excel']))->id,
        'data' => ['access_token' => 'graph-token'],
        'is_default' => true,
    ]);

    return $run;
}

/**
 * Answer Microsoft Graph. A key is "METHOD /path" under /v1.0, URL-decoded.
 *
 * @param  array<string, mixed>  $routes
 */
function excelFake(array $routes): void
{
    Http::swap(new Factory);
    Http::fake(['graph.microsoft.com/*' => function (Request $request) use ($routes) {
        $path = (string) preg_replace('~^/v1\.0~', '', rawurldecode((string) parse_url($request->url(), PHP_URL_PATH)));
        $key = $request->method().' '.$path;

        if (! array_key_exists($key, $routes)) {
            return Http::response(['error' => ['code' => 'NotFound', 'message' => "Nothing fakes {$key}"]], 404);
        }

        $answer = value($routes[$key], $request);

        return $answer instanceof PromiseInterface ? $answer : Http::response($answer);
    }]);
}

function excelRequest(string $route): Request
{
    $found = array_values(array_filter(Http::recorded()->all(), fn (array $pair): bool => $pair[0]->method().' '.preg_replace('~^/v1\.0~', '', rawurldecode((string) parse_url($pair[0]->url(), PHP_URL_PATH))) === $route));

    expect($found)->not->toBeEmpty("No request for {$route}");

    return end($found)[0];
}

/**
 * @return array<string, mixed>
 */
function excelBody(string $route): array
{
    return (array) json_decode(excelRequest($route)->body(), true);
}

/**
 * @param  array<int, mixed>  $values
 * @return array<string, mixed>
 */
function excelUsed(string $address, array $values): array
{
    return ['address' => "Sheet1!{$address}", 'values' => $values];
}

const EXCEL_SHEET = '/me/drive/items/01A/workbook/worksheets/Sheet1';
const EXCEL_TABLE = '/me/drive/items/01A/workbook/tables/Sales';

describe('excel nodes', function () {
    it('registers every node with the connector and a seeded category', function () {
        $this->seed([ConnectorSeeder::class, AppNodeCategorySeeder::class]);

        $types = array_values(array_filter(array_column(app(NodeRegistry::class)->placeableTypes(), 'type'), fn (string $type): bool => str_starts_with($type, 'excel_')));

        expect(count($types))->toBeGreaterThanOrEqual(80);

        foreach ($types as $type) {
            expect(app(NodeRegistry::class)->resolve($type))->toBeInstanceOf(AbstractExcelNode::class);
        }

        $connector = Connector::query()->where('key', 'excel')->first();

        expect($connector->sort_order)->toBe(58)
            ->and($connector->oauth['scopes'])->toContain('Files.ReadWrite.All', 'offline_access')
            ->and($connector->oauth['authorize_url'])->toContain('login.microsoftonline.com')
            ->and(config('services.excel.client_id'))->toBe(config('services.outlook.client_id'))
            ->and(NodeCategory::query()->where('slug', 'excel')->first()->sort_order)->toBe(58);
    });

    it('runs every node', function () {
        $samples = ['method' => 'GET', 'path' => '/me/drive', 'workbook_id' => '01A', 'worksheet' => 'Sheet1', 'table' => 'Sales', 'address' => 'A1:B2', 'row' => 2, 'column' => 2, 'columns' => ['Name'], 'value' => 'x', 'values' => ['a', 'b'], 'rows' => [['a', 'b']], 'index' => 0, 'name' => 'Thing', 'query' => 'x', 'chart' => 'Chart 1', 'chart_type' => 'Line', 'source' => 'A1:B2', 'reference' => '=A1', 'function' => 'pmt', 'arguments' => ['rate' => 1], 'fields' => [['key' => 0]], 'shift' => 'Up'];

        Http::fake(['graph.microsoft.com/*' => Http::response(['id' => '01B', 'name' => 'Thing', 'address' => 'Sheet1!A1:B2', 'values' => [['Name', 'Age'], ['Ada', 36]], 'value' => [['id' => '1', 'name' => 'Thing', 'index' => 0, 'values' => [['Ada', 36]]]]])]);

        $run = excelRun();
        $ran = 0;

        foreach (app(NodeRegistry::class)->placeableTypes() as $described) {
            if (! str_starts_with($described['type'], 'excel_')) {
                continue;
            }

            $node = app(NodeRegistry::class)->resolve($described['type']);
            $required = $node->configSchema()['required'];
            $config = array_intersect_key($samples, array_flip($required));

            // Nodes where any one of several settings will do.
            foreach (['name' => 'Thing', 'comment' => 'Note'] as $key => $value) {
                if (isset($node->configSchema()['properties'][$key]) && ! isset($config[$key])) {
                    $config[$key] = $value;
                }
            }

            if ($described['type'] === 'excel_update_range') {
                $config['values'] = [['a', 'b'], ['c', 'd']];
            }

            if ($described['type'] === 'excel_clear_column') {
                $config['column'] = 'C';
            }

            if (in_array($described['type'], ['excel_find_row', 'excel_find_table_row', 'excel_find_or_create_row', 'excel_update_table_column', 'excel_delete_table_column', 'excel_filter_table_column'], true)) {
                $config['column'] = 'Name';
            }

            expect(array_diff($required, array_keys($config)))->toBe([], "{$described['type']} has a required setting the sweep doesn't know");
            expect($node->execute($run, $config, []))->toBeArray();
            $ran++;
        }

        expect($ran)->toBeGreaterThanOrEqual(80);
    });

    it('finds workbooks, dropping what isn\'t one', function () {
        excelFake(["GET /me/drive/root/search(q='budget')" => ['value' => [['id' => '1', 'name' => 'Budget.xlsx', 'lastModifiedDateTime' => '2026-01-01T00:00:00Z'], ['id' => '2', 'name' => 'Budget.pdf'], ['id' => '3', 'name' => 'New budget.xlsx', 'lastModifiedDateTime' => '2026-02-01T00:00:00Z', 'parentReference' => ['driveId' => 'b!x']]]]]);

        $output = (new ExcelSearchWorkbooksNode)->execute(excelRun(), ['query' => 'budget'], []);

        expect(array_column($output['workbooks'], 'id'))->toBe(['3', '1'])
            ->and($output['workbooks'][0]['drive_id'])->toBe('b!x');
    });

    it('reaches a SharePoint drive when one is chosen', function () {
        excelFake(['GET /drives/b!x/items/01A/workbook/worksheets' => ['value' => [['id' => '{1}', 'name' => 'Sheet1', 'position' => 0, 'visibility' => 'Visible']]]]);

        expect((new ExcelListWorksheetsNode)->execute(excelRun(), ['drive_id' => 'b!x', 'workbook_id' => '01A'], [])['worksheets'][0])->toMatchArray(['name' => 'Sheet1']);
    });

    it('creates a workbook with real content, so Excel can open it', function () {
        excelFake(['PUT /me/drive/root:/Q4 Budget.xlsx:/content' => ['id' => '01N', 'name' => 'Q4 Budget.xlsx', 'webUrl' => 'https://x/y'], 'PATCH /me/drive/items/01N/workbook/worksheets/Sheet1' => ['id' => '{1}']]);

        $output = (new ExcelCreateWorkbookNode)->execute(excelRun(), ['name' => 'Q4 Budget', 'worksheet_name' => 'Sales'], []);
        $request = excelRequest('PUT /me/drive/root:/Q4 Budget.xlsx:/content');

        expect($output)->toMatchArray(['id' => '01N', 'name' => 'Q4 Budget.xlsx'])
            ->and(substr($request->body(), 0, 2))->toBe('PK')
            ->and($request->url())->toContain('conflictBehavior=rename')
            ->and(excelBody('PATCH /me/drive/items/01N/workbook/worksheets/Sheet1'))->toBe(['name' => 'Sales'])
            ->and(fn () => (new ExcelCreateWorkbookNode)->execute(excelRun(), ['name' => '../x'], []))->toThrow(InvalidArgumentException::class);
    });

    it('copies a workbook and hands back where to watch it', function () {
        excelFake(['POST /me/drive/items/01A/copy' => fn () => Http::response(null, 202, ['Location' => 'https://monitor.test/1'])]);

        expect((new ExcelCopyWorkbookNode)->execute(excelRun(), ['workbook_id' => '01A', 'name' => 'Copy.xlsx', 'folder_id' => 'F1'], []))->toBe(['accepted' => true, 'status' => 202, 'monitor_url' => 'https://monitor.test/1'])
            ->and(excelBody('POST /me/drive/items/01A/copy'))->toBe(['name' => 'Copy.xlsx', 'parentReference' => ['id' => 'F1']]);
    });

    it('hands back a download link', function () {
        excelFake(['GET /me/drive/items/01A' => ['id' => '01A', 'name' => 'a.xlsx', '@microsoft.graph.downloadUrl' => 'https://dl.test/1']]);

        expect((new ExcelDownloadWorkbookNode)->execute(excelRun(), ['workbook_id' => '01A'], [])['download_url'])->toBe('https://dl.test/1');
    });

    it('finds or adds a worksheet', function () {
        excelFake(['GET /me/drive/items/01A/workbook/worksheets' => ['value' => [['id' => '1', 'name' => 'Sales']]], 'POST /me/drive/items/01A/workbook/worksheets/add' => ['id' => '2', 'name' => 'Costs']]);

        expect((new ExcelFindOrCreateWorksheetNode)->execute(excelRun(), ['workbook_id' => '01A', 'name' => 'sales'], [])['created'])->toBeFalse()
            ->and((new ExcelFindOrCreateWorksheetNode)->execute(excelRun(), ['workbook_id' => '01A', 'name' => 'Costs'], [])['created'])->toBeTrue();
    });

    it('reads rows as records keyed by the first row, with their numbers', function () {
        excelFake(['GET '.EXCEL_SHEET.'/usedRange(valuesOnly=true)' => excelUsed('A1:B4', [['Name', 'Age'], ['Ada', 36], ['', ''], ['Bob', 40]])]);

        $output = (new ExcelListRowsNode)->execute(excelRun(), ['workbook_id' => '01A', 'worksheet' => 'Sheet1'], []);

        expect($output['rows'])->toBe([['_row' => 2, 'Name' => 'Ada', 'Age' => 36], ['_row' => 4, 'Name' => 'Bob', 'Age' => 40]])
            ->and($output['total'])->toBe(2);
    });

    it('reads a sheet that doesn\'t start at the first cell', function () {
        excelFake(['GET '.EXCEL_SHEET.'/usedRange(valuesOnly=true)' => excelUsed('$C$5:$D$6', [['Name', 'Age'], ['Ada', 36]])]);

        expect((new ExcelListRowsNode)->execute(excelRun(), ['workbook_id' => '01A', 'worksheet' => 'Sheet1'], [])['rows'][0]['_row'])->toBe(6)
            ->and((new ExcelGetColumnsNode)->execute(excelRun(), ['workbook_id' => '01A', 'worksheet' => 'Sheet1', 'columns' => ['Age', 'C']], [])['columns'])->toBe(['Age' => [36], 'C' => ['Ada']]);
    });

    it('adds a row below the last one, matching an object to the columns', function () {
        excelFake(['GET '.EXCEL_SHEET.'/usedRange(valuesOnly=true)' => excelUsed('A1:C3', [['Name', 'Age', 'City'], ['Ada', 36, 'Pune'], ['Bob', 40, 'Goa']]), 'PATCH '.EXCEL_SHEET."/range(address='A4:C4')" => ['address' => 'Sheet1!A4:C4']]);

        $output = (new ExcelAddRowNode)->execute(excelRun(), ['workbook_id' => '01A', 'worksheet' => 'Sheet1', 'values' => ['City' => 'Agra', 'Name' => 'Cy']], []);

        expect($output)->toBe(['address' => 'Sheet1!A4:C4', 'first_row' => 4, 'rows_added' => 1])
            ->and(excelBody('PATCH '.EXCEL_SHEET."/range(address='A4:C4')"))->toBe(['values' => [['Cy', null, 'Agra']]]);
    });

    it('starts an empty sheet with the keys of an object', function () {
        excelFake(['GET '.EXCEL_SHEET.'/usedRange(valuesOnly=true)' => excelUsed('A1', [['']]), 'PATCH '.EXCEL_SHEET."/range(address='A1:B2')" => ['address' => 'Sheet1!A1:B2']]);

        (new ExcelAddRowNode)->execute(excelRun(), ['workbook_id' => '01A', 'worksheet' => 'Sheet1', 'values' => ['Name' => 'Ada', 'Age' => 36]], []);

        expect(excelBody('PATCH '.EXCEL_SHEET."/range(address='A1:B2')"))->toBe(['values' => [['Name', 'Age'], ['Ada', 36]]]);
    });

    it('adds several rows and refuses a column that isn\'t there', function () {
        excelFake(['GET '.EXCEL_SHEET.'/usedRange(valuesOnly=true)' => excelUsed('A1:B1', [['Name', 'Age']]), 'PATCH '.EXCEL_SHEET."/range(address='A2:B3')" => ['address' => 'Sheet1!A2:B3']]);

        expect((new ExcelAddRowsNode)->execute(excelRun(), ['workbook_id' => '01A', 'worksheet' => 'Sheet1', 'rows' => [['Ada', 36], ['Bob', 40]]], [])['rows_added'])->toBe(2)
            ->and(fn () => (new ExcelAddRowNode)->execute(excelRun(), ['workbook_id' => '01A', 'worksheet' => 'Sheet1', 'values' => ['Nope' => 1]], []))->toThrow(InvalidArgumentException::class, 'Nope');
    });

    it('updates a row, leaving unnamed columns alone', function () {
        excelFake(['GET '.EXCEL_SHEET.'/usedRange(valuesOnly=true)' => excelUsed('A1:B2', [['Name', 'Age'], ['Ada', 36]]), 'PATCH '.EXCEL_SHEET."/range(address='A2:B2')" => ['address' => 'Sheet1!A2:B2', 'values' => [['Ada', 37]]]]);

        expect((new ExcelUpdateRowNode)->execute(excelRun(), ['workbook_id' => '01A', 'worksheet' => 'Sheet1', 'row' => 2, 'values' => ['Age' => 37]], [])['values'])->toBe([['Ada', 37]])
            ->and(excelBody('PATCH '.EXCEL_SHEET."/range(address='A2:B2')"))->toBe(['values' => [[null, 37]]]);
    });

    it('finds a row, and adds one only when there is none', function () {
        excelFake(['GET '.EXCEL_SHEET.'/usedRange(valuesOnly=true)' => excelUsed('A1:B2', [['Name', 'Age'], ['Ada', 36]]), 'PATCH '.EXCEL_SHEET."/range(address='A3:B3')" => ['address' => 'Sheet1!A3:B3']]);

        expect((new ExcelFindRowNode)->execute(excelRun(), ['workbook_id' => '01A', 'worksheet' => 'Sheet1', 'column' => 'Name', 'value' => 'ada'], [])['first']['_row'])->toBe(2)
            ->and((new ExcelFindOrCreateRowNode)->execute(excelRun(), ['workbook_id' => '01A', 'worksheet' => 'Sheet1', 'column' => 'Name', 'value' => 'Ada', 'values' => ['Ada', 1]], [])['created'])->toBeFalse()
            ->and((new ExcelFindOrCreateRowNode)->execute(excelRun(), ['workbook_id' => '01A', 'worksheet' => 'Sheet1', 'column' => 'Name', 'value' => 'Bob', 'values' => ['Bob', 2]], [])['created'])->toBeTrue();
    });

    it('deletes a row and clears a column', function () {
        excelFake(['POST '.EXCEL_SHEET."/range(address='5:5')/delete" => fn () => Http::response(null, 204), 'POST '.EXCEL_SHEET."/range(address='C:C')/clear" => fn () => Http::response(null, 200)]);

        expect((new ExcelDeleteRowNode)->execute(excelRun(), ['workbook_id' => '01A', 'worksheet' => 'Sheet1', 'row' => 5], []))->toBe(['deleted' => true, 'row' => 5])
            ->and(excelBody('POST '.EXCEL_SHEET."/range(address='5:5')/delete"))->toBe(['shift' => 'Up'])
            ->and((new ExcelClearColumnNode)->execute(excelRun(), ['workbook_id' => '01A', 'worksheet' => 'Sheet1', 'column' => '3'], []))->toBe(['cleared' => true, 'column' => 'C']);
    });

    it('reads and writes a range and a cell', function () {
        excelFake([
            'GET '.EXCEL_SHEET."/range(address='A1:B2')" => ['address' => 'Sheet1!A1:B2', 'values' => [[1, 2], [3, 4]], 'rowCount' => 2],
            'PATCH '.EXCEL_SHEET."/range(address='A1:A2')" => ['address' => 'Sheet1!A1:A2', 'formulas' => [['=1'], ['=2']]],
            'GET '.EXCEL_SHEET.'/cell(row=1,column=2)' => ['address' => 'Sheet1!C2', 'values' => [['x']]],
        ]);

        expect((new ExcelGetRangeNode)->execute(excelRun(), ['workbook_id' => '01A', 'worksheet' => 'Sheet1', 'address' => 'A1:B2'], []))->toMatchArray(['row_count' => 2, 'values' => [[1, 2], [3, 4]]])
            ->and((new ExcelGetCellNode)->execute(excelRun(), ['workbook_id' => '01A', 'worksheet' => 'Sheet1', 'row' => 2, 'column' => 3], [])['value'])->toBe('x');

        (new ExcelUpdateRangeNode)->execute(excelRun(), ['workbook_id' => '01A', 'worksheet' => 'Sheet1', 'address' => 'A1:A2', 'formulas' => [['=1'], ['=2']]], []);

        expect(excelBody('PATCH '.EXCEL_SHEET."/range(address='A1:A2')"))->toBe(['formulas' => [['=1'], ['=2']]]);
    });

    it('works with tables', function () {
        excelFake([
            'POST '.EXCEL_SHEET.'/tables/add' => ['id' => '7', 'name' => 'Table7', 'showHeaders' => true],
            'PATCH /me/drive/items/01A/workbook/tables/7' => ['id' => '7', 'name' => 'Sales'],
            'GET '.EXCEL_TABLE.'/headerRowRange' => ['values' => [['Name', 'Age']]],
            'GET '.EXCEL_TABLE.'/rows' => ['value' => [['index' => 0, 'values' => [['Ada', 36]]], ['index' => 1, 'values' => [['Bob', 40]]]]],
            'POST '.EXCEL_TABLE.'/rows' => ['index' => 2, 'values' => [['Cy', 1]]],
            'PATCH '.EXCEL_TABLE.'/rows/1' => ['index' => 1, 'values' => [['Bob', 41]]],
        ]);

        expect((new ExcelAddTableNode)->execute(excelRun(), ['workbook_id' => '01A', 'worksheet' => 'Sheet1', 'address' => 'A1:B9', 'name' => 'Sales'], [])['name'])->toBe('Sales')
            ->and((new ExcelFindTableRowNode)->execute(excelRun(), ['workbook_id' => '01A', 'table' => 'Sales', 'column' => 'Name', 'value' => 'Bob'], [])['first'])->toBe(['_index' => 1, 'Name' => 'Bob', 'Age' => 40])
            ->and((new ExcelAddTableRowNode)->execute(excelRun(), ['workbook_id' => '01A', 'table' => 'Sales', 'values' => ['Age' => 1, 'Name' => 'Cy']], [])['index'])->toBe(2)
            ->and(excelBody('POST '.EXCEL_TABLE.'/rows'))->toBe(['values' => [['Cy', 1]], 'index' => null])
            ->and((new ExcelUpdateTableRowNode)->execute(excelRun(), ['workbook_id' => '01A', 'table' => 'Sales', 'index' => 1, 'values' => ['Bob', 41]], [])['values'])->toBe([['Bob', 41]]);
    });

    it('lists named items and calls a function', function () {
        excelFake([
            'GET /me/drive/items/01A/workbook/names' => ['value' => [['name' => 'Tax', 'type' => 'Range', 'value' => 'Sheet1!$A$1', 'visible' => true]]],
            'POST /me/drive/items/01A/workbook/functions/pmt' => ['value' => 5625.5, 'error' => null],
        ]);

        expect((new ExcelListNamesNode)->execute(excelRun(), ['workbook_id' => '01A'], [])['names'][0])->toMatchArray(['name' => 'Tax', 'type' => 'Range'])
            ->and((new ExcelCallFunctionNode)->execute(excelRun(), ['workbook_id' => '01A', 'function' => 'PMT', 'arguments' => ['rate' => 4.5]], []))->toBe(['value' => 5625.5, 'error' => null])
            ->and(fn () => (new ExcelCallFunctionNode)->execute(excelRun(), ['workbook_id' => '01A', 'function' => '../x', 'arguments' => ['rate' => 1]], []))->toThrow(InvalidArgumentException::class);
    });

    it('adds a chart and renders it', function () {
        excelFake([
            'POST '.EXCEL_SHEET.'/charts/add' => ['id' => '{9}', 'name' => 'Chart 2', 'height' => 216.0],
            'GET '.EXCEL_SHEET.'/charts/Chart 2/image(width=640)' => ['value' => 'aGk='],
        ]);

        expect((new ExcelAddChartNode)->execute(excelRun(), ['workbook_id' => '01A', 'worksheet' => 'Sheet1', 'chart_type' => 'ColumnClustered', 'source' => 'A1:C4'], [])['name'])->toBe('Chart 2')
            ->and(excelBody('POST '.EXCEL_SHEET.'/charts/add'))->toBe(['type' => 'ColumnClustered', 'sourceData' => 'A1:C4', 'seriesBy' => 'Auto'])
            ->and((new ExcelGetChartImageNode)->execute(excelRun(), ['workbook_id' => '01A', 'worksheet' => 'Sheet1', 'chart' => 'Chart 2', 'width' => 640], []))->toBe(['image_base64' => 'aGk=']);
    });

    it('refreshes one pivot table or all of them', function () {
        excelFake(['POST '.EXCEL_SHEET.'/pivotTables/refreshAll' => fn () => Http::response(null, 204), 'POST '.EXCEL_SHEET.'/pivotTables/P1/refresh' => fn () => Http::response(null, 204)]);

        expect((new ExcelRefreshPivotTableNode)->execute(excelRun(), ['workbook_id' => '01A', 'worksheet' => 'Sheet1'], []))->toBe(['refreshed' => true])
            ->and((new ExcelRefreshPivotTableNode)->execute(excelRun(), ['workbook_id' => '01A', 'worksheet' => 'Sheet1', 'pivot_table' => 'P1'], []))->toBe(['refreshed' => true]);
    });

    it('keeps ids and names from becoming part of the path', function () {
        excelFake([]);

        expect(fn () => (new ExcelGetRangeNode)->execute(excelRun(), ['workbook_id' => '../../me', 'worksheet' => 'Sheet1'], []))->toThrow(InvalidArgumentException::class)
            ->and(fn () => (new ExcelGetRangeNode)->execute(excelRun(), ['workbook_id' => '01A', 'worksheet' => 'a/b'], []))->toThrow(InvalidArgumentException::class)
            ->and(fn () => (new ExcelGetRangeNode)->execute(excelRun(), ['workbook_id' => '01A', 'worksheet' => 'Sheet1', 'address' => 'A1/../x'], []))->toThrow(InvalidArgumentException::class)
            ->and(fn () => (new ExcelGetRangeNode)->execute(excelRun(), ['workbook_id' => '01A', 'drive_id' => 'a/b', 'worksheet' => 'Sheet1'], []))->toThrow(InvalidArgumentException::class);
    });

    it('encodes a sheet name and quotes an address', function () {
        excelFake(['GET /me/drive/items/01A/workbook/worksheets/Q1 Sales/range(address=\'A1\')' => ['address' => 'x']]);

        (new ExcelGetRangeNode)->execute(excelRun(), ['workbook_id' => '01A', 'worksheet' => 'Q1 Sales', 'address' => 'A1'], []);

        expect(excelRequest("GET /me/drive/items/01A/workbook/worksheets/Q1 Sales/range(address='A1')")->url())->toContain('worksheets/Q1%20Sales/range');
    });

    it('explains errors with a hint', function (int $status, string $hint) {
        excelFake(['GET '.EXCEL_SHEET.'/usedRange(valuesOnly=true)' => fn () => Http::response(['error' => ['code' => 'Nope', 'message' => 'Refused']], $status)]);

        expect(fn () => (new ExcelListRowsNode)->execute(excelRun(), ['workbook_id' => '01A', 'worksheet' => 'Sheet1'], []))->toThrow(RuntimeException::class, $hint);
    })->with([[401, 'Reconnect Excel'], [403, 'personal OneDrive'], [404, 'Check the workbook'], [429, 'rate limiting'], [423, 'locked']]);

    it('works out the bounds of an address', function () {
        expect(AbstractExcelNode::bounds("'Q1 Sales'!B2:D9"))->toBe([2, 2, 4, 9])
            ->and(AbstractExcelNode::bounds('Sheet1!$AA$10'))->toBe([27, 10, 27, 10])
            ->and(AbstractExcelNode::columnLetters(28))->toBe('AB')
            ->and(AbstractExcelNode::columnNumber('AB'))->toBe(28);
    });
});

describe('excel triggers', function () {
    it('offers the triggers in the catalog', function () {
        $names = collect(app(NodeRegistry::class)->triggerCatalog())->where('category', 'excel')->pluck('name');

        expect($names)->toContain('Excel: New row', 'Excel: Row added or updated', 'Excel: New row in table', 'Excel: Table row added or updated', 'Excel: New worksheet', 'Excel: New workbook', 'Excel: Workbook updated', 'Excel: Cell value changed');
    });

    it('polls: a baseline first, then one run for each new item', function (string $type, array $nodeConfig, array $first, array $second, string $payloadKey, array $expected) {
        Queue::fake([FireTriggerEvent::class]);
        excelFake([]);
        $owner = User::factory()->create();
        $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
        $workflow = Workflow::factory()->forWorkspace($workspace)->create();
        $credential = ConnectorCredential::factory()->forWorkspace($workspace)
            ->forConnector(Connector::query()->firstWhere('key', 'excel') ?? Connector::factory()->create(['key' => 'excel']))
            ->create(['data' => ['access_token' => 'graph-token'], 'created_by' => $owner->id]);
        $workflow->replaceGraph([
            'nodes' => [
                ['key' => 'start', 'type' => $type, 'config' => ['credential_id' => $credential->id, 'activate' => true, ...$nodeConfig]],
                ['key' => 'next', 'type' => 'transform', 'config' => ['mapping' => []]],
            ],
            'edges' => [['from' => 'start', 'to' => 'next']],
        ]);
        $workflow->fresh()->publishVersion(publisher: $owner);
        $trigger = Trigger::query()->where('target_id', $workflow->id)->where('trigger_node_key', 'start')->firstOrFail();
        SyncTriggerSubscription::dispatchSync($trigger->id);

        excelFake($first);
        dispatch_sync(new PollTrigger($trigger->fresh()));
        expect($trigger->events()->where('status', 'queued')->count())->toBe(0);

        excelFake($second);
        dispatch_sync(new PollTrigger($trigger->fresh()));

        expect($trigger->events()->where('status', 'queued')->get()->pluck("payload.{$payloadKey}")->sort()->values()->all())->toBe($expected);
    })->with([
        'new row' => ['excel_list_rows', ['workbook_id' => '01A', 'worksheet' => 'Sheet1'], ['GET '.EXCEL_SHEET.'/usedRange(valuesOnly=true)' => excelUsed('A1:A2', [['Name'], ['Ada']])], ['GET '.EXCEL_SHEET.'/usedRange(valuesOnly=true)' => excelUsed('A1:A3', [['Name'], ['Ada'], ['Bob']])], 'Name', ['Bob']],
        'updated row' => ['excel_list_updated_rows', ['workbook_id' => '01A', 'worksheet' => 'Sheet1'], ['GET '.EXCEL_SHEET.'/usedRange(valuesOnly=true)' => excelUsed('A1:A3', [['Name'], ['Ada'], ['Bob']])], ['GET '.EXCEL_SHEET.'/usedRange(valuesOnly=true)' => excelUsed('A1:A3', [['Name'], ['Ada'], ['Rob']])], 'Name', ['Rob']],
        'new table row' => ['excel_list_table_rows', ['workbook_id' => '01A', 'table' => 'Sales'], ['GET '.EXCEL_TABLE.'/headerRowRange' => ['values' => [['Name']]], 'GET '.EXCEL_TABLE.'/rows' => ['value' => [['index' => 0, 'values' => [['Ada']]]]]], ['GET '.EXCEL_TABLE.'/headerRowRange' => ['values' => [['Name']]], 'GET '.EXCEL_TABLE.'/rows' => ['value' => [['index' => 0, 'values' => [['Ada']]], ['index' => 1, 'values' => [['Bob']]]]]], 'Name', ['Bob']],
        'updated table row' => ['excel_list_updated_table_rows', ['workbook_id' => '01A', 'table' => 'Sales'], ['GET '.EXCEL_TABLE.'/headerRowRange' => ['values' => [['Name']]], 'GET '.EXCEL_TABLE.'/rows' => ['value' => [['index' => 0, 'values' => [['Ada']]]]]], ['GET '.EXCEL_TABLE.'/headerRowRange' => ['values' => [['Name']]], 'GET '.EXCEL_TABLE.'/rows' => ['value' => [['index' => 0, 'values' => [['Ann']]]]]], 'Name', ['Ann']],
        'new worksheet' => ['excel_list_worksheets', ['workbook_id' => '01A'], ['GET /me/drive/items/01A/workbook/worksheets' => ['value' => [['id' => '{1}', 'name' => 'A']]]], ['GET /me/drive/items/01A/workbook/worksheets' => ['value' => [['id' => '{1}', 'name' => 'A'], ['id' => '{2}', 'name' => 'B']]]], 'name', ['B']],
        'new workbook' => ['excel_list_workbooks', [], ["GET /me/drive/root/search(q='.xlsx')" => ['value' => [['id' => '1', 'name' => 'a.xlsx']]]], ["GET /me/drive/root/search(q='.xlsx')" => ['value' => [['id' => '1', 'name' => 'a.xlsx'], ['id' => '2', 'name' => 'b.xlsx']]]], 'name', ['b.xlsx']],
        'updated workbook' => ['excel_list_updated_workbooks', [], ["GET /me/drive/root/search(q='.xlsx')" => ['value' => [['id' => '1', 'name' => 'a.xlsx', 'lastModifiedDateTime' => '2026-01-01T00:00:00Z']]]], ["GET /me/drive/root/search(q='.xlsx')" => ['value' => [['id' => '1', 'name' => 'a.xlsx', 'lastModifiedDateTime' => '2026-02-01T00:00:00Z']]]], 'updated_at', ['2026-02-01T00:00:00Z']],
        'cell changed' => ['excel_watch_cell', ['workbook_id' => '01A', 'worksheet' => 'Sheet1', 'address' => 'B2'], ['GET '.EXCEL_SHEET."/range(address='B2')" => ['address' => 'Sheet1!B2', 'values' => [[1]], 'text' => [['1']]]], ['GET '.EXCEL_SHEET."/range(address='B2')" => ['address' => 'Sheet1!B2', 'values' => [[2]], 'text' => [['2']]]], 'values.0.0', [2]],
    ]);
});

describe('excel options', function () {
    function excelOptionsQuery(array $config = [], ?string $search = null): NodeOptionsQuery
    {
        return new NodeOptionsQuery(app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']), User::factory()->create(), $config, 'graph-token', $search);
    }

    it('lists workbooks, keeping only workbooks', function () {
        excelFake(["GET /me/drive/root/search(q='.xlsx')" => ['value' => [['id' => '1', 'name' => 'a.xlsx'], ['id' => '2', 'name' => 'a.xlsx.txt']]]]);

        expect(app(ExcelOptions::class)->load('excel.workbooks', excelOptionsQuery())->toArray()['options'])->toBe([['value' => '1', 'label' => 'a.xlsx']]);
    });

    it('lists the sheets, tables, names and charts of a workbook by name', function () {
        excelFake([
            'GET /drives/b!x/items/01A/workbook/worksheets' => ['value' => [['id' => '{1}', 'name' => 'Sales'], ['id' => '{2}', 'name' => 'Costs']]],
            'GET /me/drive/items/01A/workbook/tables' => ['value' => [['id' => '1', 'name' => 'T1']]],
            'GET /me/drive/items/01A/workbook/names' => ['value' => [['name' => 'Tax']]],
            'GET /me/drive/items/01A/workbook/worksheets/Sales/charts' => ['value' => [['id' => '{9}', 'name' => 'Chart 1']]],
            'GET /me/drive/root/children' => ['value' => [['id' => 'F', 'name' => 'Docs', 'folder' => []], ['id' => 'x', 'name' => 'a.txt']]],
            'GET /me/drives' => ['value' => [['id' => 'b!x', 'name' => 'OneDrive', 'driveType' => 'business']]],
        ]);

        expect(app(ExcelOptions::class)->load('excel.worksheets', excelOptionsQuery(['drive_id' => 'b!x', 'workbook_id' => '01A'], 'sal'))->toArray()['options'])->toBe([['value' => 'Sales', 'label' => 'Sales']])
            ->and(app(ExcelOptions::class)->load('excel.tables', excelOptionsQuery(['workbook_id' => '01A']))->toArray()['options'][0]['value'])->toBe('T1')
            ->and(app(ExcelOptions::class)->load('excel.names', excelOptionsQuery(['workbook_id' => '01A']))->toArray()['options'][0]['value'])->toBe('Tax')
            ->and(app(ExcelOptions::class)->load('excel.charts', excelOptionsQuery(['workbook_id' => '01A', 'worksheet' => 'Sales']))->toArray()['options'][0]['value'])->toBe('Chart 1')
            ->and(app(ExcelOptions::class)->load('excel.folders', excelOptionsQuery())->toArray()['options'])->toBe([['value' => 'F', 'label' => 'Docs']])
            ->and(app(ExcelOptions::class)->load('excel.drives', excelOptionsQuery())->toArray()['options'][0])->toBe(['value' => 'b!x', 'label' => 'OneDrive', 'description' => 'business']);
    });
});

describe('excel connector', function () {
    function excelCredential(): ConnectorCredential
    {
        $owner = User::factory()->create();
        $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

        return ConnectorCredential::factory()->forWorkspace($workspace)
            ->forConnector(Connector::query()->firstWhere('key', 'excel') ?? Connector::factory()->create(['key' => 'excel', 'name' => 'Microsoft Excel']))
            ->create(['data' => ['access_token' => 'graph-token'], 'created_by' => $owner->id]);
    }

    it('checks the account against Microsoft Graph', function () {
        Http::fake(['https://graph.microsoft.com/v1.0/me' => Http::response(['mail' => 'ada@acme.com'])]);

        expect(app(ConnectorCredentialTester::class)->test(excelCredential()))->toMatchArray(['ok' => true, 'account' => 'ada@acme.com']);
    });

    it('fails the check on a refused token', function () {
        Http::fake(['https://graph.microsoft.com/v1.0/me' => Http::response(['error' => ['code' => 'InvalidAuthenticationToken']], 401)]);

        expect(app(ConnectorCredentialTester::class)->test(excelCredential()))->toMatchArray(['ok' => false]);
    });
});

it('makes any Graph call, including workbook addresses, and refuses a path that leaves it', function () {
    excelFake(["GET /me/drive/items/01A/workbook/worksheets('My Sheet')" => ['id' => '1']]);

    $node = app(NodeRegistry::class)->resolve('excel_api_request');
    $run = excelRun();
    $output = $node->execute($run, ['method' => 'GET', 'path' => "/me/drive/items/01A/workbook/worksheets('My%20Sheet')"], []);

    expect($output)->toBe(['status' => 200, 'body' => ['id' => '1']])
        ->and($node->effect([])->value)->toBe('external');

    foreach (['https://evil.example/x', '/../x', '/a?b=1'] as $path) {
        expect(fn () => $node->execute($run, ['method' => 'GET', 'path' => $path], []))->toThrow(InvalidArgumentException::class);
    }
});
