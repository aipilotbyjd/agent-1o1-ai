<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SemrushGetApiUnitsNode extends AbstractSemrushNode
{
    public function type(): string
    {
        return 'semrush_get_api_units';
    }

    public function name(): string
    {
        return 'Semrush: Get API Units';
    }

    public function description(): string
    {
        return 'Reads how many API units the account has left. Free.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $response = $this->unitsResponse($run, $config);

        $this->assertOk($response);

        return ['api_units' => (int) preg_replace('/\D/', '', $response->body())];
    }

    /**
     * The balance lives on Semrush's main site, not on the API host.
     *
     * @param  array<string, mixed>  $config
     */
    private function unitsResponse(Run $run, array $config): Response
    {
        try {
            return Http::timeout(30)->get('https://www.semrush.com/users/countapiunits.html', ['key' => $this->resolveAccessToken($run, $config)]);
        } catch (ConnectionException) {
            throw new RuntimeException("Couldn't reach Semrush. Try again in a moment.");
        }
    }
}
