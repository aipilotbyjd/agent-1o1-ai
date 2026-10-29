<?php

namespace App\Services\Connectors;

use App\Exceptions\ConnectorException;
use App\Models\Connectors\ConnectorCredential;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

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

        // These log in with several fields, so there is no one token to check.
        if (in_array($credential->connector?->key, ['telegram', 'discord', 'twilio', 'smtp'], true)) {
            try {
                return $this->checkManual($credential->connector->key, (array) $credential->data, $appName);
            } catch (ConnectionException) {
                return $this->result(false, "Couldn't reach {$appName}. Try again in a moment.");
            }
        }

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

        if ($connectorKey === 'notion') {
            $response = $request->withHeaders(['Notion-Version' => '2022-06-28'])->get('https://api.notion.com/v1/users/me');
            $account = $response->json('bot.workspace_name') ?? $response->json('name');

            return $this->fromResponse($response, $appName, is_string($account) ? $account : null);
        }

        if ($connectorKey === 'linear') {
            $response = $request->post('https://api.linear.app/graphql', ['query' => '{ viewer { name email } }']);

            // Linear answers a rejected token with 200 and `errors` as often as with 401.
            if ($response->successful() && ! empty($response->json('errors'))) {
                return $this->rejected($appName);
            }

            return $this->fromResponse($response, $appName, $response->json('data.viewer.email') ?? $response->json('data.viewer.name'));
        }

        if ($connectorKey === 'jira') {
            $response = $request->get('https://api.atlassian.com/oauth/token/accessible-resources');
            $sites = collect((array) $response->json())->pluck('name')->filter()->implode(', ');

            if ($response->successful() && $sites === '') {
                return $this->result(false, 'This Atlassian account has no Jira site. Connect one that can open a site.');
            }

            return $this->fromResponse($response, $appName, $sites !== '' ? $sites : null);
        }

        if ($connectorKey === 'outlook') {
            $response = $request->get('https://graph.microsoft.com/v1.0/me');

            return $this->fromResponse($response, $appName, $response->json('mail') ?? $response->json('userPrincipalName'));
        }

        return $this->result(true, "{$appName} has a valid access token.");
    }

    /**
     * The check for an app connected with its own keys: ask the app who the
     * keys belong to (a mail server is only reached, and told who is logging in).
     *
     * @param  array<string, mixed>  $data
     * @return array{ok: bool, message: string, account: ?string, tested_at: string}
     *
     * @throws ConnectionException
     */
    private function checkManual(string $connectorKey, array $data, string $appName): array
    {
        $token = (string) ($data['api_key'] ?? '');

        if ($connectorKey === 'telegram') {
            $response = Http::acceptJson()->timeout(self::TIMEOUT_SECONDS)->get('https://api.telegram.org/bot'.$token.'/getMe');

            if ($response->status() === 401 || $response->status() === 404 || ($response->successful() && $response->json('ok') !== true)) {
                return $this->rejected($appName);
            }

            $username = $response->json('result.username');

            return $this->fromResponse($response, $appName, is_string($username) ? '@'.$username : null);
        }

        if ($connectorKey === 'discord') {
            $response = Http::withHeaders(['Authorization' => 'Bot '.$token])->acceptJson()->timeout(self::TIMEOUT_SECONDS)->get('https://discord.com/api/v10/users/@me');

            return $this->fromResponse($response, $appName, $response->json('username'));
        }

        if ($connectorKey === 'twilio') {
            $sid = (string) ($data['account_sid'] ?? '');

            if ($sid === '' || $token === '') {
                return $this->result(false, 'Twilio needs both the account SID and the auth token.');
            }

            $response = Http::withBasicAuth($sid, $token)->acceptJson()->timeout(self::TIMEOUT_SECONDS)->get("https://api.twilio.com/2010-04-01/Accounts/{$sid}.json");

            return $this->fromResponse($response, $appName, $response->json('friendly_name'));
        }

        return $this->checkSmtp($data, $appName);
    }

    /**
     * Opens a connection to the mail server and logs in, without sending anything.
     *
     * @param  array<string, mixed>  $data
     * @return array{ok: bool, message: string, account: ?string, tested_at: string}
     */
    private function checkSmtp(array $data, string $appName): array
    {
        try {
            app(SmtpMailer::class)->verify($data);
        } catch (Throwable $e) {
            return $this->result(false, 'The mail server did not accept these settings: '.Str::limit($e->getMessage(), 200));
        }

        return $this->result(true, "Connected to {$appName}.", (string) ($data['from_address'] ?? '') ?: null);
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
