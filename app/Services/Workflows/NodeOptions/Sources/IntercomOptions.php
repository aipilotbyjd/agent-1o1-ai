<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Exceptions\ConnectorException;
use App\Nodes\Integrations\Intercom\AbstractIntercomNode;
use App\Services\Workflows\NodeOptions\NodeOption;
use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use Illuminate\Http\Client\PendingRequest;
use InvalidArgumentException;

/**
 * The teammates, teams, tags, ticket types and ticket states of an Intercom
 * workspace. Intercom answers each list whole, so a search filters what came back.
 */
class IntercomOptions extends HttpOptionsSource
{
    public function sources(): array
    {
        return ['intercom.admins', 'intercom.teams', 'intercom.tags', 'intercom.ticket_types', 'intercom.ticket_states'];
    }

    protected function appName(): string
    {
        return 'Intercom';
    }

    protected function http(NodeOptionsQuery $query): PendingRequest
    {
        try {
            return AbstractIntercomNode::client($query->credentialData)->timeout(self::TIMEOUT_SECONDS);
        } catch (InvalidArgumentException $exception) {
            throw new ConnectorException($exception->getMessage());
        }
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        [$path, $key, $label] = match ($source) {
            'intercom.admins' => ['/admins', 'admins', 'name'],
            'intercom.teams' => ['/teams', 'teams', 'name'],
            'intercom.ticket_types' => ['/ticket_types', 'data', 'name'],
            'intercom.ticket_states' => ['/ticket_states', 'data', 'internal_label'],
            default => ['/tags', 'data', 'name'],
        };

        $rows = collect($this->getJson($query, AbstractIntercomNode::apiRoot($query->credentialData).$path)[$key] ?? [])
            ->filter(fn (mixed $row): bool => is_array($row) && isset($row['id']) && $query->matches((string) ($row[$label] ?? ''), (string) ($row['email'] ?? '')));

        return new NodeOptionsPage($rows->map(fn (array $row): NodeOption => new NodeOption((string) $row['id'], (string) ($row[$label] ?? $row['id']), $row['email'] ?? null)));
    }
}
