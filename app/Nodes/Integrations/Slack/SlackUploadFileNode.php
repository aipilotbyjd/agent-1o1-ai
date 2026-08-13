<?php

namespace App\Nodes\Integrations\Slack;

use App\Models\Runs\Run;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Ported against Slack's classic `files.upload` endpoint (multipart, one
 * request) rather than the newer three-step external-upload flow, since no
 * file storage/`Artifact` abstraction exists yet to source a real file from
 * (docs/PLAN.md's `Artifact` entry is still a plan) — `content` is raw text
 * passed straight through as the file body.
 */
class SlackUploadFileNode extends AbstractSlackNode
{
    public function type(): string
    {
        return 'slack_upload_file';
    }

    public function name(): string
    {
        return 'Slack: Upload File';
    }

    public function description(): string
    {
        return 'Uploads a text file to one or more Slack channels.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['channels', 'content'],
            'properties' => [
                'access_token' => ['type' => 'string'],
                'credential_id' => ['type' => 'integer'],
                'channels' => ['type' => 'string'],
                'content' => ['type' => 'string'],
                'filename' => ['type' => 'string'],
                'title' => ['type' => 'string'],
                'initial_comment' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $token = $this->resolveAccessToken($run, $config);

        $response = Http::withToken($token)
            ->attach('file', $config['content'], $config['filename'] ?? 'upload.txt')
            ->post('https://slack.com/api/files.upload', array_filter([
                'channels' => $config['channels'],
                'title' => $config['title'] ?? null,
                'initial_comment' => $config['initial_comment'] ?? null,
            ], fn ($value) => $value !== null));

        $body = $response->json() ?? [];

        if (($body['ok'] ?? false) !== true) {
            throw new RuntimeException('Slack API error [files.upload]: '.($body['error'] ?? 'unknown_error'));
        }

        return $body;
    }
}
