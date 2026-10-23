<?php

namespace App\Services\SuperAdmin\Catalog;

use App\Models\Connectors\Connector;
use App\Models\Nodes\CustomNode;
use App\Models\SuperAdmin\SuperAdmin;
use App\Services\SuperAdmin\Audit\SuperAdminAuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Switching catalog items on and off: connectors, custom nodes, and the
 * visibility of templates. Nothing is deleted, so anything switched off can
 * be switched back on, and every change is audited with the stated reason.
 *
 * (The model catalog is deliberately not here: it is operator-fixed.)
 */
class SuperAdminCatalogService
{
    public function __construct(private readonly SuperAdminAuditLogger $audit) {}

    public function setConnectorActive(SuperAdmin $actor, Connector $connector, bool $active, string $reason): Connector
    {
        return $this->setActive($actor, $connector, 'catalog.connector', $active, $reason);
    }

    public function setCustomNodeActive(SuperAdmin $actor, CustomNode $node, bool $active, string $reason): CustomNode
    {
        return $this->setActive($actor, $node, 'catalog.node', $active, $reason);
    }

    /**
     * Publishing is limited to platform templates (no workspace): a workspace's
     * private template is not the platform's to make public. Unpublishing is
     * allowed for any.
     */
    public function setTemplateVisibility(SuperAdmin $actor, Model $template, string $visibility, string $reason): Model
    {
        abort_if(
            $visibility === 'public' && $template->workspace_id !== null,
            409,
            'Only platform templates can be published from here.',
        );

        if ($template->visibility === $visibility) {
            return $template;
        }

        DB::transaction(function () use ($actor, $template, $visibility, $reason): void {
            $previous = $template->visibility;

            $template->forceFill(['visibility' => $visibility])->save();

            $this->audit->record(
                $actor,
                $visibility === 'public' ? 'catalog.template.published' : 'catalog.template.unpublished',
                $template,
                ['visibility' => $previous],
                ['visibility' => $visibility],
                $reason,
            );
        });

        return $template;
    }

    private function setActive(SuperAdmin $actor, Model $item, string $actionPrefix, bool $active, string $reason): Model
    {
        if ((bool) $item->is_active === $active) {
            return $item;
        }

        DB::transaction(function () use ($actor, $item, $actionPrefix, $active, $reason): void {
            $item->forceFill(['is_active' => $active])->save();

            $this->audit->record(
                $actor,
                $actionPrefix.($active ? '.enabled' : '.disabled'),
                $item,
                ['is_active' => ! $active],
                ['is_active' => $active],
                $reason,
            );
        });

        return $item;
    }
}
