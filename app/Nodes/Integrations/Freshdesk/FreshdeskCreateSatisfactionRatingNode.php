<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskCreateSatisfactionRatingNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_create_satisfaction_rating';
    }

    public function name(): string
    {
        return 'Freshdesk: Create Satisfaction Rating';
    }

    public function description(): string
    {
        return 'Records a satisfaction rating on a ticket.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['ticket_id', 'ratings'],
            'properties' => [
                ...$this->credentialFields(),
                'ticket_id' => Field::text('Ticket id'),
                'ratings' => Field::json('Ratings (JSON)', 'e.g. {"default_question": 103}.'),
                'feedback' => Field::advanced(Field::textarea('Feedback')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'ticket_id', 'The ticket');

        return $this->freshdesk($run, $config, 'POST', "/tickets/{$id}/satisfaction_ratings", [], $this->present(['ratings' => $this->requiredJson($config, 'ratings', 'The ratings'), 'feedback' => $this->text($config, 'feedback')]));
    }
}
