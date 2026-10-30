<?php

namespace App\Nodes\Integrations\Exa;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExaCreateAgentRunNode extends AbstractExaNode
{
    public function type(): string
    {
        return 'exa_create_agent_run';
    }

    public function name(): string
    {
        return 'Exa: Start Research';
    }

    public function description(): string
    {
        return 'Starts an Exa Agent on deep research, list-building or enrichment, with cited or structured results, and returns the run id at once. Read it with Get Research, or start a flow with the Research Finished trigger.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['query'],
            'properties' => [
                ...$this->credentialFields(),
                'query' => Field::textarea('What to research'),
                'effort' => Field::select('Effort', ['auto', 'minimal', 'low', 'medium', 'high', 'xhigh', 'ultra'], 'Higher takes longer and costs more.', 'auto'),
                'output_schema' => Field::json('Result shape', 'A JSON schema for the result.'),
                'system_prompt' => Field::advanced(Field::textarea('Instructions')),
                'input_data' => Field::advanced(Field::json('Records to enrich', 'A list of records.', 'array')),
                'previous_run_id' => Field::advanced(Field::text('Continue from', 'The id of an earlier run.')),
                'max_cost_usd' => Field::advanced(Field::integer('Max cost (USD)', null, null, 1)),
                'max_seconds' => Field::advanced(Field::integer('Max time (seconds)', null, null, 1)),
                'metadata' => Field::advanced(Field::keyValue('Metadata')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shapeAgentRun($this->exa($run, $config, 'POST', '/agent/runs', $this->withoutEmpty([
            'query' => $this->requiredString($config, 'query', 'What to research'),
            'effort' => $this->textOf($config, 'effort'),
            'outputSchema' => $this->decoded($config['output_schema'] ?? null, 'The result shape'),
            'systemPrompt' => $this->textOf($config, 'system_prompt'),
            'input' => $this->withoutEmpty(['data' => $this->decoded($config['input_data'] ?? null, 'The records')]),
            'previousRunId' => $this->textOf($config, 'previous_run_id'),
            'budget' => $this->withoutEmpty(['maxCostDollars' => $this->floatOf($config, 'max_cost_usd'), 'maxDurationSeconds' => $this->intOf($config, 'max_seconds')]),
            'metadata' => $this->decoded($config['metadata'] ?? null, 'The metadata'),
        ])));
    }
}
