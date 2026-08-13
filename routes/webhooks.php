<?php

use App\Http\Controllers\Api\Internal\V1\Connectors\OAuthConnectorController;
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

// Cashier auto-registration is disabled (AppServiceProvider::configureCashier)
// so this resolves to our own controller (idempotency guard + plan/usage-period
// sync) instead of Cashier's default one.
Route::post('stripe/webhook', [StripeWebhookController::class, 'handleWebhook'])->name('cashier.webhook');
