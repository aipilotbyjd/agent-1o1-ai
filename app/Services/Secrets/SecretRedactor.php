<?php

namespace App\Services\Secrets;

/**
 * Scrubs the plaintext of the secrets a step used out of anything that gets
 * persisted — a node's output and its error message. Without this a node that
 * echoes its own config back (an HTTP node returning the request it made, an
 * API returning the token it was called with, an exception quoting the URL)
 * would write the secret straight into `node_runs`, where every run-viewer in
 * the workspace can read it.
 *
 * Complements `StepFailureHandler`'s pattern-based redaction, which catches
 * key-shaped strings the engine never saw stored.
 */
final class SecretRedactor
{
    public const string PLACEHOLDER = '[redacted]';

    /**
     * @param  array<int, string>  $values
     */
    public function redact(mixed $subject, array $values): mixed
    {
        if ($values === []) {
            return $subject;
        }

        if (is_array($subject)) {
            return array_map(fn (mixed $item): mixed => $this->redact($item, $values), $subject);
        }

        if (! is_string($subject)) {
            return $subject;
        }

        return str_replace($values, self::PLACEHOLDER, $subject);
    }

    /**
     * @param  array<int, string>  $values
     */
    public function redactString(string $subject, array $values): string
    {
        return $values === [] ? $subject : str_replace($values, self::PLACEHOLDER, $subject);
    }
}
