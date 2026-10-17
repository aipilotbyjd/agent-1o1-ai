<?php

namespace App\Nodes\TriggersEvents;

use App\Enums\Triggers\TriggerNodeType;
use App\Enums\Triggers\WebhookAuthentication;
use App\Nodes\Support\Field;

/**
 * Starts a workflow when a request is POSTed to the trigger's private URL.
 *
 * The URL exists once the workflow is published. How a request proves it's
 * genuine is the node's Authentication setting.
 */
class WebhookTriggerNode extends TriggerNode
{
    /**
     * Which trigger node this is.
     */
    public function nodeType(): TriggerNodeType
    {
        return TriggerNodeType::Webhook;
    }

    /**
     * The name shown in the node library.
     */
    public function name(): string
    {
        return 'Webhook';
    }

    /**
     * The Hugeicons name shown on the node.
     */
    public function icon(): string
    {
        return 'webhook';
    }

    /**
     * One line on what the node does.
     */
    public function description(): string
    {
        return 'Starts the workflow when a request is POSTed to its private URL. The URL appears once the workflow is published.';
    }

    /**
     * The node's settings.
     */
    public function configSchema(): array
    {
        return $this->withActivateSwitch([
            'type' => 'object',
            'properties' => [
                // Like n8n's Webhook node. The secret is never stored in the
                // workflow: it's set on the published trigger, encrypted.
                'authentication' => Field::select('Authentication', [
                    WebhookAuthentication::None->value => 'None: anyone with the URL',
                    WebhookAuthentication::HeaderToken->value => 'Header token',
                    WebhookAuthentication::Signature->value => 'Signature (HMAC-SHA256)',
                ], 'Header token: the sender sends your secret in a header. Signature: the sender signs the body with your secret (X-Signature-256: sha256=...).', WebhookAuthentication::None->value),
                'header_name' => Field::advanced(Field::text('Token header', 'The header that carries the token, for Header token authentication.', WebhookAuthentication::DEFAULT_HEADER)),
                'filters' => $this->filtersField(),
            ],
        ]);
    }
}
