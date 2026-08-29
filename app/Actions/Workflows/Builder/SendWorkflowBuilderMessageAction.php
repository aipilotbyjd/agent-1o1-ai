<?php

namespace App\Actions\Workflows\Builder;

use App\Ai\Agents\WorkflowBuilderAgent;
use App\Models\Workflows\Builder\WorkflowBuilderMessage;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use App\Services\Ai\ModelCatalogResolver;
use RuntimeException;

class SendWorkflowBuilderMessageAction
{
    /**
     * The `model_catalog` slug for the workflow-builder assistant itself
     * (not a user-selectable entry — see `ModelCatalog::is_internal`).
     * Gumloop's own equivalent internal agent runs on an open-weight model
     * via Fireworks for a large cost saving with no UX change; this lets you
     * do the same here by adding/enabling a route on this slug, without
     * touching this action again.
     */
    private const string MODEL_CATALOG_SLUG = 'workflow-builder-assistant';

    public function __construct(private readonly ModelCatalogResolver $modelCatalog) {}

    public function execute(WorkflowBuilderSession $session, string $message): WorkflowBuilderMessage
    {
        $userMessage = $session->messages()->create([
            'role' => 'user',
            'content' => $message,
        ]);

        $lockVersionBefore = $session->draft_lock_version;

        $response = (new WorkflowBuilderAgent($session, $userMessage->id))
            ->prompt($message, provider: $this->resolveProvider());

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

    /**
     * Falls back to `laravel/ai`'s own default provider (`config('ai.default')`)
     * when the `workflow-builder-assistant` catalog entry hasn't been seeded
     * or has no enabled route — so a fresh install isn't broken by this.
     *
     * @return array<string, string>|null
     */
    private function resolveProvider(): ?array
    {
        try {
            return $this->modelCatalog->providerChain(self::MODEL_CATALOG_SLUG);
        } catch (RuntimeException) {
            return null;
        }
    }
}
