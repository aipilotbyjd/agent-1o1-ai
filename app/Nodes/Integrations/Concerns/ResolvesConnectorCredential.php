<?php

namespace App\Nodes\Integrations\Concerns;

use App\Enums\Connectors\ConnectorCredentialScope;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Runs\Run;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Shared by `AbstractGitHubNode`/`AbstractSlackNode`/`AbstractGmailNode` to
 * turn a step's `config` into a usable access token, tenant-scoped by the
 * executing `Run`. A `ConnectorCredential` is always looked up by
 * `workspace_id` first — a step can never reach a credential belonging to
 * another workspace by guessing its id — with a fallback to a plain
 * `access_token` config field for backward compatibility with nodes
 * configured before `ConnectorCredential` existed (docs/PLAN.md Phase 6).
 *
 * With neither pinned, falls back to a *default* credential — Gumloop's
 * "Personal Default"/"if you only have one account connected, it's
 * automatically your default" (see
 * docs/gumloop/output/raw/core-concepts/credentials.md). The running
 * user's (`Run::triggered_by`) personal default takes priority over the
 * workspace's team default, matching the doc's "everyone uses their own
 * account by default" rule of thumb.
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
            return $this->tokenFrom($this->findCredential($run, (int) $credentialId));
        }

        $token = $config['access_token'] ?? null;

        if (is_string($token) && $token !== '') {
            return $token;
        }

        return $this->tokenFrom($this->defaultCredential($run));
    }

    private function findCredential(Run $run, int $credentialId): ConnectorCredential
    {
        $credential = ConnectorCredential::query()
            ->where('workspace_id', $run->workspace_id)
            ->find($credentialId);

        if ($credential === null) {
            throw new RuntimeException("Connector credential [{$credentialId}] not found in this workspace.");
        }

        return $credential;
    }

    /**
     * The credential a node gets when nothing pins one: the running user's
     * personal default (or their sole personal credential, if they have
     * exactly one and none is marked default) for this connector, else the
     * workspace's team default (or its sole team credential).
     */
    private function defaultCredential(Run $run): ConnectorCredential
    {
        $connector = Connector::where('key', $this->category())->first();
        $credential = null;

        if ($connector !== null && $run->triggered_by !== null) {
            $credential = $this->preferredOf(
                ConnectorCredential::query()
                    ->where('workspace_id', $run->workspace_id)
                    ->where('connector_id', $connector->id)
                    ->where('scope', ConnectorCredentialScope::Personal->value)
                    ->where('created_by', $run->triggered_by)
                    ->get(),
            );
        }

        $credential ??= $connector === null ? null : $this->preferredOf(
            ConnectorCredential::query()
                ->where('workspace_id', $run->workspace_id)
                ->where('connector_id', $connector->id)
                ->where('scope', ConnectorCredentialScope::Team->value)
                ->get(),
        );

        if ($credential === null) {
            throw new RuntimeException(ucfirst($this->category()).' access_token or credential_id is required.');
        }

        return $credential;
    }

    /**
     * @param  Collection<int, ConnectorCredential>  $candidates
     */
    private function preferredOf($candidates): ?ConnectorCredential
    {
        if ($candidates->isEmpty()) {
            return null;
        }

        return $candidates->firstWhere('is_default', true) ?? ($candidates->count() === 1 ? $candidates->first() : null);
    }

    private function tokenFrom(ConnectorCredential $credential): string
    {
        if ($credential->isExpired()) {
            throw new RuntimeException("Connector credential [{$credential->id}] has expired.");
        }

        $token = $credential->data['access_token'] ?? $credential->data['api_key'] ?? null;

        if (! is_string($token) || $token === '') {
            throw new RuntimeException("Connector credential [{$credential->id}] has no usable access token.");
        }

        $credential->forceFill(['last_used_at' => now()])->saveQuietly();

        return $token;
    }
}
