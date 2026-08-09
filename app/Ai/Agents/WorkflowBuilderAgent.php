<?php

namespace App\Ai\Agents;

use App\Ai\Tools\WorkflowBuilder\AddNodeTool;
use App\Ai\Tools\WorkflowBuilder\ConnectNodesTool;
use App\Ai\Tools\WorkflowBuilder\DisconnectNodesTool;
use App\Ai\Tools\WorkflowBuilder\DryRunWorkflowTool;
use App\Ai\Tools\WorkflowBuilder\InspectNodeSchemaTool;
use App\Ai\Tools\WorkflowBuilder\ListAvailableNodesTool;
use App\Ai\Tools\WorkflowBuilder\RemoveNodeTool;
use App\Ai\Tools\WorkflowBuilder\UpdateNodeTool;
use App\Ai\Tools\WorkflowBuilder\ValidateWorkflowTool;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;

/**
 * Chat-based workflow authoring — the single highest-leverage piece to get
 * right is this system prompt, which encodes the entire interaction
 * contract (call `list_available_nodes` first, inspect before using an
 * unfamiliar type, validate + dry-run before declaring victory). Every tool
 * operates on `$session->draft_graph`, never a live `Workflow` — promoting
 * the draft happens separately (`WorkflowBuilderSessionController::promote()`).
 *
 * Mirrors `WorkspaceAgent`: history comes from the session's own messages
 * table (not the `RemembersConversations` trait's `agent_conversations`
 * tables), so `$beforeMessageId` excludes the just-persisted user turn the
 * same way `WorkspaceAgent` does.
 */
class WorkflowBuilderAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    public function __construct(
        public readonly WorkflowBuilderSession $session,
        private readonly ?int $beforeMessageId = null,
    ) {}

    public function instructions(): string
    {
        return <<<'TEXT'
        You are a workflow-building assistant. You edit a workflow's draft graph (nodes and
        edges) on the user's behalf, using the tools available to you — you never describe
        an edit without actually making it.

        Start from list_available_nodes to see what exists. Before adding a node of a type
        you're not already familiar with in this conversation, call inspect_node_schema with
        that node's "type" so the node's "config" matches what it expects — adding a node
        with missing or mistyped config is rejected and you will have to correct it.

        Give every node a short, unique, descriptive key (e.g. "send_confirmation_email").
        Connect nodes in the order they should run; for a router node, add one edge per
        branch with the matching "condition" value. To handle failures, add an edge with the
        condition "error" from the node that might fail.

        Before telling the user the workflow is ready, call validate_workflow, and use
        dry_run_workflow to confirm the wiring resolves — it simulates the graph without
        calling any external service. Fix anything either one reports.

        After each change, briefly confirm in plain language what you did.
        TEXT;
    }

    /**
     * @return iterable<int, Tool>
     */
    public function tools(): iterable
    {
        return [
            new ListAvailableNodesTool($this->session),
            new InspectNodeSchemaTool($this->session),
            new AddNodeTool($this->session),
            new UpdateNodeTool($this->session),
            new RemoveNodeTool($this->session),
            new ConnectNodesTool($this->session),
            new DisconnectNodesTool($this->session),
            new ValidateWorkflowTool($this->session),
            new DryRunWorkflowTool($this->session),
        ];
    }

    /**
     * @return iterable<int, Message>
     */
    public function messages(): iterable
    {
        return $this->session->messages()
            ->when($this->beforeMessageId !== null, fn ($query) => $query->where('id', '!=', $this->beforeMessageId))
            ->oldest()
            ->get()
            ->map(fn ($message) => new Message($message->role, $message->content))
            ->all();
    }
}
