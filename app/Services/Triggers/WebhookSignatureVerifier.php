<?php

namespace App\Services\Triggers;

use App\Models\Triggers\Trigger;
use Illuminate\Http\Request;

/**
 * Per-`trigger_presets.signature_scheme` HMAC verification. Verifies against
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

        // Signing is opt-in per preset — a trigger with no scheme configured
        // has nothing to verify against.
        if ($scheme === null) {
            return true;
        }

        $secret = $trigger->signing_secret;

        if (blank($secret)) {
            return false;
        }

        return match ($scheme) {
            'github' => $this->verifyGithub($request, $rawBody, $secret),
            'stripe' => $this->verifyStripe($request, $rawBody, $secret),
            'slack' => $this->verifySlack($request, $rawBody, $secret),
            default => false,
        };
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
