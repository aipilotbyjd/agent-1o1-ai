<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class AhrefsWebAnalyticsBreakdownNode extends AbstractAhrefsNode
{
    public function type(): string
    {
        return 'ahrefs_web_analytics_breakdown';
    }

    public function name(): string
    {
        return 'Ahrefs: Web Analytics Breakdown';
    }

    public function description(): string
    {
        return 'Visitors of a project by browser, city, country, device, page, source or UTM parameter.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project_id', 'dimension'],
            'properties' => [
                ...$this->credentialFields(),
                'project_id' => Field::dynamic('Project', 'ahrefs.projects', 'Pick a project, or enter its id.', '123456'),
                'dimension' => Field::select('Break down by', ['browsers' => 'Browsers', 'browser-versions' => 'Browser versions', 'cities' => 'Cities', 'continents' => 'Continents', 'countries' => 'Countries', 'devices' => 'Devices', 'entry-pages' => 'Entry pages', 'exit-pages' => 'Exit pages', 'languages' => 'Languages', 'operating-systems' => 'Operating systems', 'operating-systems-versions' => 'Operating system versions', 'source-channels' => 'Source channels', 'sources' => 'Sources', 'referrers' => 'Referrers', 'top-pages' => 'Top pages', 'utm-params' => 'UTM parameters'], null, 'top-pages'),
                'from' => Field::text('From', 'As YYYY-MM-DD.', '2026-01-01'),
                'to' => Field::text('To', 'As YYYY-MM-DD.', '2026-01-31'),
                'utm_param' => Field::advanced(Field::select('UTM parameter', ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'], 'Only for UTM parameters.')),
                'limit' => Field::integer('Limit', 'The most rows to return.', 100, 1, 100000),
                'order_by' => Field::advanced(Field::text('Sort', 'Columns with optional direction, such as visitors:desc.', 'visitors:desc')),
                'where' => Field::advanced(Field::text('Filter', 'A filter in Ahrefs\' syntax.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $answer = $this->ahrefs($run, $config, 'GET', '/web-analytics/'.$this->dimension($config), ['project_id' => $this->requiredString($config, 'project_id', 'The project'), 'from' => $config['from'] ?? null, 'to' => $config['to'] ?? null, 'utm_param' => $config['utm_param'] ?? null, 'limit' => $config['limit'] ?? 100, 'order_by' => $config['order_by'] ?? null, 'where' => $config['where'] ?? null]);

        return $this->shape($answer, 'stats');
    }

    /**
     * The breakdown named in the settings, checked so it can sit in a URL.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    private function dimension(array $config): string
    {
        $dimension = (string) ($config['dimension'] ?? 'top-pages');

        return preg_match('/^[a-z-]{1,40}$/', $dimension) === 1 ? $dimension : throw new InvalidArgumentException('Unknown breakdown.');
    }
}
