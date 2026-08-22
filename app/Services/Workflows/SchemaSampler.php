<?php

namespace App\Services\Workflows;

/**
 * Turns a `NodeContract::outputSchema()` into a representative sample value,
 * plus the set of dot-paths beneath which the schema stops describing
 * anything.
 *
 * `DryRunner` needs both halves. The sample lets a downstream node's
 * `{{ nodes.a.body.id }}` resolve during simulation instead of warning that
 * "nothing provides" it. The open paths cover the other case: a schema like
 * `CallApiNode`'s `body` is an *unconstrained* object — a real run puts a
 * whole API response there, so a reference into it is legitimate and must not
 * warn, even though no sample can enumerate its keys.
 *
 * Speaks the same JSON-schema subset as `ConfigSchemaValidator` — object,
 * array, string, integer, number, boolean, `enum` — and deliberately not a
 * general-purpose JSON Schema implementation.
 */
class SchemaSampler
{
    /**
     * @param  array<string, mixed>  $schema
     */
    public function sample(array $schema): mixed
    {
        if (isset($schema['enum']) && $schema['enum'] !== []) {
            return $schema['enum'][0];
        }

        return match ($schema['type'] ?? null) {
            'object' => $this->sampleObject($schema),
            'array' => isset($schema['items']) ? [$this->sample($schema['items'])] : [],
            'string' => '',
            'integer', 'number' => 0,
            'boolean' => false,
            // A schema with no `type` describes nothing about the value —
            // `FilterNode`'s `value` is written exactly that way.
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function sampleObject(array $schema): array
    {
        $sample = [];

        foreach ($schema['properties'] ?? [] as $field => $fieldSchema) {
            $sample[$field] = $this->sample($fieldSchema);
        }

        return $sample;
    }

    /**
     * Dot-paths (relative to `$prefix`) at which the schema stops constraining
     * the value, so anything *below* them is unknowable rather than absent.
     *
     * @param  array<string, mixed>  $schema
     * @return array<int, string>
     */
    public function openPaths(array $schema, string $prefix = ''): array
    {
        $type = $schema['type'] ?? null;

        if ($type === 'object') {
            $properties = $schema['properties'] ?? [];

            // An object with no declared properties is a free-form bag.
            if ($properties === []) {
                return [$prefix];
            }

            $paths = [];

            foreach ($properties as $field => $fieldSchema) {
                $paths = [...$paths, ...$this->openPaths($fieldSchema, $this->child($prefix, (string) $field))];
            }

            return $paths;
        }

        if ($type === 'array') {
            // `data_get()` addresses list elements as `path.0` and `path.*`,
            // so an item schema's own open paths sit beneath the wildcard.
            return isset($schema['items'])
                ? $this->openPaths($schema['items'], $this->child($prefix, '*'))
                : [$prefix];
        }

        return $type === null ? [$prefix] : [];
    }

    private function child(string $prefix, string $field): string
    {
        return $prefix === '' ? $field : "{$prefix}.{$field}";
    }
}
