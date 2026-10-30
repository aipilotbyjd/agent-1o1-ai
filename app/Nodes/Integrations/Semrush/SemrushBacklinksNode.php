<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushBacklinksNode extends AbstractSemrushReportNode
{
    public function type(): string
    {
        return 'semrush_backlinks';
    }

    public function name(): string
    {
        return 'Semrush: Backlinks';
    }

    public function description(): string
    {
        return 'The backlinks of a domain or URL.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['target', 'target_type'],
            'properties' => [
                ...$this->credentialFields(),
                'target' => Field::text('Target', 'A root domain, a subdomain or a URL.', 'example.com'),
                'target_type' => $this->targetTypeField(),
                'limit' => $this->limitField(),
                'offset' => $this->offsetField(),
                'sort' => $this->sortField(['page_ascore_asc', 'page_ascore_desc', 'last_seen_asc', 'last_seen_desc', 'first_seen_asc', 'first_seen_desc']),
                'filter' => $this->filterField(),
                'columns' => $this->columnsField(['page_ascore', 'response_code', 'source_size', 'external_num', 'internal_num', 'redirect_url', 'source_url', 'source_title', 'image_url', 'target_url', 'target_title', 'anchor', 'image_alt', 'last_seen', 'first_seen', 'nofollow', 'form', 'frame', 'image', 'sitewide', 'newlink', 'lostlink']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->seoReport($run, $config, 'backlinks', 'page_ascore,source_title,source_url,target_url,anchor,external_num,internal_num,first_seen,last_seen,nofollow,newlink,lostlink', ['target' => $this->requiredString($config, 'target', 'The target')], '/analytics/v1/');
    }
}
