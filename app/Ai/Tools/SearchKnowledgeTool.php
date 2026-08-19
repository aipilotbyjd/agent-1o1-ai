<?php

namespace App\Ai\Tools;

use App\Models\Workspaces\Workspace;
use App\Services\Agents\KnowledgeBase;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * RAG over `document_embeddings` — auto-attached by `ToolRegistry` to every
 * agent whose workspace has any embedded chunks (see docs/AGENTS_PLAN.md's
 * "Knowledge / RAG" section). The chunking, embedding, and cosine ranking
 * itself lives in `Services\Agents\KnowledgeBase`, shared with the Internal
 * API's knowledge-base endpoints, so what an agent retrieves is exactly what
 * that API's search preview shows.
 */
class SearchKnowledgeTool implements Tool
{
    public function __construct(
        private readonly Workspace $workspace,
        private readonly ?string $collection = null,
        private readonly KnowledgeBase $knowledgeBase = new KnowledgeBase,
    ) {}

    public function description(): Stringable|string
    {
        return 'Searches the workspace knowledge base for chunks relevant to a query.';
    }

    public function handle(Request $request): Stringable|string
    {
        $results = $this->knowledgeBase
            ->search($this->workspace, (string) $request['query'], $this->collection)
            ->map(fn (array $result): array => [
                'source' => $result['source'],
                'text' => $result['text'],
                'score' => round($result['score'], 4),
            ]);

        return json_encode($results->all()) ?: '[]';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->required(),
        ];
    }
}
