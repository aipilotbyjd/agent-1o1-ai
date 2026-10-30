<?php

namespace App\Nodes\Integrations\Firecrawl;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FirecrawlExecuteInSessionNode extends AbstractFirecrawlNode
{
    public function type(): string
    {
        return 'firecrawl_execute_in_session';
    }

    public function name(): string
    {
        return 'Firecrawl: Run Code In Browser Session';
    }

    public function description(): string
    {
        return 'Runs Playwright, Python or agent-browser code in a browser session.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['session_id', 'code'],
            'properties' => [
                ...$this->credentialFields(),
                'session_id' => Field::text('Session id', 'The id Create Browser Session returned.'),
                'code' => Field::textarea('Code'),
                'language' => Field::select('Language', ['node', 'python', 'bash'], null, 'node'),
                'timeout' => Field::advanced(Field::integer('Timeout (seconds)', null, 30, 1, 90)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $timeout = $this->limit($config, 30, 90, 'timeout');
        $answer = $this->firecrawl($run, $config, 'POST', '/interact/'.$this->pathId($config, 'session_id', 'The session id').'/execute', ['code' => $this->requiredString($config, 'code', 'The code'), 'language' => $this->textOf($config, 'language') ?? 'node', 'timeout' => $timeout], timeout: $timeout + 15);

        return ['stdout' => $answer['stdout'] ?? null, 'result' => $answer['result'] ?? null, 'stderr' => $answer['stderr'] ?? null, 'exit_code' => $answer['exitCode'] ?? null, 'killed' => $answer['killed'] ?? null, 'error' => $answer['error'] ?? null];
    }
}
