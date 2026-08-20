<?php

namespace App\Services\Secrets;

use App\Models\Secrets\Secret;
use App\Services\Workflows\TemplatePaths;

/**
 * Loads the workspace secrets a single node config references, so
 * `WorkflowRunner` can resolve `{{ secrets.API_TOKEN }}` at run time without
 * the value ever being stored in the graph.
 *
 * Only the keys that config actually mentions are read — a workspace's other
 * secrets never enter the run's memory — and the resolved map is merged into
 * the templating context alone, never into the `$context` array handed to
 * `NodeContract::execute()`, so a node can't walk it looking for values it
 * wasn't given.
 *
 * A referenced key that doesn't exist resolves to `null`, exactly like any
 * other unknown template path — `DryRunner` is where a typo gets surfaced.
 */
final class SecretResolver
{
    /**
     * Template prefixes that address this store. Both map to the same rows.
     */
    private const array PREFIXES = ['secrets', 'vars'];

    /**
     * @param  array<string, mixed>  $config
     */
    public function forConfig(int $workspaceId, array $config): ResolvedSecrets
    {
        $keys = $this->referencedKeys($config);

        if ($keys === []) {
            return new ResolvedSecrets;
        }

        $secrets = Secret::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('key', $keys)
            ->get();

        if ($secrets->isEmpty()) {
            return new ResolvedSecrets;
        }

        $secrets->toQuery()->toBase()->update(['last_used_at' => now()]);

        return new ResolvedSecrets(
            values: $secrets->pluck('value', 'key')->all(),
            sensitiveKeys: $secrets->where('is_secret', true)->pluck('key')->all(),
        );
    }

    /**
     * The secret keys a config tree references, from `{{ secrets.X }}` /
     * `{{ vars.X }}` expressions. A deeper path (`{{ secrets.X.y }}`) still
     * resolves to key `X` — the extra segments simply won't resolve, since a
     * secret is one opaque string.
     *
     * @param  array<string, mixed>  $config
     * @return array<int, string>
     */
    private function referencedKeys(array $config): array
    {
        $keys = [];

        foreach (TemplatePaths::referencedIn($config) as $path) {
            $segments = explode('.', $path);

            if (count($segments) >= 2 && in_array($segments[0], self::PREFIXES, true)) {
                $keys[] = $segments[1];
            }
        }

        return array_values(array_unique($keys));
    }
}
