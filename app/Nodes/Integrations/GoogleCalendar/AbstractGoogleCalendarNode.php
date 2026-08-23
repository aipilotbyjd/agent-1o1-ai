<?php

namespace App\Nodes\Integrations\GoogleCalendar;

use App\Contracts\NodeContract;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ResolvesConnectorCredential;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Shared request/error-handling for the Google Calendar node family.
 * Resolves the access token via `ResolvesConnectorCredential` — the same
 * workspace-scoped `ConnectorCredential` (`gmail` connector, whose OAuth
 * scopes cover every Google product node) used by `AbstractGmailNode`.
 */
abstract class AbstractGoogleCalendarNode implements NodeContract
{
    use ResolvesConnectorCredential;

    private const string BASE_URL = 'https://www.googleapis.com/calendar/v3';

    public function category(): string
    {
        return 'google_calendar';
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function get(Run $run, string $endpoint, array $config, array $params = []): array
    {
        return $this->call($run, 'get', $endpoint, $config, $params);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function post(Run $run, string $endpoint, array $config, array $params = []): array
    {
        return $this->call($run, 'post', $endpoint, $config, $params);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function delete(Run $run, string $endpoint, array $config, array $params = []): array
    {
        return $this->call($run, 'delete', $endpoint, $config, $params);
    }

    /**
     * Calendar signals failure via a normal HTTP status code, with the error
     * detail under `error.message` in the JSON body, same as Gmail/Drive. A
     * successful delete returns an empty body, which `json()` decodes to
     * `null`.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function call(Run $run, string $method, string $endpoint, array $config, array $params): array
    {
        $token = $this->resolveAccessToken($run, $config);

        $request = Http::withToken($token);
        $response = match ($method) {
            'get' => $request->get(self::BASE_URL.$endpoint, $params),
            'delete' => $request->delete(self::BASE_URL.$endpoint, $params),
            default => $request->asJson()->post(self::BASE_URL.$endpoint, $params),
        };

        if ($response->failed()) {
            $message = $response->json('error.message') ?? $response->body();

            throw new RuntimeException("Google Calendar API error [{$endpoint}]: {$message}");
        }

        return $response->json() ?? [];
    }
}
