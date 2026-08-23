<?php

namespace App\Nodes\Integrations\GoogleSheets;

use App\Contracts\NodeContract;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ResolvesConnectorCredential;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Shared request/error-handling for the Google Sheets node family. Resolves
 * the access token via `ResolvesConnectorCredential` — the same
 * workspace-scoped `ConnectorCredential` (`gmail` connector, whose OAuth
 * scopes cover every Google product node) used by `AbstractGmailNode`.
 */
abstract class AbstractGoogleSheetsNode implements NodeContract
{
    use ResolvesConnectorCredential;

    private const string BASE_URL = 'https://sheets.googleapis.com/v4';

    public function category(): string
    {
        return 'google_sheets';
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
    protected function put(Run $run, string $endpoint, array $config, array $params = []): array
    {
        return $this->call($run, 'put', $endpoint, $config, $params);
    }

    /**
     * Sheets signals failure via a normal HTTP status code, with the error
     * detail under `error.message` in the JSON body, same as Gmail/Drive.
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
            'put' => $request->asJson()->put(self::BASE_URL.$endpoint, $params),
            default => $request->asJson()->post(self::BASE_URL.$endpoint, $params),
        };

        if ($response->failed()) {
            $message = $response->json('error.message') ?? $response->body();

            throw new RuntimeException("Google Sheets API error [{$endpoint}]: {$message}");
        }

        return $response->json() ?? [];
    }
}
