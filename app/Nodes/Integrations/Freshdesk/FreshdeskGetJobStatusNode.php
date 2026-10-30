<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskGetJobStatusNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_get_job_status';
    }

    public function name(): string
    {
        return 'Freshdesk: Get Job Status';
    }

    public function description(): string
    {
        return 'Checks how a background job, such as a bulk update, went.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['job_id'],
            'properties' => [
                ...$this->credentialFields(),
                'job_id' => Field::text('Job id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->safeId($config, 'job_id', 'The job');

        return $this->findOne($run, $config, "/jobs/{$id}", $id);
    }
}
