<?php

namespace App\Actions\Agents;

use App\Models\Agents\AgentMessage;
use App\Models\Agents\AgentSession;
use App\Services\Agents\AgentRunner;

class SendAgentMessageAction
{
    public function __construct(private readonly AgentRunner $runner) {}

    public function execute(AgentSession $session, string $message): AgentMessage
    {
        return $this->runner->run($session, $message);
    }
}
