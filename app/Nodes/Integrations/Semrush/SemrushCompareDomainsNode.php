<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushCompareDomainsNode extends AbstractSemrushReportNode
{
    public function type(): string
    {
        return 'semrush_compare_domains';
    }

    public function name(): string
    {
        return 'Semrush: Compare Domains';
    }

    public function description(): string
    {
        return 'Compares up to five domains by the keywords they share or don\'t (Domain vs. Domain).';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['domains', 'database'],
            'properties' => [
                ...$this->credentialFields(),
                'domains' => Field::list('Domains', 'Up to five. The first is yours.', 'example.com'),
                'mode' => Field::select('Keywords to show', ['shared' => 'Shared by all', 'all' => 'All', 'unique' => 'Only on your domain', 'untapped' => 'On the others, not yours', 'missing' => 'On all the others, not yours', 'exclusive' => 'On only one of them'], null, 'shared'),
                'search' => Field::select('Search', ['or' => 'Organic', 'ad' => 'Paid'], null, 'or'),
                'database' => $this->databaseField(),
                'limit' => $this->limitField(),
                'offset' => $this->offsetField(),
                'date' => $this->dateField(),
                'sort' => $this->sortField(['p0_asc', 'p0_desc', 'p1_asc', 'p1_desc', 'nq_asc', 'nq_desc', 'co_asc', 'co_desc', 'cp_asc', 'cp_desc', 'kd_asc', 'kd_desc']),
                'filter' => $this->filterField(),
                'columns' => $this->columnsField(['Ph', 'P0', 'P1', 'P2', 'P3', 'P4', 'Nr', 'Cp', 'Nq', 'Kd', 'Co', 'Td']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $domains = array_slice(array_map(self::bareDomain(...), $this->requiredList($config, 'domains', 'The domains')), 0, 5);

        return $this->seoReport($run, $config, 'domain_domains', 'Ph,P0,P1,P2,P3,P4,Nr,Cp,Nq,Kd,Co,Td', ['domains' => self::comparison($domains, (string) ($config['mode'] ?? 'shared'), (string) ($config['search'] ?? 'or'))]);
    }

    /**
     * The domains setting in Semrush's form: `<sign>|<type>|<domain>`, joined by `|`.
     * A comparison of "yours" against the others is written with yours first, or last
     * when it looks for what yours is missing.
     *
     * @param  list<string>  $domains
     */
    public static function comparison(array $domains, string $mode, string $search): string
    {
        $first = array_shift($domains);
        $search = $search === 'ad' ? 'ad' : 'or';

        $parts = match ($mode) {
            'all' => [['*', $first], ...array_map(fn (string $domain): array => ['+', $domain], $domains)],
            'unique' => [['*', $first], ...array_map(fn (string $domain): array => ['-', $domain], $domains)],
            'exclusive' => [['*', $first], ...array_map(fn (string $domain): array => ['/', $domain], $domains)],
            'untapped' => [...array_map(fn (string $domain, int $index): array => [$index === 0 ? '*' : '+', $domain], $domains, array_keys($domains)), ['-', $first]],
            'missing' => [...array_map(fn (string $domain): array => ['*', $domain], $domains), ['-', $first]],
            default => [['*', $first], ...array_map(fn (string $domain): array => ['*', $domain], $domains)],
        };

        return implode('|', array_map(fn (array $part): string => "{$part[0]}|{$search}|{$part[1]}", $parts));
    }
}
