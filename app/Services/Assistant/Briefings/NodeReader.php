<?php

namespace App\Services\Assistant\Briefings;

use App\Contracts\DeclaresEffect;
use App\Enums\Agents\ActionEffect;
use App\Models\Assistant\Assistant;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Runs\Run;
use App\Services\Workflows\NodeRegistry;
use LogicException;

/**
 * Runs integration nodes for background reports — and only nodes that
 * declare themselves read-only. A report can never send, change or delete
 * anything, even if a collector asked for the wrong node.
 */
class NodeReader
{
    public function __construct(private readonly NodeRegistry $nodes) {}

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function read(string $type, Assistant $assistant, ConnectorCredential $credential, array $config = []): array
    {
        $node = $this->nodes->resolve($type);

        if (! $node instanceof DeclaresEffect || $node->effect($config) !== ActionEffect::Read) {
            throw new LogicException("[{$type}] is not a read-only node and can't be used in a report.");
        }

        $run = new Run;
        $run->forceFill(['workspace_id' => $assistant->workspace_id, 'triggered_by' => $assistant->user_id]);
        $run->setRelation('workspace', $assistant->workspace);

        return $node->execute($run, [...$config, 'credential_id' => $credential->id], ['input' => [], 'nodes' => []]);
    }
}
