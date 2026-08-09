<?php

namespace App\Ai\Tools;

use App\Contracts\NodeContract;
use App\Models\Agents\AgentToolBinding;
use App\Models\Runs\Run;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Wraps a `NodeContract` as a `Laravel\Ai` tool for an `Agent` — the
 * bound-config/exposed-fields security boundary from `AgentToolBinding`'s
 * docblock lives entirely in `handle()`/`schema()` below. **This must never
 * regress**: a tool-call argument can never override a bound config value,
 * and the model is never even shown a schema field for one.
 */
class NodeTool implements Tool
{
    public function __construct(
        private readonly NodeContract $node,
        private readonly AgentToolBinding $binding,
        private readonly Run $run,
    ) {}

    /**
     * The node's own type string, unique per agent (`agent_node`'s
     * `unique(agent_id, node_type)`) — safe to use as the tool name.
     */
    public function name(): string
    {
        return $this->node->type();
    }

    public function description(): Stringable|string
    {
        return "Calls the '{$this->node->type()}' node.";
    }

    /**
     * Bound config is spread *after* the model-supplied arguments, so a
     * matching key always loses to the bound value — the model can never
     * choose a credential, channel, or any other field the workspace member
     * fixed at attach time.
     */
    public function handle(Request $request): Stringable|string
    {
        $config = [...$request->all(), ...($this->binding->config ?? [])];

        $output = $this->node->execute($this->run, $config, ['input' => [], 'nodes' => []]);

        return json_encode($output) ?: '{}';
    }

    /**
     * Only exposed, unbound fields are ever shown to the model — a bound
     * field (even if also listed in `exposed_fields` by mistake) never gets
     * a schema entry, so the model has no way to know it exists to try to
     * override it.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        $configSchema = $this->node->configSchema();
        $properties = $configSchema['properties'] ?? [];
        $required = $configSchema['required'] ?? [];
        $boundKeys = array_keys($this->binding->config ?? []);
        $exposedKeys = $this->binding->exposed_fields ?? array_values(array_diff(array_keys($properties), $boundKeys));

        $result = [];

        foreach ($properties as $key => $propertySchema) {
            if (! in_array($key, $exposedKeys, true) || in_array($key, $boundKeys, true)) {
                continue;
            }

            $type = $this->propertyToType($propertySchema, $schema);

            $result[$key] = in_array($key, $required, true) ? $type->required() : $type;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $propertySchema
     */
    private function propertyToType(array $propertySchema, JsonSchema $schema): Type
    {
        $type = match ($propertySchema['type'] ?? null) {
            'object' => $schema->object(fn () => []),
            'array' => $schema->array()->items(
                isset($propertySchema['items']) ? $this->propertyToType($propertySchema['items'], $schema) : $schema->string(),
            ),
            'integer' => $schema->integer(),
            'boolean' => $schema->boolean(),
            default => $schema->string(),
        };

        if (isset($propertySchema['enum']) && method_exists($type, 'enum')) {
            $type = $type->enum($propertySchema['enum']);
        }

        return $type;
    }
}
