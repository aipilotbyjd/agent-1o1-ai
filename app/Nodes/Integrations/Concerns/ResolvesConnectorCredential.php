<?php

namespace App\Nodes\Integrations\Concerns;

use App\Models\Connectors\ConnectorCredential;
use App\Models\Runs\Run;
use RuntimeException;

/**
 * Shared by `AbstractGitHubNode`/`AbstractSlackNode`/`AbstractGmailNode` to
 * turn a step's `config` into a usable access token, tenant-scoped by the
 * executing `Run`. A `ConnectorCredential` is always looked up by
 * `workspace_id` first — a step can never reach a credential belonging to
 * another workspace by guessing its id — with a fallback to a plain
 * `access_token` config field for backward compatibility with nodes
 * configured before `ConnectorCredential` existed (docs/PLAN.md Phase 6).
 */
trait ResolvesConnectorCredential
{
    /**
     * @param  array<string, mixed>  $config
     */
    protected function resolveAccessToken(Run $run, array $config): string
    {
        $credentialId = $config['credential_id'] ?? null;

        if ($credentialId !== null) {
            return $this->accessTokenFromCredential($run, (int) $credentialId);
        }

        $token = $config['access_token'] ?? null;

        if (! is_string($token) || $token === '') {
            throw new RuntimeException(ucfirst($this->category()).' access_token or credential_id is required.');
        }

        return $token;
    }

    private function accessTokenFromCredential(Run $run, int $credentialId): string
    {
        $credential = ConnectorCredential::query()
            ->where('workspace_id', $run->workspace_id)
            ->find($credentialId);

        if ($credential === null) {
            throw new RuntimeException("Connector credential [{$credentialId}] not found in this workspace.");
        }

        if ($credential->isExpired()) {
            throw new RuntimeException("Connector credential [{$credentialId}] has expired.");
        }

        $token = $credential->data['access_token'] ?? $credential->data['api_key'] ?? null;

        if (! is_string($token) || $token === '') {
            throw new RuntimeException("Connector credential [{$credentialId}] has no usable access token.");
        }

        $credential->forceFill(['last_used_at' => now()])->saveQuietly();

        return $token;
    }
}
