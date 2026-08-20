<?php

namespace App\Observers;

use App\Events\Agents\AgentMessageCreated;
use App\Models\Agents\AgentMessage;

/**
 * Messages are append-only, so creation is the only transition worth
 * publishing — `usage` being force-filled onto an assistant message
 * afterwards is accounting, not conversation.
 */
class AgentMessageObserver
{
    public function created(AgentMessage $message): void
    {
        AgentMessageCreated::dispatch($message);
    }
}
