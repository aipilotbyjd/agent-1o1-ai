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
        return "{$this->node->name()}: {$this->node->description()}";
    }

    /**
     * Only the fields `schema()` offers are taken from the model — anything
     * else it sends is dropped, so a field left out of `exposed_fields` can't
     * be set just by naming it. Bound config is spread last as well, so a
     * matching key always loses to the bound value — the model can never
     * choose a credential, channel, or any other field the workspace member
     * fixed at attach time.
     */
    public function handle(Request $request): Stringable|string
    {
        $arguments = array_intersect_key($request->all(), array_flip($this->modelSettableKeys()));

        $config = [...$arguments, ...($this->binding->config ?? [])];

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
        $settableKeys = $this->modelSettableKeys();

        $result = [];

        foreach ($properties as $key => $propertySchema) {
            if (! in_array($key, $settableKeys, true)) {
                continue;
            }

            $type = $this->propertyToType($propertySchema, $schema);

            $result[$key] = in_array($key, $required, true) ? $type->required() : $type;
        }

        return $result;
    }

    /**
     * The config keys the model may supply: exposed (every schema property
     * when `exposed_fields` is unset) and not bound.
     *
     * @return array<int, string>
     */
    private function modelSettableKeys(): array
    {
        $properties = array_keys($this->node->configSchema()['properties'] ?? []);
        $boundKeys = array_keys($this->binding->config ?? []);
        $exposedKeys = $this->binding->exposed_fields ?? $properties;

        return array_values(array_diff(array_intersect($properties, $exposedKeys), $boundKeys));
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
