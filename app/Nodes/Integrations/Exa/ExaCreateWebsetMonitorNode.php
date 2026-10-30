<?php

namespace App\Nodes\Integrations\Exa;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExaCreateWebsetMonitorNode extends AbstractExaNode
{
    public function type(): string
    {
        return 'exa_create_webset_monitor';
    }

    public function name(): string
    {
        return 'Exa: Create Webset Monitor';
    }

    public function description(): string
    {
        return 'Keeps a webset growing: searches for new items on a cron schedule.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['webset_id', 'cron', 'query'],
            'properties' => [
                ...$this->credentialFields(),
                'webset_id' => $this->websetField(),
                'cron' => Field::text('When (cron)', null, '0 9 * * 1'),
                'timezone' => Field::advanced(Field::text('Time zone', null, 'Etc/UTC')),
                'query' => Field::textarea('Search for', 'What each run looks for.'),
                'count' => Field::integer('How many per run', null, 10, 1),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['monitor' => $this->exa($run, $config, 'POST', self::WEBSETS.'/monitors', [
            'websetId' => $this->requiredString($config, 'webset_id', 'The webset'),
            'cadence' => $this->withoutEmpty(['cron' => $this->requiredString($config, 'cron', 'The schedule'), 'timezone' => $this->textOf($config, 'timezone')]),
            'behavior' => ['type' => 'search', 'config' => ['query' => $this->requiredString($config, 'query', 'What to search for'), 'count' => $this->intOf($config, 'count') ?? 10, 'behavior' => 'append']],
        ])];
    }
}
