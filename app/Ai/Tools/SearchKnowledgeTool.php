<?php

namespace App\Ai\Tools;

use App\Models\Agents\DocumentEmbedding;
use App\Models\Workspaces\Workspace;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * RAG over `document_embeddings` — auto-attached by `ToolRegistry` to every
 * agent whose workspace has any embedded chunks (see docs/AGENTS_PLAN.md's
 * "Knowledge / RAG" section). Embeds the query, ranks every row for the
 * workspace by cosine similarity computed in PHP (not a native vector
 * query — `document_embeddings.embedding` is JSON, deliberately, for
 * sqlite/Postgres portability; see that migration's docblock), and returns
 * the top-N chunks.
 */
class SearchKnowledgeTool implements Tool
{
    private const int TOP_N = 5;

    public function __construct(
        private readonly Workspace $workspace,
        private readonly ?string $collection = null,
    ) {}

    public function description(): Stringable|string
    {
        return 'Searches the workspace knowledge base for chunks relevant to a query.';
    }

    public function handle(Request $request): Stringable|string
    {
        $query = (string) $request['query'];
        $queryVector = Embeddings::for([$query])->generate()->embeddings[0] ?? [];

        $ranked = DocumentEmbedding::query()
            ->where('workspace_id', $this->workspace->id)
            ->when($this->collection !== null, fn ($q) => $q->where('collection', $this->collection))
            ->get()
            ->map(fn (DocumentEmbedding $chunk) => [
                'source' => $chunk->source,
                'text' => $chunk->chunk_text,
                'score' => $this->cosineSimilarity($queryVector, $chunk->embedding),
            ])
            ->sortByDesc('score')
            ->take(self::TOP_N)
            ->values();

        return json_encode(
            $ranked->map(fn (array $r) => [...$r, 'score' => round($r['score'], 4)])->all(),
        ) ?: '[]';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->required(),
        ];
    }

    /**
     * @param  array<int, float>  $a
     * @param  array<int, float>  $b
     */
    private function cosineSimilarity(array $a, array $b): float
    {
        if ($a === [] || $b === [] || count($a) !== count($b)) {
            return 0.0;
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($a as $i => $value) {
            $dot += $value * $b[$i];
            $normA += $value ** 2;
            $normB += $b[$i] ** 2;
        }

        if ($normA === 0.0 || $normB === 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }
}
