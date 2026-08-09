<?php

namespace App\Actions\Workflows\Builder;

use App\Ai\Agents\WorkflowBuilderAgent;
use App\Models\Workflows\Builder\WorkflowBuilderMessage;
use App\Models\Workflows\Builder\WorkflowBuilderSession;

class SendWorkflowBuilderMessageAction
{
    public function execute(WorkflowBuilderSession $session, string $message): WorkflowBuilderMessage
    {
        $userMessage = $session->messages()->create([
            'role' => 'user',
            'content' => $message,
        ]);

        $lockVersionBefore = $session->draft_lock_version;

        $response = (new WorkflowBuilderAgent($session, $userMessage->id))->prompt($message);

        $session->refresh();

        $assistantMessage = $session->messages()->create([
            'role' => 'assistant',
            'content' => $response->text,
            'draft_version_id' => $session->draft_lock_version > $lockVersionBefore
                ? $session->draftVersions()->latest('id')->value('id')
                : null,
        ]);

        $session->forceFill(['last_activity_at' => now()])->save();

        return $assistantMessage;
    }
}
