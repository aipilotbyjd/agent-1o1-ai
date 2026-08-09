<?php

namespace App\Ai\Tools\WorkflowBuilder;

use App\Models\Workflows\Builder\WorkflowBuilderSession;
use App\Services\Workflows\DryRunner;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use JsonException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class DryRunWorkflowTool implements Tool
{
    public function __construct(public readonly WorkflowBuilderSession $session) {}

    public function name(): string
    {
        return 'dry_run_workflow';
    }

    public function description(): Stringable|string
    {
        return "Simulate the draft end to end without calling any external service. Returns the order nodes would run in, each node's resolved config, and any template that points at data nothing provides. Use this to check your wiring before telling the user it works.";
    }

    public function handle(Request $request): Stringable|string
    {
        try {
            $input = json_decode((string) ($request->all()['sample_input_json'] ?? '{}'), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return 'sample_input_json must be a valid JSON object string.';
        }

        $result = app(DryRunner::class)->run($this->session->currentGraph(), $input ?? []);

        return json_encode($result, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'sample_input_json' => $schema->string()->description('Example run input as a JSON object string, e.g. {"email":"a@b.com"}.'),
        ];
    }
}
