<?php

namespace App\Nodes\DataTransform;

use App\Contracts\NodeContract;
use App\Enums\NodeCategory;
use App\Models\Runs\Run;
use Illuminate\Support\Arr;

/**
 * Reshapes data: `config.mapping` is `{output_key: "dot.path.into.context"}`.
 * Paths are resolved against `$context` as-is — templating
 * (`{{ node.output }}` expressions) lands in Stage 5, see
 * docs/WORKFLOWS_AGENTS_BUILD_PLAN.md.
 */
class TransformNode implements NodeContract
{
    public function type(): string
    {
        return 'transform';
    }

    public function category(): string
    {
        return NodeCategory::DataTransform->value;
    }

    public function name(): string
    {
        return 'Transform';
    }

    public function description(): string
    {
        return 'Reshapes data by mapping output keys to dot-paths into the run context.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['mapping'],
            'properties' => [
                'mapping' => ['type' => 'object'],
            ],
        ];
    }

    /**
     * Exactly the keys of the configured `mapping`. Each value is whatever
     * sat at the mapped context path, which the schema can't narrow — so the
     * properties are declared but left unconstrained.
     */
    public function outputSchema(array $config = []): array
    {
        $properties = [];

        foreach (array_keys($config['mapping'] ?? []) as $outputKey) {
            $properties[$outputKey] = [];
        }

        return ['type' => 'object', 'properties' => $properties];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $mapping = $config['mapping'] ?? [];

        $output = [];

        foreach ($mapping as $outputKey => $path) {
            $output[$outputKey] = Arr::get($context, $path);
        }

        return $output;
    }
}
