<?php

namespace App\Services\Agents;

use App\Actions\Billing\DeductCreditsAction;
use App\Enums\Billing\CreditTransactionType;
use App\Models\Agents\DocumentEmbedding;
use App\Models\Workspaces\Workspace;
use App\Services\Billing\CreditMeter;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
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
 *
 * Search is hybrid: a chunk's score is its cosine similarity plus a bonus for
 * containing the query's words. Embeddings are good at meaning and poor at
 * exact tokens — an invoice number, a product SKU, a person's surname — so a
 * chunk that literally contains them is lifted above ones that are merely
 * about something similar. Chunks that are neither semantically close nor
 * share a word with the query are dropped instead of returned as the "best"
 * of nothing, so a model searching for something the knowledge base doesn't
 * cover gets no results rather than misleading ones.
 */
class KnowledgeBase
{
    public const int DEFAULT_TOP_N = 5;

    /**
     * The cosine similarity below which a chunk with no word in common with
     * the query is treated as unrelated.
     */
    public const float MIN_SEMANTIC_SCORE = 0.25;

    /**
     * Added to the score for the share of query words a chunk contains.
     */
    private const float KEYWORD_WEIGHT = 0.4;

    /**
     * Added on top when a chunk contains a multi-word query verbatim.
     */
    private const float PHRASE_WEIGHT = 0.1;

    /**
     * Words too common to say anything about whether a chunk is relevant.
     */
    private const array STOP_WORDS = [
        'a', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'do', 'does', 'for', 'from', 'how', 'in', 'is', 'it',
        'me', 'my', 'of', 'on', 'or', 'our', 'that', 'the', 'this', 'to', 'was', 'we', 'what', 'when', 'where',
        'which', 'who', 'why', 'with', 'you', 'your',
    ];

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
     * Split text into chunks, embed them, and store one row per chunk. The
     * embedding tokens are charged to `$workspace` — see `chargeForEmbeddings()`.
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
        $usage = ['prompt_tokens' => 0, 'provider' => null, 'model' => null];

        foreach (array_chunk($chunks, self::EMBED_BATCH) as $batch) {
            $response = Embeddings::for($batch)->generate();

            $vectors = [...$vectors, ...$response->embeddings];
            $usage = [
                'prompt_tokens' => $usage['prompt_tokens'] + $response->tokens,
                'provider' => $response->meta->provider,
                'model' => $response->meta->model,
            ];
        }

        $stored = collect($chunks)->map(fn (string $chunk, int $index) => DocumentEmbedding::create([
            'workspace_id' => $workspace->id,
            'collection' => $collection,
            'source' => $source,
            'chunk_text' => $chunk,
            'embedding' => $vectors[$index] ?? [],
            'metadata' => $metadata,
        ]));

        $this->chargeForEmbeddings($workspace, $stored->first(), $usage);

        return $stored;
    }

    /**
     * Bills the ingestion's embedding tokens against its first stored chunk.
     * With overdraft: the provider has already been paid by the time the
     * chunks exist. Retrieval (`search()`) isn't charged — one short query's
     * embedding rounds to nothing, and an agent's search is already billed
     * as the tool call it is.
     *
     * @param  array{prompt_tokens: int, provider: string|null, model: string|null}  $usage
     */
    private function chargeForEmbeddings(Workspace $workspace, DocumentEmbedding $firstChunk, array $usage): void
    {
        $credits = app(CreditMeter::class)->costForEmbeddings($usage);

        if ($credits === 0) {
            return;
        }

        app(DeductCreditsAction::class)->execute(
            $workspace,
            CreditTransactionType::KnowledgeIngestion,
            $firstChunk->id,
            $credits,
            $firstChunk->source !== null ? 'Knowledge ingestion ('.Str::limit($firstChunk->source, 200).')' : 'Knowledge ingestion',
            allowOverdraft: true,
        );
    }

    /**
     * The top-scoring chunks for a query, highest first.
     *
     * @param  string|array<int, string>|null  $collection  One collection, a
     *                                                      list to search across (an agent's attached sources — see
     *                                                      `ToolRegistry`), or null for every collection in the workspace.
     * @return Collection<int, array{id: string, source: string|null, text: string, score: float}>
     */
    public function search(
        Workspace $workspace,
        string $query,
        string|array|null $collection = null,
        int $topN = self::DEFAULT_TOP_N,
    ): Collection {
        $queryVector = Embeddings::for([$query])->generate()->embeddings[0] ?? [];

        // Chunks are read in batches and only the running top N is kept, so
        // memory stays flat however large the workspace's knowledge base is.
        $best = [];

        $chunks = DocumentEmbedding::query()
            ->select(['id', 'source', 'chunk_text', 'embedding'])
            ->where('workspace_id', $workspace->id)
            ->when($collection !== null, fn ($builder) => is_array($collection)
                ? $builder->whereIn('collection', $collection)
                : $builder->where('collection', $collection))
            ->lazyById(500);

        $terms = $this->terms($query);
        $phrase = count($terms) > 1 ? mb_strtolower(trim($query)) : null;

        foreach ($chunks as $chunk) {
            $semantic = $this->cosineSimilarity($queryVector, $chunk->embedding ?? []);
            $keyword = $this->keywordCoverage($terms, $chunk->chunk_text);

            if ($semantic < self::MIN_SEMANTIC_SCORE && $keyword === 0.0) {
                continue;
            }

            $phraseBonus = $phrase !== null && str_contains(mb_strtolower($chunk->chunk_text), $phrase) ? self::PHRASE_WEIGHT : 0.0;

            $best[] = [
                'id' => $chunk->id,
                'source' => $chunk->source,
                'text' => $chunk->chunk_text,
                'score' => $semantic + self::KEYWORD_WEIGHT * $keyword + $phraseBonus,
            ];

            if (count($best) > $topN) {
                $best = $this->rank($best);
                array_pop($best);
            }
        }

        return collect($this->rank($best))->values();
    }

    /**
     * Highest score first; `usort` is stable, so ties keep insertion (id)
     * order.
     *
     * @param  array<int, array{id: string, source: string|null, text: string, score: float}>  $results
     * @return array<int, array{id: string, source: string|null, text: string, score: float}>
     */
    private function rank(array $results): array
    {
        usort($results, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return $results;
    }

    /**
     * The full text of one "document" — every chunk sharing a `source`
     * (and, when given, a `collection`), reassembled in storage order.
     * Backs `Ai\Tools\ReadKnowledgeDocumentTool`: a search hit's `source` is
     * a chunk-level snippet, and this is what a model calls when a snippet
     * alone isn't enough context.
     *
     * @param  string|array<int, string>|null  $collection
     */
    public function readDocument(Workspace $workspace, string $source, string|array|null $collection = null): ?string
    {
        $chunks = DocumentEmbedding::query()
            ->where('workspace_id', $workspace->id)
            ->where('source', $source)
            ->when($collection !== null, fn ($builder) => is_array($collection)
                ? $builder->whereIn('collection', $collection)
                : $builder->where('collection', $collection))
            ->orderBy('id')
            ->pluck('chunk_text');

        return $chunks->isEmpty() ? null : $chunks->implode("\n\n");
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
    /**
     * The distinct, meaningful words of a query, lowercased.
     *
     * @return array<int, string>
     */
    private function terms(string $query): array
    {
        $words = preg_split('/[^\p{L}\p{N}_-]+/u', mb_strtolower($query), flags: PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter(
            $words,
            fn (string $word): bool => mb_strlen($word) > 1 && ! in_array($word, self::STOP_WORDS, true),
        )));
    }

    /**
     * The share of `$terms` that appear in `$text` as whole words, from 0 to 1.
     *
     * @param  array<int, string>  $terms
     */
    private function keywordCoverage(array $terms, string $text): float
    {
        if ($terms === []) {
            return 0.0;
        }

        $haystack = mb_strtolower($text);
        $found = 0;

        foreach ($terms as $term) {
            if (preg_match('/(?<![\p{L}\p{N}_-])'.preg_quote($term, '/').'(?![\p{L}\p{N}_-])/u', $haystack) === 1) {
                $found++;
            }
        }

        return $found / count($terms);
    }

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
