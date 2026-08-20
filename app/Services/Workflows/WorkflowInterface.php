<?php

namespace App\Services\Workflows;

use App\Enums\Workflows\InterfaceFieldType;
use App\Models\Workflows\Workflow;
use Illuminate\Validation\Rule;

/**
 * A workflow's front door: the fields a caller is expected to supply as the
 * run's `input`. This is what turns "POST some arbitrary JSON at a workflow"
 * into a form a non-author can fill in.
 *
 * Two sources, in order:
 *
 * 1. **Declared** — `workflows.input_schema`, authored deliberately, with
 *    labels, types, defaults and required-ness.
 * 2. **Derived** — every `{{ input.* }}` reference in the graph the workflow
 *    would actually run, so a workflow nobody has annotated still presents a
 *    usable interface instead of an empty one.
 *
 * Derivation reads the *published* version when there is one, falling back to
 * the draft: the interface should describe what a run will really execute,
 * and a run is always pinned to the published version.
 */
class WorkflowInterface
{
    /**
     * @return array{source: 'declared'|'derived', fields: array<int, array<string, mixed>>}
     */
    public function describe(Workflow $workflow): array
    {
        $declared = $workflow->input_schema['fields'] ?? null;

        if (is_array($declared) && $declared !== []) {
            return ['source' => 'declared', 'fields' => array_values(array_map($this->normalizeField(...), $declared))];
        }

        return ['source' => 'derived', 'fields' => $this->derive($workflow)];
    }

    /**
     * Validation rules for a submitted form, keyed under `input.*` to match
     * the run payload the fields become.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(Workflow $workflow): array
    {
        $rules = [];

        foreach ($this->describe($workflow)['fields'] as $field) {
            $type = InterfaceFieldType::tryFrom($field['type']) ?? InterfaceFieldType::String;

            $rule = [$field['required'] ? 'required' : 'nullable', ...$type->rules()];

            if ($type === InterfaceFieldType::Select && $field['options'] !== []) {
                $rule[] = Rule::in(array_column($field['options'], 'value'));
            }

            $rules["input.{$field['key']}"] = $rule;
        }

        return $rules;
    }

    /**
     * Fills in any field the submission omitted but the schema gives a
     * default for — so a form can leave optional inputs out entirely and the
     * run still receives what the graph expects.
     *
     * @param  array<string, mixed>  $submitted
     * @return array<string, mixed>
     */
    public function applyDefaults(Workflow $workflow, array $submitted): array
    {
        foreach ($this->describe($workflow)['fields'] as $field) {
            if (! array_key_exists($field['key'], $submitted) && $field['default'] !== null) {
                $submitted[$field['key']] = $field['default'];
            }
        }

        return $submitted;
    }

    /**
     * Every top-level `input.*` key the graph reads.
     *
     * Only the first segment is taken: `{{ input.customer.email }}` means the
     * run input needs a `customer` object, not a field literally named
     * `customer.email`, and run input is a flat top-level array. A reference
     * that goes deeper is therefore reported as a `json` field.
     *
     * @return array<int, array<string, mixed>>
     */
    private function derive(Workflow $workflow): array
    {
        $graph = $workflow->currentVersion?->graph ?? $workflow->draftGraph();

        $fields = [];

        foreach ($graph['nodes'] ?? [] as $node) {
            foreach (TemplatePaths::referencedIn($node['config'] ?? []) as $path) {
                if (! str_starts_with($path, 'input.')) {
                    continue;
                }

                $remainder = substr($path, strlen('input.'));
                $key = explode('.', $remainder)[0];

                if ($key === '') {
                    continue;
                }

                $isNested = str_contains($remainder, '.');

                // A key seen once as `input.a` and once as `input.a.b` is an
                // object — the nested reading wins.
                $fields[$key] = [
                    ...$this->normalizeField([
                        'key' => $key,
                        'label' => $key,
                        'type' => $isNested ? InterfaceFieldType::Json->value : InterfaceFieldType::String->value,
                        // Derived fields are never required: nothing declared
                        // them, and refusing a run over a guess would be worse
                        // than letting the template resolve to null.
                        'required' => false,
                    ]),
                    'type' => $isNested || (($fields[$key]['type'] ?? null) === InterfaceFieldType::Json->value)
                        ? InterfaceFieldType::Json->value
                        : InterfaceFieldType::String->value,
                ];
            }
        }

        ksort($fields);

        return array_values($fields);
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<string, mixed>
     */
    private function normalizeField(array $field): array
    {
        return [
            'key' => (string) $field['key'],
            'label' => $field['label'] ?? $field['key'],
            'type' => InterfaceFieldType::tryFrom($field['type'] ?? '')?->value ?? InterfaceFieldType::String->value,
            'required' => (bool) ($field['required'] ?? false),
            'help' => $field['help'] ?? null,
            'default' => $field['default'] ?? null,
            'options' => array_values($field['options'] ?? []),
        ];
    }
}
