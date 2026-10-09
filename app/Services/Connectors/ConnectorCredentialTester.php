<?php

namespace App\Services\Connectors;

use App\Exceptions\ConnectorException;
use App\Models\Connectors\ConnectorCredential;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Checks a connection really works: it gets a usable access token
 * (refreshing an OAuth token that needs it, exactly as a run would) and
 * then asks the provider who that token belongs to. A connector with no
 * known identity endpoint is judged on the token alone.
 */
class ConnectorCredentialTester
{
    private const int TIMEOUT_SECONDS = 10;

    public function __construct(private readonly ConnectorTokens $tokens) {}

    /**
     * Runs the check and records it on the credential, so the result (and
     * whose account it is) outlives the request that asked.
     *
     * @return array{ok: bool, message: string, account: ?string, tested_at: string}
     */
    public function test(ConnectorCredential $credential): array
    {
        $result = $this->check($credential);

        $credential->forceFill([
            'last_tested_at' => $result['tested_at'],
            'last_test_ok' => $result['ok'],
            'last_test_message' => Str::limit($result['message'], 497),
            'account_label' => $result['account'] ?? $credential->account_label,
        ])->saveQuietly();

        return $result;
    }

    /**
     * @return array{ok: bool, message: string, account: ?string, tested_at: string}
     */
    private function check(ConnectorCredential $credential): array
    {
        $credential->loadMissing('connector');
        $appName = $credential->connector?->name ?? 'This app';

        try {
            $token = $this->tokens->accessToken($credential, markUsed: false);
        } catch (ConnectorException $e) {
            return $this->result(false, $e->getMessage());
        }

        try {
            return $this->checkIdentity($credential->connector?->key ?? '', $token, $appName);
        } catch (ConnectionException) {
            return $this->result(false, "Couldn't reach {$appName}. Try again in a moment.");
        }
    }

    /**
     * @return array{ok: bool, message: string, account: ?string, tested_at: string}
     *
     * @throws ConnectionException
     */
    private function checkIdentity(string $connectorKey, string $token, string $appName): array
    {
        $request = Http::withToken($token)->acceptJson()->timeout(self::TIMEOUT_SECONDS);

        if ($connectorKey === 'github') {
            $response = $request->get('https://api.github.com/user');

            return $this->fromResponse($response, $appName, $response->json('login'));
        }

        if ($connectorKey === 'slack') {
            $response = $request->post('https://slack.com/api/auth.test');

            if ($response->successful() && $response->json('ok') !== true) {
                return $this->rejected($appName);
            }

            $account = collect([$response->json('user'), $response->json('team')])->filter()->implode(' @ ');

            return $this->fromResponse($response, $appName, $account !== '' ? $account : null);
        }

        if ($connectorKey === 'gmail' || str_starts_with($connectorKey, 'google_')) {
            $response = Http::acceptJson()->timeout(self::TIMEOUT_SECONDS)
                ->get('https://www.googleapis.com/oauth2/v3/tokeninfo', ['access_token' => $token]);

            if ($response->status() === 400) {
                return $this->rejected($appName);
            }

            return $this->fromResponse($response, $appName, $response->json('email'));
        }

        if ($connectorKey === 'outlook') {
            $response = $request->get('https://graph.microsoft.com/v1.0/me');

            return $this->fromResponse($response, $appName, $response->json('mail') ?? $response->json('userPrincipalName'));
        }

        return $this->result(true, "{$appName} has a valid access token.");
    }

    /**
     * @return array{ok: bool, message: string, account: ?string, tested_at: string}
     */
    private function fromResponse(Response $response, string $appName, ?string $account): array
    {
        if ($response->status() === 401 || $response->status() === 403) {
            return $this->rejected($appName);
        }

        if (! $response->successful()) {
            return $this->result(false, "{$appName} didn't respond as expected (HTTP {$response->status()}). Try again in a moment.");
        }

        return $this->result(true, "Connected to {$appName}.", $account);
    }

    /**
     * @return array{ok: bool, message: string, account: ?string, tested_at: string}
     */
    private function rejected(string $appName): array
    {
        return $this->result(false, "{$appName} rejected this account's access. Reconnect it to keep it working.");
    }

    /**
     * @return array{ok: bool, message: string, account: ?string, tested_at: string}
     */
    private function result(bool $ok, string $message, ?string $account = null): array
    {
        return [
            'ok' => $ok,
            'message' => $message,
            'account' => $account,
            'tested_at' => now()->toIso8601String(),
        ];
    }
}
