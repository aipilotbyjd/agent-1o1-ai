<?php

namespace App\Services\Triggers;

use App\Models\Triggers\Trigger;
use Illuminate\Http\Request;

/**
 * Per-`trigger_presets.signature_scheme` HMAC verification (plus a generic
 * `X-Signature-256` scheme for triggers that have a signing secret but no
 * provider preset). Verifies against
 * the raw request body (`payload_snippet`, not the decoded `payload`) — a
 * provider's signature is computed over exact bytes, and re-encoding JSON
 * before hashing would fail to reproduce it.
 */
class WebhookSignatureVerifier
{
    private const TIMESTAMP_TOLERANCE_SECONDS = 300;

    public function verify(Trigger $trigger, Request $request, string $rawBody): bool
    {
        $scheme = $trigger->preset?->signature_scheme;
        $secret = $trigger->signing_secret;

        // A preset names the provider's scheme. With none, the trigger is
        // unsigned unless its owner set a signing secret — and then the
        // secret must actually be enforced, not silently ignored, so it falls
        // back to the generic scheme below.
        if ($scheme === null && blank($secret)) {
            return true;
        }

        if (blank($secret)) {
            return false;
        }

        return match ($scheme ?? 'generic') {
            'generic' => $this->verifyGeneric($request, $rawBody, $secret),
            'github' => $this->verifyGithub($request, $rawBody, $secret),
            'stripe' => $this->verifyStripe($request, $rawBody, $secret),
            'slack' => $this->verifySlack($request, $rawBody, $secret),
            default => false,
        };
    }

    /**
     * For senders with no provider scheme: `X-Signature-256: sha256=<hex>`,
     * an HMAC-SHA256 of the raw body keyed with the trigger's signing secret.
     */
    private function verifyGeneric(Request $request, string $rawBody, string $secret): bool
    {
        $signature = (string) $request->header('X-Signature-256');

        if ($signature === '') {
            return false;
        }

        return hash_equals('sha256='.hash_hmac('sha256', $rawBody, $secret), $signature);
    }

    private function verifyGithub(Request $request, string $rawBody, string $secret): bool
    {
        $signature = (string) $request->header('X-Hub-Signature-256');

        if ($signature === '') {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expected, $signature);
    }

    private function verifyStripe(Request $request, string $rawBody, string $secret): bool
    {
        $parts = $this->parseHeaderPairs((string) $request->header('Stripe-Signature'));
        $timestamp = $parts['t'][0] ?? null;
        $signatures = $parts['v1'] ?? [];

        if ($timestamp === null || $signatures === [] || $this->isStale((int) $timestamp)) {
            return false;
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$rawBody}", $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, (string) $signature)) {
                return true;
            }
        }

        return false;
    }

    private function verifySlack(Request $request, string $rawBody, string $secret): bool
    {
        $timestamp = (string) $request->header('X-Slack-Request-Timestamp');
        $signature = (string) $request->header('X-Slack-Signature');

        if ($timestamp === '' || $signature === '' || $this->isStale((int) $timestamp)) {
            return false;
        }

        $expected = 'v0='.hash_hmac('sha256', "v0:{$timestamp}:{$rawBody}", $secret);

        return hash_equals($expected, $signature);
    }

    /**
     * Parses a `key=value,key=value` header (Stripe's `Stripe-Signature` shape)
     * into `key => [values]`, since `v1` can repeat for secret rotation.
     *
     * @return array<string, array<int, string>>
     */
    private function parseHeaderPairs(string $header): array
    {
        $parts = [];

        foreach (explode(',', $header) as $pair) {
            [$key, $value] = array_pad(explode('=', $pair, 2), 2, null);

            if ($key !== null && $value !== null) {
                $parts[$key][] = $value;
            }
        }

        return $parts;
    }

    private function isStale(int $timestamp): bool
    {
        return abs(time() - $timestamp) > self::TIMESTAMP_TOLERANCE_SECONDS;
    }
}
