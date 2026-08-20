<?php

namespace App\Services\Secrets;

/**
 * The secrets/variables one node's config actually referenced, resolved for a
 * single step — see `SecretResolver`.
 */
final readonly class ResolvedSecrets
{
    /**
     * @param  array<string, string>  $values  key => plaintext value
     * @param  array<int, string>  $sensitiveKeys  the subset stored with `is_secret`
     */
    public function __construct(
        public array $values = [],
        public array $sensitiveKeys = [],
    ) {}

    /**
     * The templating-context fragment merged in for this step. `secrets` and
     * `vars` are two names for the same store, so an author can use whichever
     * reads better at the call site.
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return ['secrets' => $this->values, 'vars' => $this->values];
    }

    /**
     * Plaintext values that must not survive into a persisted node output or
     * error message. Values shorter than 4 characters are left out: redacting
     * a one- or two-character string would shred unrelated output for no real
     * protection.
     *
     * @return array<int, string>
     */
    public function sensitiveValues(): array
    {
        return array_values(array_filter(
            array_map(fn (string $key): string => $this->values[$key] ?? '', $this->sensitiveKeys),
            fn (string $value): bool => mb_strlen($value) >= 4,
        ));
    }
}
