<?php

namespace App\Http\Controllers;

use App\Actions\Agents\ResolveAgentActionsAction;
use App\Models\Agents\AgentAction;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The page an approval email links to. The link is signed for one recipient
 * and one action (`AgentActionApprovalRequestedNotification`), which is what
 * authenticates it — no login needed to approve or reject from a phone.
 *
 * Opening the link only shows the action; nothing is decided until the form
 * is submitted, because mail scanners open links on their own. Editing the
 * arguments isn't offered here — that needs the app.
 */
class AgentActionSignedDecisionController extends Controller
{
    public function show(Request $request, AgentAction $action): View
    {
        return view('agent-actions.decide', [
            'action' => $action->load('agent'),
            'decided' => ! $action->status->isAwaitingDecision(),
            'formUrl' => $request->fullUrl(),
        ]);
    }

    public function store(Request $request, AgentAction $action, ResolveAgentActionsAction $resolve): View
    {
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $user = User::query()->findOrFail($request->query('user'));

        try {
            $resolve->execute($user, [[
                'action_id' => $action->id,
                'decision' => $validated['decision'],
                'note' => $validated['note'] ?? null,
            ]], 'email');
        } catch (AuthorizationException) {
            abort(403, 'You are no longer allowed to decide this action.');
        }

        return view('agent-actions.decide', [
            'action' => $action->fresh()->load('agent'),
            'decided' => true,
            'formUrl' => null,
        ]);
    }
}
