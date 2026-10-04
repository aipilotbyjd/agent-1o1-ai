<?php

use App\Http\Controllers\Api\Internal\V1\Connectors\OAuthConnectorController;
use App\Http\Controllers\Webhooks\AssistantEmailWebhookController;
use App\Http\Controllers\Webhooks\AssistantSlackEventsController;
use App\Http\Controllers\Webhooks\AssistantSlackOAuthController;
use App\Http\Controllers\Webhooks\AssistantSmsWebhookController;
use App\Http\Controllers\Webhooks\AssistantWebhookController;
use App\Http\Controllers\Webhooks\SlackAgentActionController;
use App\Http\Controllers\Webhooks\StripeWebhookController;
use App\Http\Controllers\Webhooks\WaitCallbackController;
use App\Http\Controllers\Webhooks\WebhookController;
use Illuminate\Support\Facades\Route;

// Public — authenticated by the trigger's own token, not a session or API key.
// Deliberately outside routes/api/{internal,public}/, whose middleware groups
// this endpoint must not inherit. See docs/TRIGGERS_PLAN.md.
Route::post('hooks/{token}', WebhookController::class)
    ->middleware('throttle:trigger-hooks')
    ->name('hooks.trigger');

// A personal assistant's webhook trigger — the token in the URL is the
// credential. Answers at once; the assistant runs on the queue.
// The assistant's own channels. Declared before hooks/assistant/{token} so
// these fixed paths are never read as a trigger token.
Route::post('hooks/assistant/email', AssistantEmailWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('hooks.assistant.email');
Route::post('hooks/assistant/slack/events', AssistantSlackEventsController::class)
    ->name('hooks.assistant.slack.events');
Route::get('hooks/assistant/slack/oauth', AssistantSlackOAuthController::class)
    ->name('hooks.assistant.slack.oauth');
Route::post('hooks/assistant/sms', AssistantSmsWebhookController::class)
    ->middleware('throttle:300,1')
    ->name('hooks.assistant.sms');

Route::post('hooks/assistant/{token}', AssistantWebhookController::class)
    ->middleware('throttle:assistant-hooks')
    ->name('hooks.assistant');

// Same pattern, applied to a Wait node's one-time callback token instead of
// a Trigger's — reuses the 'trigger-hooks' limiter since it's already
// generically keyed by the {token} route param, not trigger-specific.
Route::post('hooks/wait/{token}', WaitCallbackController::class)
    ->middleware('throttle:trigger-hooks')
    ->name('hooks.wait-callback');

// Public — the provider redirects the user's browser here after the OAuth
// consent screen, so no session/API-key auth is available. Tenant-safety
// comes from the unguessable `state` query param OAuthConnectorController
// looks up, not from this route's auth.
Route::get('oauth/connectors/callback', [OAuthConnectorController::class, 'callback'])
    ->name('oauth.connectors.callback');

// Public — Slack's interactivity callback for approve/reject buttons on
// agent approval messages. Authenticated by Slack's request signature and
// the button's encrypted value; see SlackAgentActionController.
Route::post('slack/agent-actions', SlackAgentActionController::class)
    ->middleware('throttle:trigger-hooks')
    ->name('slack.agent-actions');

// Cashier auto-registration is disabled (AppServiceProvider::configureCashier)
// so this resolves to our own controller (idempotency guard + plan/usage-period
// sync) instead of Cashier's default one.
Route::post('stripe/webhook', [StripeWebhookController::class, 'handleWebhook'])->name('cashier.webhook');
