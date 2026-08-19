<?php

namespace App\Services\Agents;

use App\Models\Agents\DocumentEmbedding;
use App\Models\Workspaces\Workspace;
use Illuminate\Support\Collection;
use Laravel\Ai\Embeddings;

/**
 * The workspace knowledge base behind `document_embeddings`: turning text
 * into stored chunks, and ranking those chunks against a query. Shared by
 * the Internal API's knowledge-base endpoints and `Ai\Tools\SearchKnowledgeTool`
 * (the agent-facing side of the same data), so ingestion and retrieval agree
 * on chunking and scoring instead of each implementing their own.
 *
 * Ranking is cosine similarity computed in PHP rather than a native vector
 * query — `document_embeddings.embedding` is JSON, deliberately, for
 * sqlite/Postgres portability. See that migration's docblock and
 * docs/AGENTS_PLAN.md's "Knowledge / RAG" section for the follow-up path to
 * a native pgvector column.
 */
class KnowledgeBase
{
    public const int DEFAULT_TOP_N = 5;

    /**
     * Target size of one stored chunk. Chunks are split on paragraph, then
     * sentence, boundaries, so a chunk runs a little over or under this.
     */
    private const int CHUNK_CHARACTERS = 1000;

    /**
     * Inputs per embeddings call — providers cap how many strings one
     * request may carry, so a long document is embedded in batches.
     */
    private const int EMBED_BATCH = 64;

    /**
     * Split text into chunks, embed them, and store one row per chunk.
     *
     * @param  array<string, mixed>|null  $metadata
     * @return Collection<int, DocumentEmbedding>
     */
    public function ingest(
        Workspace $workspace,
        string $text,
        ?string $source = null,
        string $collection = 'default',
        ?array $metadata = null,
    ): Collection {
        $chunks = $this->chunk($text);

        if ($chunks === []) {
            return collect();
        }

        $vectors = [];

        foreach (array_chunk($chunks, self::EMBED_BATCH) as $batch) {
            $vectors = [...$vectors, ...Embeddings::for($batch)->generate()->embeddings];
        }

        return collect($chunks)->map(fn (string $chunk, int $index) => DocumentEmbedding::create([
            'workspace_id' => $workspace->id,
            'collection' => $collection,
            'source' => $source,
            'chunk_text' => $chunk,
            'embedding' => $vectors[$index] ?? [],
            'metadata' => $metadata,
        ]));
    }

    /**
     * The top-scoring chunks for a query, highest first.
     *
     * @return Collection<int, array{id: int, source: string|null, text: string, score: float}>
     */
    public function search(
        Workspace $workspace,
        string $query,
        ?string $collection = null,
        int $topN = self::DEFAULT_TOP_N,
    ): Collection {
        $queryVector = Embeddings::for([$query])->generate()->embeddings[0] ?? [];

        return DocumentEmbedding::query()
            ->where('workspace_id', $workspace->id)
            ->when($collection !== null, fn ($builder) => $builder->where('collection', $collection))
            ->get()
            ->map(fn (DocumentEmbedding $chunk): array => [
                'id' => $chunk->id,
                'source' => $chunk->source,
                'text' => $chunk->chunk_text,
                'score' => $this->cosineSimilarity($queryVector, $chunk->embedding ?? []),
            ])
            ->sortByDesc('score')
            ->take($topN)
            ->values();
    }

    /**
     * Split on blank lines first (a paragraph is the most meaningful unit to
     * keep whole), then pack paragraphs up to the target size, splitting any
     * single paragraph that overshoots on sentence boundaries.
     *
     * @return array<int, string>
     */
    public function chunk(string $text): array
    {
        $chunks = [];
        $current = '';

        foreach ($this->paragraphs($text) as $paragraph) {
            if ($current !== '' && mb_strlen($current) + mb_strlen($paragraph) + 2 > self::CHUNK_CHARACTERS) {
                $chunks[] = $current;
                $current = '';
            }

            $current = $current === '' ? $paragraph : "{$current}\n\n{$paragraph}";
        }

        if (trim($current) !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /**
     * @return array<int, string>
     */
    private function paragraphs(string $text): array
    {
        $paragraphs = [];

        foreach (preg_split('/\n\s*\n/', trim($text)) ?: [] as $paragraph) {
            $paragraph = trim($paragraph);

            if ($paragraph === '') {
                continue;
            }

            if (mb_strlen($paragraph) <= self::CHUNK_CHARACTERS) {
                $paragraphs[] = $paragraph;

                continue;
            }

            $paragraphs = [...$paragraphs, ...$this->splitLongParagraph($paragraph)];
        }

        return $paragraphs;
    }

    /**
     * @return array<int, string>
     */
    private function splitLongParagraph(string $paragraph): array
    {
        $pieces = [];
        $current = '';

        // Sentence boundaries first; a "sentence" longer than the target
        // (minified JSON, a giant URL list) is hard-split so one pathological
        // line can't become a single enormous chunk.
        foreach (preg_split('/(?<=[.!?])\s+/', $paragraph) ?: [] as $sentence) {
            foreach (mb_str_split($sentence, self::CHUNK_CHARACTERS) as $piece) {
                if ($current !== '' && mb_strlen($current) + mb_strlen($piece) + 1 > self::CHUNK_CHARACTERS) {
                    $pieces[] = $current;
                    $current = '';
                }

                $current = $current === '' ? $piece : "{$current} {$piece}";
            }
        }

        if (trim($current) !== '') {
            $pieces[] = $current;
        }

        return $pieces;
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
