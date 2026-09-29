<?php

namespace App\Ai\Agents;

use App\Ai\Tools\WorkflowBuilder\AddNodeTool;
use App\Ai\Tools\WorkflowBuilder\ConnectNodesTool;
use App\Ai\Tools\WorkflowBuilder\DisconnectNodesTool;
use App\Ai\Tools\WorkflowBuilder\DryRunWorkflowTool;
use App\Ai\Tools\WorkflowBuilder\InspectNodeOutputTool;
use App\Ai\Tools\WorkflowBuilder\InspectNodeSchemaTool;
use App\Ai\Tools\WorkflowBuilder\ListAvailableNodesTool;
use App\Ai\Tools\WorkflowBuilder\ListWorkflowsTool;
use App\Ai\Tools\WorkflowBuilder\ReadDraftTool;
use App\Ai\Tools\WorkflowBuilder\RemoveNodeTool;
use App\Ai\Tools\WorkflowBuilder\UpdateNodeTool;
use App\Ai\Tools\WorkflowBuilder\ValidateWorkflowTool;
use App\Enums\Workflows\BuilderMessageStatus;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;

/**
 * Chat-based workflow authoring — the single highest-leverage piece to get
 * right is this system prompt, which encodes the entire interaction
 * contract (read the draft before changing it, inspect before using an
 * unfamiliar type, validate + dry-run before declaring victory). Every tool
 * operates on `$session->draft_graph`, never a live `Workflow` — promoting
 * the draft happens separately (`WorkflowBuilderSessionController::promote()`).
 *
 * Mirrors `WorkspaceAgent`: history comes from the session's own messages
 * table (not the `RemembersConversations` trait's `agent_conversations`
 * tables), so `$beforeMessageId` excludes the just-persisted user turn the
 * same way `WorkspaceAgent` does. Replies that are still being written or
 * that failed are left out too — neither is something the assistant said.
 *
 * Run from `ProcessWorkflowBuilderMessageJob`; the step and time limits keep
 * a confused model from looping on tool calls until the job is killed.
 */
#[MaxSteps(25)]
#[Timeout(280)]
class WorkflowBuilderAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    public function __construct(
        public readonly WorkflowBuilderSession $session,
        private readonly ?string $beforeMessageId = null,
    ) {}

    public function instructions(): string
    {
        $nodeCount = count($this->session->currentGraph()['nodes']);

        return <<<TEXT
        You are a workflow-building assistant. You edit a workflow's draft graph (nodes and
        edges) on the user's behalf, using the tools available to you — you never describe
        an edit without actually making it.

        The draft is currently titled "{$this->session->title}" and has {$nodeCount} node(s).
        If it has any, call read_draft before changing it so you work from what is really
        there — the user can also edit the draft by hand between your turns.

        Use list_available_nodes to see what exists. Before adding a node of a type you're
        not already familiar with in this conversation, call inspect_node_schema with that
        node's "type" so the node's "config" matches what it expects — adding a node with
        missing or mistyped config is rejected and you will have to correct it.

        To pass data between nodes, reference an earlier node's output in a later node's
        config with a template like {{ nodes.<key>.<field> }}, or the run input with
        {{ input.<field> }}. Call inspect_node_output to see which fields a node is known to
        produce before referencing them. Never put credentials in a config — reference a
        stored secret with {{ secrets.<NAME> }} instead.

        Give every node a short, unique, descriptive key (e.g. "send_confirmation_email").
        Connect nodes in the order they should run; for a router node, add one edge per
        branch with the matching "condition" value. To handle failures, add an edge with the
        condition "error" from the node that might fail.

        Flow-logic nodes shape how a run moves: human_approval pauses for a person, wait
        pauses for an external callback, join_paths waits for parallel branches to meet,
        subflow runs another workflow, and loop runs one per item of a list. Their
        inspect_node_schema result includes how_to_wire — read it before using one, since
        some of them fail (and so need an "error" edge) instead of branching. subflow and
        loop need a published workflow's id from list_workflows. To repeat a single node
        once per item, give that node a "_loop" config ({"items_path": "nodes.<key>.<list>"})
        rather than adding a loop node.

        If an edit is rejected because the user changed the draft, call read_draft and
        redo the edit against the current draft.

        Before telling the user the workflow is ready, call validate_workflow, and use
        dry_run_workflow to confirm the wiring resolves — it simulates the graph without
        calling any external service. Fix anything either one reports.

        Keep replies short: people are building, not reading. After your changes, say in
        one or two sentences what you did and what the user may still need to fill in
        (connected accounts, secrets, IDs only they know).
        TEXT;
    }

    /**
     * @return iterable<int, Tool>
     */
    public function tools(): iterable
    {
        return [
            new ReadDraftTool($this->session),
            new ListAvailableNodesTool($this->session),
            new InspectNodeSchemaTool($this->session),
            new InspectNodeOutputTool($this->session),
            new ListWorkflowsTool($this->session),
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
            ->where('processing_status', BuilderMessageStatus::Completed)
            ->where('content', '!=', '')
            ->when($this->beforeMessageId !== null, fn ($query) => $query->where('id', '!=', $this->beforeMessageId))
            ->oldest()
            ->oldest('id')
            ->get()
            ->map(fn ($message) => new Message($message->role, $message->content))
            ->all();
    }
}
