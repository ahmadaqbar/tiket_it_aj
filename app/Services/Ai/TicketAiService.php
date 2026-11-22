<?php

namespace App\Services\Ai;

use App\Enums\TicketPriority;
use App\Models\Category;
use App\Models\KbArticle;
use App\Models\Subcategory;
use App\Models\Ticket;
use App\Models\User;
use App\Services\KnowledgeBaseService;
use Illuminate\Support\Collection;

/**
 * Fitur AI pada tiket (khusus IT). Semua hasil adalah SARAN yang dapat diedit — tidak ada perubahan data
 * tiket, status, prioritas, atau pengiriman balasan yang dilakukan otomatis.
 */
class TicketAiService
{
    public const REPLY_STYLES = [
        'formal' => 'Formal — bahasa resmi dan sopan',
        'professional' => 'Profesional — ramah dan jelas',
        'short' => 'Singkat — 1–3 kalimat',
        'technical' => 'Teknis — menyertakan detail teknis seperlunya',
    ];

    public function __construct(
        private readonly AiGateway $gateway,
        private readonly AiContextBuilder $context,
        private readonly KnowledgeBaseService $kb,
    ) {}

    /** Saran kategori/subkategori/prioritas/dampak + ringkasan + langkah awal. Tanpa catatan internal. */
    public function classify(Ticket $ticket, User $user): array
    {
        $prompt = "Klasifikasikan tiket berikut. Pilih kategori & subkategori HANYA dari daftar (gunakan ID).\n"
            ."Daftar kategori aktif:\n".$this->context->categoryList()."\n\n"
            .$this->context->ticket($ticket, includeInternal: false)."\n\n"
            .'Skema JSON: {"category_id": int|null, "subcategory_id": int|null, "priority": "LOW"|"MEDIUM"|"HIGH"|"URGENT", '
            .'"impact": "Satu perangkat|Satu pengguna|Beberapa pengguna|Satu unit|Beberapa unit", "summary": string (maks 2 kalimat), '
            .'"troubleshooting": [string, maks 6 langkah singkat]}. Prioritas: URGENT bila operasional berhenti, HIGH bila pekerjaan terhambat, '
            .'MEDIUM bila mengganggu sebagian, LOW bila tidak mengganggu.';

        $data = $this->ask('classify', $user, $prompt, $ticket);

        $category = is_int($data['category_id'] ?? null) ? Category::active()->find($data['category_id']) : null;
        $subcategory = $category && is_int($data['subcategory_id'] ?? null)
            ? Subcategory::active()->where('category_id', $category->id)->find($data['subcategory_id']) : null;
        $priority = TicketPriority::tryFrom((string) ($data['priority'] ?? ''));

        return [
            'category_id' => $category?->id,
            'category' => $category?->name,
            'subcategory_id' => $subcategory?->id,
            'subcategory' => $subcategory?->name,
            'priority' => $priority?->value,
            'priority_label' => $priority?->label(),
            'impact' => AiJson::string($data['impact'] ?? null, 100),
            'summary' => AiJson::string($data['summary'] ?? null, 500),
            'troubleshooting' => AiJson::stringList($data['troubleshooting'] ?? [], 6, 300),
        ];
    }

    /** Langkah troubleshooting dengan prioritas artikel KB yang boleh dibaca user. Tanpa catatan internal. */
    public function troubleshoot(Ticket $ticket, User $user): array
    {
        $articles = $this->relevantArticles($ticket, $user);

        $prompt = "Berikan langkah troubleshooting praktis dan berurutan untuk tiket berikut. Utamakan artikel knowledge base bila relevan.\n\n"
            .$this->context->ticket($ticket, includeInternal: false)."\n\nArtikel knowledge base:\n".$this->context->articles($articles)."\n\n"
            .'Skema JSON: {"steps": [string, maks 10], "notes": string|null, "kb_article_ids": [int, hanya ID artikel yang dipakai]}.';

        $data = $this->ask('troubleshoot', $user, $prompt, $ticket);
        $refs = $this->validRefs($data['kb_article_ids'] ?? [], $articles);

        return [
            'steps' => AiJson::stringList($data['steps'] ?? [], 10, 400),
            'notes' => AiJson::string($data['notes'] ?? null, 800),
            'references' => $refs,
            'used_knowledge_base' => $refs !== [],
        ];
    }

    /** Ringkasan untuk laporan operasional IT. Termasuk catatan internal karena hasilnya hanya untuk IT. */
    public function summarize(Ticket $ticket, User $user): array
    {
        $prompt = "Buat ringkasan operasional singkat dari tiket berikut untuk laporan internal IT.\n\n"
            .$this->context->ticket($ticket, includeInternal: true, maxReplies: 30)."\n\n"
            .'Skema JSON: {"problem": string, "actions": [string], "current_status": string, "resolution": string|null, "next_steps": [string]}.';

        $data = $this->ask('summary', $user, $prompt, $ticket);

        return [
            'problem' => AiJson::string($data['problem'] ?? null, 600),
            'actions' => AiJson::stringList($data['actions'] ?? [], 10, 300),
            'current_status' => AiJson::string($data['current_status'] ?? null, 300),
            'resolution' => AiJson::string($data['resolution'] ?? null, 600),
            'next_steps' => AiJson::stringList($data['next_steps'] ?? [], 6, 300),
        ];
    }

    /**
     * Draf balasan untuk pelapor. TIDAK memakai catatan internal (agar tidak bocor ke pelapor).
     * Hanya mengembalikan teks — pengiriman tetap lewat form balasan setelah diedit/dikonfirmasi IT.
     */
    public function draftReply(Ticket $ticket, User $user, string $style, ?string $instruction = null): array
    {
        $style = array_key_exists($style, self::REPLY_STYLES) ? $style : 'professional';

        $prompt = 'Tulis draf balasan dari Tim IT kepada pelapor tiket berikut. Gaya: '.self::REPLY_STYLES[$style].".\n"
            ."Jangan menjanjikan waktu yang tidak diketahui. Jangan menyebut nama orang. Jangan menyertakan data rahasia.\n"
            .($instruction ? $this->context->data('arahan_teknisi', $instruction, 500)."\n" : '')
            ."\n".$this->context->ticket($ticket, includeInternal: false)."\n\n"
            .'Skema JSON: {"reply": string}.';

        $data = $this->ask('draft_reply', $user, $prompt, $ticket);

        return ['reply' => AiJson::string($data['reply'] ?? null, 3000), 'style' => $style];
    }

    /** @return Collection<int, KbArticle> */
    private function relevantArticles(Ticket $ticket, User $user): Collection
    {
        $related = $this->kb->relatedForTicket($ticket, $user, 3);
        $search = $this->kb->search(KbArticle::query()->readableBy($user), $ticket->title.' '.mb_substr($ticket->description, 0, 200))
            ->whereNotIn('kb_articles.id', $related->pluck('id'))->limit(2)->get();

        return KbArticle::query()->readableBy($user)->whereIn('id', $related->pluck('id')->merge($search->pluck('id')))->get();
    }

    /** Referensi artikel divalidasi: hanya ID yang benar-benar dikirim ke AI (tidak ada referensi karangan). */
    private function validRefs(mixed $ids, Collection $articles): array
    {
        $allowed = $articles->keyBy('id');

        return collect(is_array($ids) ? $ids : [])->filter(fn ($id) => is_int($id) && $allowed->has($id))->unique()
            ->map(fn ($id) => ['id' => $id, 'title' => $allowed[$id]->title, 'url' => route('kb.show', $allowed[$id])])
            ->values()->all();
    }

    private function ask(string $feature, User $user, string $prompt, Ticket $ticket): array
    {
        $result = $this->gateway->complete($feature, $user, AiContextBuilder::SYSTEM_RULES, $prompt, $ticket->id);
        $data = AiJson::decode($result->text);
        if ($data === null) {
            $this->gateway->markInvalid($feature, $user, $ticket->id);
            throw new AiUnavailableException('Jawaban AI tidak dapat diproses. Silakan coba lagi.');
        }

        return $data;
    }
}
