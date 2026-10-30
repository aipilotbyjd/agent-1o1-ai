<?php

namespace App\Nodes\Integrations\Semrush;

use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

/**
 * A node that reads one SEO, Backlinks or Trends report. Every setting has one name
 * across the family and is sent under the name Semrush gives it, so a node only says
 * which report it is and which settings it offers.
 */
abstract class AbstractSemrushReportNode extends AbstractSemrushNode
{
    /**
     * Node settings and the report parameters they fill.
     *
     * @var array<string, string>
     */
    private const array PARAMETERS = [
        'limit' => 'display_limit',
        'offset' => 'display_offset',
        'date' => 'display_date',
        'sort' => 'display_sort',
        'filter' => 'display_filter',
        'positions' => 'display_positions',
        'positions_type' => 'display_positions_type',
        'daily' => 'display_daily',
        'target_type' => 'target_type',
        'database' => 'database',
    ];

    /**
     * Reads a report of the SEO API, or of its Backlinks part when the path says so.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query  what the node sets itself
     * @return array{rows: list<array<string, string>>, count: int}
     */
    protected function seoReport(Run $run, array $config, string $type, string $columns, array $query = [], string $path = '/'): array
    {
        $parameters = ['type' => $type, 'export_escape' => 1, 'export_columns' => $this->columns($config, $columns), ...$query];

        foreach (self::PARAMETERS as $setting => $parameter) {
            if (filled($config[$setting] ?? null) && ! array_key_exists($parameter, $parameters)) {
                $parameters[$parameter] = is_bool($config[$setting]) ? (int) $config[$setting] : $config[$setting];
            }
        }

        if (array_key_exists('daily', $config) && ! $config['daily']) {
            unset($parameters['display_daily']);
        }

        return $this->semrushRows($run, $config, $path, $parameters);
    }

    /**
     * Reads a report of the Trends API, whose parameters keep their own names.
     *
     * @param  array<string, mixed>  $config
     * @param  list<string>  $parameters  the settings to send as they are
     * @param  list<string>  $lists  those that hold several values
     * @return array{rows: list<array<string, string>>, count: int}
     */
    protected function trendsReport(Run $run, array $config, string $report, array $parameters, array $lists = []): array
    {
        $query = ['export_columns' => $this->columns($config, ''), 'display_limit' => $config['limit'] ?? null, 'display_offset' => $config['offset'] ?? null];

        foreach ($parameters as $parameter) {
            $value = in_array($parameter, $lists, true) ? implode(',', $this->listOf($config, $parameter)) : ($config[$parameter] ?? null);

            if (filled($value) || $value === false) {
                $query[$parameter] = is_bool($value) ? ($value ? 'true' : 'false') : $value;
            }
        }

        return $this->semrushRows($run, $config, "/analytics/ta/api/v3/{$report}", $query);
    }

    /**
     * The columns setting without spaces, or the report's default.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function columns(array $config, string $default): string
    {
        $columns = implode(',', $this->listOf($config, 'columns')) ?: $default;

        if ($columns !== '' && preg_match('/^[A-Za-z0-9_,]+$/', $columns) !== 1) {
            throw new InvalidArgumentException('Columns are codes separated by commas, such as Ph,Po,Nq.');
        }

        return $columns;
    }

    /**
     * The regional database, such as `us`.
     *
     * @return array<string, mixed>
     */
    protected function databaseField(): array
    {
        return Field::text('Database', 'The regional database, as a lower-case country code: us, uk, de, in and so on.', 'us');
    }

    /**
     * @param  list<string>  $available
     * @return array<string, mixed>
     */
    protected function columnsField(array $available): array
    {
        return Field::advanced(Field::text('Columns', 'Codes separated by commas. Empty uses the usual ones. Available: '.implode(', ', $available).'.', $available[0] ?? null));
    }

    /**
     * @param  list<string>  $sorts
     * @return array<string, mixed>
     */
    protected function sortField(array $sorts): array
    {
        return Field::advanced(Field::text('Sort', 'One of: '.implode(', ', $sorts).'.', $sorts[0] ?? null));
    }

    /**
     * @return array<string, mixed>
     */
    protected function limitField(int $default = 100): array
    {
        return Field::integer('Limit', 'The most rows to return. Semrush charges per row.', $default, 1, 100000);
    }

    /**
     * @return array<string, mixed>
     */
    protected function offsetField(): array
    {
        return Field::advanced(Field::integer('Skip', 'Rows to skip first. Raise the limit by the same number.', null, 0));
    }

    /**
     * @return array<string, mixed>
     */
    protected function filterField(): array
    {
        return Field::advanced(Field::text('Filter', 'In Semrush\'s form: sign, field, operation and value, such as +|Ph|Co|seo.', '+|Ph|Co|seo'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function dateField(): array
    {
        return Field::advanced(Field::text('Date', 'A past month as YYYYMM15. Empty is the latest data. History costs more API units.', '20260115'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function targetTypeField(): array
    {
        return Field::select('Target type', ['root_domain' => 'Root domain', 'domain' => 'Subdomain', 'url' => 'URL'], null, 'root_domain');
    }
}
