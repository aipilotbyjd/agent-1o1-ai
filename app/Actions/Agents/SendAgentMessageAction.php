<?php

namespace App\Actions\Agents;

use App\Models\Agents\AgentMessage;
use App\Models\Agents\AgentSession;
use App\Services\Agents\AgentRunner;

class SendAgentMessageAction
{
    public function __construct(private readonly AgentRunner $runner) {}

    /**
     * `$triggerType` is recorded on the turn's own `Run` — it defaults to
     * `manual` (a person chatting through the API) and is set to the trigger's
     * type when the turn was started by the trigger pipeline
     * (`App\Services\Triggers\TargetRunStarter`).
     */
    public function execute(AgentSession $session, string $message, string $triggerType = 'manual'): AgentMessage
    {
        return $this->runner->run($session, $message, $triggerType);
    }
}
