<?php

namespace App\Nodes\Integrations\Excel;

use App\Contracts\DeclaresEffect;
use App\Contracts\NodeContract;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ResolvesConnectorCredential;
use App\Nodes\Support\Field;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use stdClass;

/**
 * Shared request, error handling, validation and result shaping for the Excel node
 * family: workbooks in OneDrive for Business and SharePoint through Microsoft Graph,
 * logged in the same way as Outlook. Calls are sessionless, so each change is saved
 * as it is made. A workbook is named by its drive item id, on the account's own
 * OneDrive unless a drive id picks another one. A sheet and a table are named by
 * their name or id; Graph takes either in the same place of the path.
 */
abstract class AbstractExcelNode implements DeclaresEffect, NodeContract
{
    use ResolvesConnectorCredential;

    public const string BASE_URL = 'https://graph.microsoft.com/v1.0';

    /**
     * An empty workbook with one sheet. Graph can't open a zero-byte file, so a new
     * workbook has to be uploaded with real content.
     */
    private const string BLANK_WORKBOOK = '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';

    public function category(): string
    {
        return 'excel';
    }

    /**
     * Call Graph and return the decoded body (`ok` for an empty answer).
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    protected function graph(Run $run, array $config, string $method, string $path, array $query = [], ?array $body = null): array
    {
        $response = $this->graphResponse($run, $config, $method, $path, $query, $body);

        $this->assertOk($response);

        return $response->json() ?? ['ok' => true];
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $body
     */
    protected function graphResponse(Run $run, array $config, string $method, string $path, array $query = [], ?array $body = null): Response
    {
        $options = ['query' => array_filter($query, fn (mixed $value): bool => $value !== null)];

        if ($body !== null) {
            $options['json'] = $body === [] ? new stdClass : $body;
        }

        return Http::withToken($this->resolveAccessToken($run, $config))->acceptJson()->timeout(60)->send(strtoupper($method), self::BASE_URL.$path, $options);
    }

    /**
     * @throws RuntimeException
     */
    protected function assertOk(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $message = (string) ($response->json('error.message') ?? $response->json('error.code') ?? "HTTP {$response->status()}");
        $hint = match ($response->status()) {
            401 => ' Reconnect Excel: the token was refused or has expired.',
            403 => ' The account has no access to this file. Excel works on OneDrive for Business and SharePoint, not on personal OneDrive.',
            404 => ' Check the workbook, sheet, table or range.',
            409, 423 => ' The workbook is locked or being changed by someone else; try again in a moment.',
            429 => ' Microsoft Graph is rate limiting the account; try again in a moment.',
            default => '',
        };

        throw new RuntimeException('Excel error: '.mb_substr($message, 0, 400).(str_ends_with($message, '.') ? '' : '.').$hint);
    }

    /**
     * The drive the settings point at: the account's own, or the drive id.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function drive(array $config): string
    {
        return filled($config['drive_id'] ?? null) ? '/drives/'.$this->pathId($config, 'drive_id', 'The drive') : '/me/drive';
    }

    /**
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function item(array $config, string $key = 'workbook_id', string $label = 'The workbook'): string
    {
        return $this->drive($config).'/items/'.$this->pathId($config, $key, $label);
    }

    /**
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function workbook(array $config): string
    {
        return $this->item($config).'/workbook';
    }

    /**
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function sheet(array $config): string
    {
        return $this->workbook($config).'/worksheets/'.$this->nameSegment($config, 'worksheet', 'The worksheet');
    }

    /**
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function table(array $config): string
    {
        return $this->workbook($config).'/tables/'.$this->nameSegment($config, 'table', 'The table');
    }

    /**
     * `/worksheets/{name}/range(address='A1:B2')`.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function range(array $config, ?string $address = null): string
    {
        return $this->sheet($config).'/range(address='.$this->quotedAddress($address ?? $this->requiredString($config, 'address', 'The range')).')';
    }

    /**
     * An A1 address as a quoted function argument, with `:` and `!` left readable.
     *
     * @throws InvalidArgumentException
     */
    protected function quotedAddress(string $address): string
    {
        if (preg_match('/^[^\x00-\x1F\/\\\\?#]{1,255}$/u', $address) !== 1) {
            throw new InvalidArgumentException('The range must be an A1 address, such as A1:C10.');
        }

        return "'".str_replace(['%21', '%3A'], ['!', ':'], rawurlencode(str_replace("'", "''", $address)))."'";
    }

    /**
     * A drive, item or folder id, checked so it can sit in a URL.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function pathId(array $config, string $key, string $label): string
    {
        $value = $this->requiredString($config, $key, $label);

        return preg_match('/^[A-Za-z0-9!_.\-]{1,256}$/', $value) === 1 ? rawurlencode($value) : throw new InvalidArgumentException("{$label} id may only hold letters, digits, !, _, . and -.");
    }

    /**
     * A sheet, table, column, chart or name, URL-encoded: names may hold spaces, but
     * never a path of their own.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function nameSegment(array $config, string $key, string $label): string
    {
        $value = $this->requiredString($config, $key, $label);

        if ($value === '..' || $value === '.' || preg_match('/[\x00-\x1F\/\\\\?#]/u', $value) === 1) {
            throw new InvalidArgumentException("{$label} name can't hold /, \\, ? or #.");
        }

        return rawurlencode($value);
    }

    /**
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function requiredString(array $config, string $key, string $label): string
    {
        $value = trim((string) ($config[$key] ?? ''));

        return $value !== '' ? $value : throw new InvalidArgumentException("{$label} is required.");
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function limit(array $config, int $default = 100, int $max = 5000): int
    {
        return max(1, min($max, (int) ($config['limit'] ?? $default)));
    }

    /**
     * A whole number from a setting.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function wholeNumber(array $config, string $key, string $label, int $minimum = 1): int
    {
        $value = $config[$key] ?? null;

        if (! is_numeric($value) || (int) $value < $minimum) {
            throw new InvalidArgumentException("{$label} must be a whole number of at least {$minimum}.");
        }

        return (int) $value;
    }

    /**
     * Cell values as rows: a JSON string, one row as a flat list, or a list of rows.
     *
     * @param  array<string, mixed>  $config
     * @return list<list<mixed>>
     *
     * @throws InvalidArgumentException
     */
    protected function matrix(array $config, string $key = 'values', string $label = 'Values'): array
    {
        $rows = $this->decoded($config, $key, $label);
        $rows = array_is_list($rows) && is_array($rows[0] ?? null) ? $rows : [$rows];

        return array_map(fn (mixed $row): array => array_values((array) $row), $rows);
    }

    /**
     * A list or object setting that may arrive as JSON text.
     *
     * @param  array<string, mixed>  $config
     * @return array<int|string, mixed>
     *
     * @throws InvalidArgumentException
     */
    protected function decoded(array $config, string $key, string $label): array
    {
        $value = $config[$key] ?? null;
        $value = is_string($value) ? json_decode($value, true) : $value;

        if (! is_array($value) || $value === []) {
            throw new InvalidArgumentException("{$label} is required, as a list or an object.");
        }

        return $value;
    }

    /**
     * The sheet's used cells, or the given range, with the values only.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function usedRange(Run $run, array $config): array
    {
        $path = filled($config['address'] ?? null) ? $this->range($config) : $this->sheet($config).'/usedRange(valuesOnly=true)';

        return $this->graph($run, $config, 'GET', $path, ['$select' => 'address,values,rowCount,columnCount']);
    }

    /**
     * The first column, first row, last column and last row of an A1 address, as
     * 1-based numbers. A sheet with nothing on it answers `A1`.
     *
     * @return array{0: int, 1: int, 2: int, 3: int}
     */
    public static function bounds(string $address): array
    {
        $cells = str_contains($address, '!') ? substr($address, (int) strrpos($address, '!') + 1) : $address;
        [$from, $to] = array_pad(explode(':', str_replace('$', '', $cells), 2), 2, null);
        $to ??= $from;

        preg_match('/^([A-Za-z]*)(\d*)$/', (string) $from, $start);
        preg_match('/^([A-Za-z]*)(\d*)$/', (string) $to, $end);

        return [self::columnNumber((string) ($start[1] ?? '')), (int) (($start[2] ?? '') ?: 1), self::columnNumber((string) ($end[1] ?? '')), (int) (($end[2] ?? '') ?: 1)];
    }

    /**
     * `C` to 3. Empty is the first column.
     */
    public static function columnNumber(string $letters): int
    {
        $number = 0;

        foreach (str_split(strtoupper($letters)) as $letter) {
            $number = $number * 26 + ord($letter) - 64;
        }

        return max(1, $number);
    }

    /**
     * 3 to `C`.
     */
    public static function columnLetters(int $number): string
    {
        $letters = '';

        for (; $number > 0; $number = intdiv($number - 1, 26)) {
            $letters = chr(65 + ($number - 1) % 26).$letters;
        }

        return $letters;
    }

    /**
     * Column names of a read range: the first row's cells.
     *
     * @param  array<string, mixed>  $range
     * @return list<string>
     */
    protected function headerOf(array $range): array
    {
        $first = array_values((array) (array_values((array) ($range['values'] ?? []))[0] ?? []));

        return array_map(fn (mixed $name, int $column): string => trim((string) $name) !== '' ? (string) $name : 'column_'.($column + 1), $first, array_keys($first));
    }

    /**
     * A read range as records keyed by its first row, each with `_row` (its number in
     * the sheet). Blank rows are left out.
     *
     * @param  array<string, mixed>  $range
     * @return list<array<string, mixed>>
     */
    protected function recordsOf(array $range): array
    {
        $values = array_values((array) ($range['values'] ?? []));
        $header = $this->headerOf($range);
        [, $firstRow] = self::bounds((string) ($range['address'] ?? 'A1'));
        $records = [];

        foreach (array_slice($values, 1) as $index => $row) {
            $cells = array_values((array) $row);

            if (array_filter($cells, fn (mixed $cell): bool => $cell !== '' && $cell !== null) === []) {
                continue;
            }

            $record = ['_row' => $firstRow + $index + 1];

            foreach ($header as $column => $name) {
                $record[$name] = $cells[$column] ?? null;
            }

            $records[] = $record;
        }

        return $records;
    }

    /**
     * Records with `_version`, a fingerprint of their cells that changes when one does.
     *
     * @param  list<array<string, mixed>>  $records
     * @return list<array<string, mixed>>
     */
    protected function versioned(array $records): array
    {
        return array_map(fn (array $record): array => [...$record, '_version' => substr(md5(json_encode(array_diff_key($record, ['_row' => true, '_index' => true]), JSON_THROW_ON_ERROR)), 0, 12)], $records);
    }

    /**
     * Only the last records: a poll looks at the first `poll_max_items` it is given,
     * so a longer list would hide the newest rows behind older ones.
     *
     * @param  list<array<string, mixed>>  $records
     * @return list<array<string, mixed>>
     */
    protected function recentRecords(array $records): array
    {
        $keep = max(1, (int) config('triggers.poll_max_items'));

        return count($records) > $keep ? array_slice($records, -$keep) : $records;
    }

    /**
     * A row's cells in sheet order: a list as it is, an object by the column names
     * (a column it doesn't name stays `null`, which Graph leaves untouched).
     *
     * @param  array<int|string, mixed>  $values
     * @param  list<string>  $header
     * @return list<mixed>
     *
     * @throws InvalidArgumentException
     */
    protected function cellsFor(array $values, array $header): array
    {
        if (array_is_list($values)) {
            return $values;
        }

        $unknown = array_diff(array_map('strval', array_keys($values)), $header);

        if ($unknown !== []) {
            throw new InvalidArgumentException('There is no column named: '.implode(', ', $unknown).'.');
        }

        return array_map(fn (string $name): mixed => $values[$name] ?? null, $header);
    }

    /**
     * Writes rows below the sheet's last used row. An empty sheet given objects gets
     * their keys as its first row.
     *
     * @param  array<string, mixed>  $config
     * @param  list<array<int|string, mixed>>  $rows
     * @return array{address: string|null, first_row: int, rows_added: int}
     */
    protected function appendRows(Run $run, array $config, array $rows): array
    {
        $used = $this->graph($run, $config, 'GET', $this->sheet($config).'/usedRange(valuesOnly=true)', ['$select' => 'address,values']);
        $values = array_values((array) ($used['values'] ?? []));
        $empty = count($values) <= 1 && array_filter((array) ($values[0] ?? []), fn (mixed $cell): bool => $cell !== '' && $cell !== null) === [];
        [$firstColumn, $firstRow, , $lastRow] = self::bounds((string) ($used['address'] ?? 'A1'));
        $header = $this->headerOf($used);
        $next = $empty ? $firstRow : $lastRow + 1;

        if ($empty && ! array_is_list($rows[0])) {
            $header = array_map('strval', array_keys($rows[0]));
            $rows = [$header, ...$rows];
        }

        $cells = array_map(fn (array $row): array => $this->cellsFor($row, $header), $rows);
        $width = max(array_map('count', $cells));
        $cells = array_map(fn (array $row): array => array_pad($row, $width, null), $cells);
        $address = self::columnLetters($firstColumn).$next.':'.self::columnLetters($firstColumn + $width - 1).($next + count($cells) - 1);

        $written = $this->graph($run, $config, 'PATCH', $this->range($config, $address), [], ['values' => $cells]);

        return ['address' => $written['address'] ?? $address, 'first_row' => $next, 'rows_added' => count($cells)];
    }

    /**
     * Reads the header row of a table.
     *
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    protected function tableHeader(Run $run, array $config): array
    {
        return $this->headerOf($this->graph($run, $config, 'GET', $this->table($config).'/headerRowRange', ['$select' => 'values']));
    }

    /**
     * A table's rows as records keyed by its header, each with `_index` (its place in
     * the table, from 0).
     *
     * @param  array<string, mixed>  $config
     * @return list<array<string, mixed>>
     */
    protected function tableRecords(Run $run, array $config): array
    {
        $header = $this->tableHeader($run, $config);
        $rows = (array) ($this->graph($run, $config, 'GET', $this->table($config).'/rows', ['$select' => 'index,values'])['value'] ?? []);

        return array_map(function (array $row) use ($header): array {
            $cells = array_values((array) (array_values((array) ($row['values'] ?? []))[0] ?? []));
            $record = ['_index' => (int) ($row['index'] ?? 0)];

            foreach ($header as $column => $name) {
                $record[$name] = $cells[$column] ?? null;
            }

            return $record;
        }, array_values(array_filter($rows, 'is_array')));
    }

    /**
     * Records whose column holds the value.
     *
     * @param  list<array<string, mixed>>  $records
     * @return list<array<string, mixed>>
     */
    protected function matching(array $records, string $column, string $value, string $match = 'equals'): array
    {
        return array_values(array_filter($records, function (array $record) use ($column, $value, $match): bool {
            $cell = mb_strtolower((string) ($record[$column] ?? ''));
            $wanted = mb_strtolower($value);

            return $match === 'contains' ? str_contains($cell, $wanted) : $cell === $wanted;
        }));
    }

    /**
     * Workbooks found by a search, newest change first. Graph's search looks at names
     * and contents, so anything that isn't an Open XML workbook is dropped.
     *
     * @param  array<string, mixed>  $config
     * @return list<array<string, mixed>>
     */
    protected function searchWorkbooks(Run $run, array $config, string $query, int $limit): array
    {
        $path = $this->drive($config)."/root/search(q='".rawurlencode(str_replace("'", "''", $query))."')";
        $found = (array) ($this->graph($run, $config, 'GET', $path, ['$top' => 200, '$select' => 'id,name,webUrl,size,createdDateTime,lastModifiedDateTime,parentReference,file,lastModifiedBy'])['value'] ?? []);
        $books = array_values(array_filter($found, fn (mixed $item): bool => is_array($item) && preg_match('/\.(xlsx|xlsm|xlsb)$/i', (string) ($item['name'] ?? '')) === 1));

        usort($books, fn (array $a, array $b): int => strcmp((string) ($b['lastModifiedDateTime'] ?? ''), (string) ($a['lastModifiedDateTime'] ?? '')));

        return array_map($this->shapeItem(...), array_slice($books, 0, $limit));
    }

    /**
     * Uploads an empty workbook to a `.../root:/name.xlsx:/content` style path, renamed
     * by Graph if the name is taken.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function uploadBlankWorkbook(Run $run, array $config, string $path): array
    {
        $response = Http::withToken($this->resolveAccessToken($run, $config))->acceptJson()->timeout(60)
            ->withBody((string) base64_decode(self::BLANK_WORKBOOK), 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->put(self::BASE_URL.$path.'?@microsoft.graph.conflictBehavior=rename');

        $this->assertOk($response);

        return (array) $response->json();
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    protected function shapeItem(array $item): array
    {
        return [
            'id' => $item['id'] ?? null,
            'name' => $item['name'] ?? null,
            'url' => $item['webUrl'] ?? null,
            'size' => $item['size'] ?? null,
            'created_at' => $item['createdDateTime'] ?? null,
            'updated_at' => $item['lastModifiedDateTime'] ?? null,
            'drive_id' => data_get($item, 'parentReference.driveId'),
            'folder' => data_get($item, 'parentReference.path'),
            'updated_by' => data_get($item, 'lastModifiedBy.user.displayName'),
        ];
    }

    /**
     * @param  array<string, mixed>  $sheet
     * @return array<string, mixed>
     */
    protected function shapeWorksheet(array $sheet): array
    {
        return ['id' => $sheet['id'] ?? null, 'name' => $sheet['name'] ?? null, 'position' => $sheet['position'] ?? null, 'visibility' => $sheet['visibility'] ?? null];
    }

    /**
     * @param  array<string, mixed>  $table
     * @return array<string, mixed>
     */
    protected function shapeTable(array $table): array
    {
        return ['id' => $table['id'] ?? null, 'name' => $table['name'] ?? null, 'show_headers' => $table['showHeaders'] ?? null, 'show_totals' => $table['showTotals'] ?? null, 'style' => $table['style'] ?? null];
    }

    /**
     * @param  array<string, mixed>  $range
     * @return array<string, mixed>
     */
    protected function shapeRange(array $range): array
    {
        return [
            'address' => $range['address'] ?? null,
            'row_count' => $range['rowCount'] ?? null,
            'column_count' => $range['columnCount'] ?? null,
            'values' => $range['values'] ?? null,
            'text' => $range['text'] ?? null,
            'formulas' => $range['formulas'] ?? null,
            'number_format' => $range['numberFormat'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $column
     * @return array<string, mixed>
     */
    protected function shapeColumn(array $column): array
    {
        return ['id' => $column['id'] ?? null, 'name' => $column['name'] ?? null, 'index' => $column['index'] ?? null, 'values' => $column['values'] ?? null];
    }

    /**
     * @param  array<string, mixed>  $chart
     * @return array<string, mixed>
     */
    protected function shapeChart(array $chart): array
    {
        return ['id' => $chart['id'] ?? null, 'name' => $chart['name'] ?? null, 'height' => $chart['height'] ?? null, 'width' => $chart['width'] ?? null, 'left' => $chart['left'] ?? null, 'top' => $chart['top'] ?? null];
    }

    /**
     * @param  array<string, mixed>  $name
     * @return array<string, mixed>
     */
    protected function shapeName(array $name): array
    {
        return ['name' => $name['name'] ?? null, 'type' => $name['type'] ?? null, 'value' => $name['value'] ?? null, 'visible' => $name['visible'] ?? null, 'comment' => $name['comment'] ?? null, 'scope' => $name['scope'] ?? null];
    }

    /**
     * The account picker, the drive and the workbook, which nearly every node starts with.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function workbookFields(): array
    {
        return [
            ...$this->credentialFields(),
            'drive_id' => $this->driveField(),
            'workbook_id' => Field::dynamic('Workbook', 'excel.workbooks', 'Pick a workbook, or enter its drive item id.', '01ABCDEF', uses: ['drive_id']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function driveField(): array
    {
        return Field::advanced(Field::dynamic('Drive', 'excel.drives', 'A SharePoint document library or another drive. Empty is your own OneDrive.', 'b!abc'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function worksheetField(): array
    {
        return Field::dynamic('Worksheet', 'excel.worksheets', 'Pick a sheet, or enter its name.', 'Sheet1', dependsOn: ['workbook_id'], uses: ['drive_id']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function tableField(): array
    {
        return Field::dynamic('Table', 'excel.tables', 'Pick a table, or enter its name.', 'Table1', dependsOn: ['workbook_id'], uses: ['drive_id']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function addressField(string $title = 'Range'): array
    {
        return Field::text($title, 'An A1 address on the sheet.', 'A1:C10');
    }

    /**
     * @return array<string, mixed>
     */
    protected function valuesField(string $description = 'A list of cell values, or an object keyed by column name.'): array
    {
        return Field::json('Values', $description);
    }
}
