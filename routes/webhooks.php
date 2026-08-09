<?php

use App\Http\Controllers\Webhooks\WebhookController;
use Illuminate\Support\Facades\Route;

// Public — authenticated by the trigger's own token, not a session or API key.
// Deliberately outside routes/api/{internal,public}/, whose middleware groups
// this endpoint must not inherit. See docs/TRIGGERS_PLAN.md.
Route::post('hooks/{token}', WebhookController::class)
    ->middleware('throttle:trigger-hooks')
    ->name('hooks.trigger');
