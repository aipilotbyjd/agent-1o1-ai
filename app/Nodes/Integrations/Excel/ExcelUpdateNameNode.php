<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ExcelUpdateNameNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_update_name';
    }

    public function name(): string
    {
        return 'Excel: Update Named Item';
    }

    public function description(): string
    {
        return 'Changes a named item\'s comment or visibility.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['workbook_id', 'name'],
            'properties' => [
                ...$this->workbookFields(),
                'name' => Field::dynamic('Name', 'excel.names', 'Pick a named item, or enter its name.', 'TaxRate', dependsOn: ['workbook_id'], uses: ['drive_id']),
                'comment' => Field::text('Comment'),
                'visible' => Field::boolean('Visible', null, true),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = array_filter(['comment' => filled($config['comment'] ?? null) ? (string) $config['comment'] : null, 'visible' => array_key_exists('visible', $config) ? (bool) $config['visible'] : null], fn (mixed $value): bool => $value !== null);

        if ($body === []) {
            throw new InvalidArgumentException('Give a comment or a visibility.');
        }

        return $this->shapeName($this->graph($run, $config, 'PATCH', $this->workbook($config).'/names/'.$this->nameSegment($config, 'name', 'The name'), [], $body));
    }
}
