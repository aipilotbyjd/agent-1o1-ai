<?php

namespace App\Ai\Assistant\Tools;

use App\Enums\Assistant\AssistantToolEffect;
use App\Models\Assistant\Assistant;
use App\Models\Assistant\AssistantSession;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Saves a durable fact about the owner. Every saved fact is put in front of
 * the model on each later turn (`AssistantInstructions`).
 */
class RememberTool extends AssistantTool
{
    public const string NAME = 'remember';

    public function __construct(
        private readonly Assistant $assistant,
        private readonly ?AssistantSession $session = null,
    ) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function effect(): AssistantToolEffect
    {
        return AssistantToolEffect::Internal;
    }

    public function description(): Stringable|string
    {
        return 'Saves or updates a durable fact about the person you work for, recalled in every future conversation. '
            .'Use it when they tell you about themselves, their work or their preferences, or ask you to remember something. '
            .'Use a short, stable snake_case key (e.g. "preferred_name") so saving again updates the fact instead of duplicating it.';
    }

    protected function execute(Request $request): string
    {
        $key = (string) $request['key'];

        $this->assistant->memories()->updateOrCreate(
            ['key' => $key],
            ['value' => (string) $request['value'], 'source_session_id' => $this->session?->id],
        );

        return "Remembered {$key}.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'key' => $schema->string()->required(),
            'value' => $schema->string()->required(),
        ];
    }
}
