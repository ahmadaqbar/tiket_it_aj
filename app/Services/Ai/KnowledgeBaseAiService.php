<?php

namespace App\Services\Ai;

use App\Models\KbArticle;
use App\Models\User;
use App\Services\KnowledgeBaseService;
use Illuminate\Support\Collection;

/**
 * Tanya-jawab Knowledge Base dengan bahasa alami (khusus IT).
 * Retrieval berbasis database (FULLTEXT/LIKE) dari artikel yang boleh dibaca penanya saja.
 * Status "berdasarkan knowledge base" ditentukan oleh SERVER dari referensi yang tervalidasi, bukan klaim model.
 */
class KnowledgeBaseAiService
{
    public const MAX_ARTICLES = 5;

    public function __construct(
        private readonly AiGateway $gateway,
        private readonly AiContextBuilder $context,
        private readonly KnowledgeBaseService $kb,
    ) {}

    public function ask(string $question, User $user): array
    {
        $articles = $this->retrieve($question, $user);

        $prompt = "Jawab pertanyaan teknisi IT berikut. Utamakan isi artikel knowledge base yang diberikan dan sebutkan ID artikel yang dipakai.\n"
            ."Bila artikel tidak cukup/tidak ada, beri langkah troubleshooting umum dan nyatakan bahwa itu saran umum.\n\n"
            .$this->context->data('pertanyaan', $question, 1000)."\n\nArtikel knowledge base:\n".$this->context->articles($articles)."\n\n"
            .'Skema JSON: {"answer": string, "steps": [string, maks 8], "source_ids": [int]}.';

        $result = $this->gateway->complete('kb_ask', $user, AiContextBuilder::SYSTEM_RULES, $prompt);
        $data = AiJson::decode($result->text);
        if ($data === null) {
            $this->gateway->markInvalid('kb_ask', $user);
            throw new AiUnavailableException('Jawaban AI tidak dapat diproses. Silakan coba lagi.');
        }

        $allowed = $articles->keyBy('id');
        $sources = collect(is_array($data['source_ids'] ?? null) ? $data['source_ids'] : [])
            ->filter(fn ($id) => is_int($id) && $allowed->has($id))->unique()
            ->map(fn ($id) => ['id' => $id, 'title' => $allowed[$id]->title, 'url' => route('kb.show', $allowed[$id])])->values()->all();

        return [
            'answer' => AiJson::string($data['answer'] ?? null, 3000),
            'steps' => AiJson::stringList($data['steps'] ?? [], 8, 400),
            'sources' => $sources,
            'based_on_knowledge_base' => $sources !== [],
            'articles_found' => $articles->count(),
        ];
    }

    /** @return Collection<int, KbArticle> */
    public function retrieve(string $question, User $user): Collection
    {
        $results = $this->kb->search(KbArticle::query()->readableBy($user), $question)->limit(self::MAX_ARTICLES)->get();

        // FULLTEXT: buang hasil dengan relevansi jauh di bawah hasil teratas.
        $top = (float) ($results->first()->relevance ?? 0);
        if ($top > 0) {
            $results = $results->filter(fn ($a) => (float) $a->relevance >= $top * 0.2)->values();
        }

        return $results;
    }
}
