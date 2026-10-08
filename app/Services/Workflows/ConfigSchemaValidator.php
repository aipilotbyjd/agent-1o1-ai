<?php

namespace App\Services\Workflows;

/**
 * Validates a node's `config` against its `NodeContract::configSchema()`.
 * Supports the small subset of JSON-schema shapes this project's node
 * `configSchema()` implementations actually use (`type`: object/array/
 * string/integer/boolean, `required`, `properties`, `items`, `enum`) — not a
 * general-purpose JSON Schema implementation, and deliberately not a new
 * Composer dependency.
 *
 * A value that is exactly one `{{ }}` template (`SafePattern::WHOLE`) is
 * accepted for any type when saving or publishing: its real value only
 * exists at run time, where `ResolvedConfigCaster` converts and checks it.
 */
class ConfigSchemaValidator
{
    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $config
     * @param  bool  $allowTemplates  false once templates have been resolved, so a leftover one is reported
     * @return array<int, string>
     */
    public function validate(array $schema, array $config, bool $allowTemplates = true): array
    {
        return $this->validateValue($schema, $config, '', $allowTemplates);
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function validateValue(array $schema, mixed $value, string $path, bool $allowTemplates): array
    {
        if ($allowTemplates && is_string($value) && preg_match(SafePattern::WHOLE, $value) === 1) {
            return [];
        }

        $type = $schema['type'] ?? null;

        return match ($type) {
            'object' => $this->validateObject($schema, $value, $path, $allowTemplates),
            'array' => $this->validateArray($schema, $value, $path, $allowTemplates),
            'string' => $this->validateScalar($schema, $value, $path, 'is_string', 'a string'),
            'integer' => $this->validateScalar($schema, $value, $path, 'is_int', 'an integer'),
            'boolean' => $this->validateScalar($schema, $value, $path, 'is_bool', 'a boolean'),
            default => $this->validateEnum($schema, $value, $path),
        };
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function validateObject(array $schema, mixed $value, string $path, bool $allowTemplates): array
    {
        if (! is_array($value)) {
            return [$this->label($path).' must be an object.'];
        }

        $errors = [];

        foreach ($schema['required'] ?? [] as $field) {
            if (! array_key_exists($field, $value)) {
                $errors[] = $this->label($this->child($path, $field)).' is required.';
            }
        }

        foreach ($schema['properties'] ?? [] as $field => $fieldSchema) {
            if (array_key_exists($field, $value)) {
                $errors = [...$errors, ...$this->validateValue($fieldSchema, $value[$field], $this->child($path, $field), $allowTemplates)];
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function validateArray(array $schema, mixed $value, string $path, bool $allowTemplates): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return [$this->label($path).' must be an array.'];
        }

        if (! isset($schema['items'])) {
            return [];
        }

        $errors = [];

        foreach ($value as $index => $item) {
            $errors = [...$errors, ...$this->validateValue($schema['items'], $item, "{$path}[{$index}]", $allowTemplates)];
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function validateScalar(array $schema, mixed $value, string $path, callable $check, string $expected): array
    {
        if (! $check($value)) {
            return [$this->label($path)." must be {$expected}."];
        }

        return $this->validateEnum($schema, $value, $path);
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function validateEnum(array $schema, mixed $value, string $path): array
    {
        if (isset($schema['enum']) && ! in_array($value, $schema['enum'], true)) {
            return [$this->label($path).' must be one of: '.implode(', ', $schema['enum']).'.'];
        }

        return [];
    }

    private function child(string $path, string $field): string
    {
        return $path === '' ? $field : "{$path}.{$field}";
    }

    private function label(string $path): string
    {
        return $path === '' ? 'config' : "config.{$path}";
    }
}
