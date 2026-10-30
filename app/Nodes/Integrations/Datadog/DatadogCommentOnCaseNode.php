<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogCommentOnCaseNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_comment_on_case';
    }

    public function name(): string
    {
        return 'Datadog: Comment On Case';
    }

    public function description(): string
    {
        return 'Adds a comment to a work item in Case Management.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['case_id', 'comment'],
            'properties' => [
                ...$this->credentialFields(),
                'case_id' => Field::text('Case id'),
                'comment' => Field::textarea('Comment'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->pathId($config, 'case_id', 'The case id');

        return $this->flatten((array) ($this->datadog($run, $config, 'POST', "/api/v2/cases/{$id}/comment", [], ['data' => ['type' => 'case', 'attributes' => ['comment' => $this->requiredString($config, 'comment', 'The comment')]]])['data'] ?? []));
    }
}
