<?php

namespace App\Nodes\TriggersEvents;

use App\Enums\Triggers\TriggerNodeType;
use App\Nodes\Support\Field;

/**
 * Starts a workflow on a cron schedule, in the chosen timezone.
 */
class ScheduleTriggerNode extends TriggerNode
{
    /**
     * Which trigger node this is.
     */
    public function nodeType(): TriggerNodeType
    {
        return TriggerNodeType::Schedule;
    }

    /**
     * The name shown in the node library.
     */
    public function name(): string
    {
        return 'Schedule';
    }

    /**
     * The Hugeicons name shown on the node.
     */
    public function icon(): string
    {
        return 'calendar-03';
    }

    /**
     * One line on what the node does.
     */
    public function description(): string
    {
        return 'Starts the workflow on a repeating schedule.';
    }

    /**
     * The node's settings.
     */
    public function configSchema(): array
    {
        return $this->withActivateSwitch([
            'type' => 'object',
            'required' => ['cron'],
            'properties' => [
                'cron' => Field::text('Schedule (cron)', 'Five fields: minute hour day month weekday. "0 9 * * 1-5" is weekdays at 9:00.', '0 9 * * *'),
                'timezone' => Field::dynamic('Timezone', 'system.timezones', 'The schedule runs in this timezone. Defaults to UTC.', 'UTC', allowCustom: false),
            ],
        ]);
    }
}
