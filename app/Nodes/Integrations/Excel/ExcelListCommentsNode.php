<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ExcelListCommentsNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_list_comments';
    }

    public function name(): string
    {
        return 'Excel: List Comments';
    }

    public function description(): string
    {
        return 'Lists the comments in a workbook.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['workbook_id'],
            'properties' => [
                ...$this->workbookFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $comments = array_map(fn (array $comment): array => ['id' => $comment['id'] ?? null, 'content' => $comment['content'] ?? null, 'content_type' => $comment['contentType'] ?? null], array_values(array_filter((array) ($this->graph($run, $config, 'GET', $this->workbook($config).'/comments')['value'] ?? []), 'is_array')));

        return ['comments' => $comments, 'count' => count($comments)];
    }
}
