<?php

namespace App\Services;

use App\Enums\KbStatus;
use App\Models\KbArticle;
use App\Models\KbTag;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Knowledge Base: CRUD, siklus status, pencarian berbasis database (FULLTEXT + fallback LIKE),
 * dan artikel terkait tiket. Tidak memakai vector database (dapat ditambahkan kemudian).
 */
class KnowledgeBaseService
{
    public const SEARCH_COLUMNS = ['title', 'problem', 'symptoms', 'steps', 'resolution'];

    private const FIELDS = ['title', 'category_id', 'subcategory_id', 'problem', 'symptoms', 'root_cause', 'steps', 'resolution', 'visibility', 'source_ticket_id'];

    public function __construct(private readonly AuditService $audit) {}

    public function create(array $data, User $author): KbArticle
    {
        return DB::transaction(function () use ($data, $author) {
            $article = new KbArticle(Arr::only($data, self::FIELDS));
            $article->slug = $this->uniqueSlug($data['title']);
            $article->status = KbStatus::DRAFT;
            $article->author_id = $author->id;
            $article->updated_by = $author->id;
            $article->save();
            $this->syncTags($article, $data['tags'] ?? '');

            $this->audit->log('kb.created', $article, "Membuat artikel KB: {$article->title}", newValues: Arr::only($article->getAttributes(), ['title', 'visibility', 'category_id']));

            if (! empty($data['publish'])) {
                $this->publish($article, $author);
            }

            return $article;
        });
    }

    public function update(KbArticle $article, array $data, User $actor): KbArticle
    {
        return DB::transaction(function () use ($article, $data, $actor) {
            $before = Arr::only($article->getAttributes(), self::FIELDS);
            $article->fill(Arr::only($data, self::FIELDS));
            $article->updated_by = $actor->id;
            $article->save();
            $this->syncTags($article, $data['tags'] ?? '');
            $this->audit->logChanges('kb.updated', $article, $before, "Mengubah artikel KB: {$article->title}");

            return $article;
        });
    }

    public function publish(KbArticle $article, User $actor): KbArticle
    {
        return $this->transition($article, KbStatus::PUBLISHED, $actor, 'kb.published');
    }

    public function archive(KbArticle $article, User $actor): KbArticle
    {
        return $this->transition($article, KbStatus::ARCHIVED, $actor, 'kb.archived');
    }

    public function toDraft(KbArticle $article, User $actor): KbArticle
    {
        return $this->transition($article, KbStatus::DRAFT, $actor, 'kb.unpublished');
    }

    /**
     * Pencarian. FULLTEXT (InnoDB, natural language) untuk relevansi; bila kosong (mis. kata < 3 huruf
     * atau indeks belum ter-commit) jatuh ke LIKE per kata.
     */
    public function search(Builder $query, ?string $term): Builder
    {
        $term = trim(Str::limit((string) $term, 200, ''));
        if ($term === '') {
            return $query;
        }

        $match = 'MATCH ('.implode(',', self::SEARCH_COLUMNS).') AGAINST (? IN NATURAL LANGUAGE MODE)';
        // Relevansi > 0: kata yang muncul di semua artikel (mis. "tidak") tidak dianggap kecocokan.
        $fulltext = (clone $query)
            ->whereRaw("{$match} > 0", [$term])
            ->select('kb_articles.*')
            ->selectRaw("{$match} AS relevance", [$term])
            ->reorder()
            ->orderByDesc('relevance');

        if ((clone $fulltext)->exists()) {
            return $fulltext;
        }

        $words = collect(preg_split('/\s+/', Str::lower($term)))
            ->map(fn ($w) => trim($w, " \t\n\r\0\x0B.,;:!?\"'()"))
            ->filter(fn ($w) => mb_strlen($w) >= 3)
            ->unique()->take(8)->values();
        if ($words->isEmpty()) {
            $words = collect([$term]);
        }

        return $query->where(function (Builder $q) use ($words) {
            foreach ($words as $word) {
                $like = '%'.addcslashes($word, '%_\\').'%';
                foreach (self::SEARCH_COLUMNS as $col) {
                    $q->orWhere($col, 'like', $like);
                }
                $q->orWhereHas('tags', fn ($t) => $t->where('name', 'like', $like));
            }
        });
    }

    /**
     * Artikel terkait tiket: subkategori sama → kategori sama → kecocokan teks judul tiket.
     * Hanya artikel yang boleh dibaca $viewer.
     *
     * @return Collection<int, KbArticle>
     */
    public function relatedForTicket(Ticket $ticket, User $viewer, int $limit = 5): Collection
    {
        $base = fn () => KbArticle::query()->readableBy($viewer)->select('id', 'title', 'slug', 'category_id', 'subcategory_id', 'visibility', 'status');

        $results = collect();
        if ($ticket->subcategory_id) {
            $results = $results->merge($base()->where('subcategory_id', $ticket->subcategory_id)->latest('updated_at')->limit($limit)->get());
        }
        if ($results->count() < $limit) {
            $results = $results->merge($base()->where('category_id', $ticket->category_id)->whereNotIn('id', $results->pluck('id'))->latest('updated_at')->limit($limit - $results->count())->get());
        }
        if ($results->count() < $limit) {
            $results = $results->merge($this->search($base(), $ticket->title)->whereNotIn('kb_articles.id', $results->pluck('id'))->limit($limit - $results->count())->get());
        }

        return $results->unique('id')->take($limit)->values();
    }

    /** Hitung tampilan sekali per sesi per artikel; penulis tidak dihitung. */
    public function recordView(KbArticle $article, User $viewer): void
    {
        $key = 'kb_viewed.'.$article->id;
        if ($viewer->id === $article->author_id || session()->has($key)) {
            return;
        }
        session()->put($key, true);
        KbArticle::whereKey($article->id)->increment('view_count');
    }

    private function transition(KbArticle $article, KbStatus $to, User $actor, string $action): KbArticle
    {
        $from = $article->status;
        $article->status = $to;
        $article->updated_by = $actor->id;
        if ($to === KbStatus::PUBLISHED) {
            $article->published_at ??= now();
            $article->archived_at = null;
        }
        if ($to === KbStatus::ARCHIVED) {
            $article->archived_at = now();
        }
        $article->save();
        $this->audit->log($action, $article, "{$article->title}: {$from->value} → {$to->value}", ['status' => $from->value], ['status' => $to->value]);

        return $article;
    }

    /** Tag dari input "printer, epson, kasir". */
    private function syncTags(KbArticle $article, string|array|null $input): void
    {
        $names = collect(is_array($input) ? $input : explode(',', (string) $input))
            ->map(fn ($t) => Str::limit(trim(preg_replace('/\s+/', ' ', (string) $t)), 50, ''))
            ->filter()->unique(fn ($t) => Str::slug($t))->take(15);

        $ids = $names->map(fn ($name) => KbTag::firstOrCreate(['slug' => Str::slug($name)], ['name' => Str::lower($name)])->id);
        $article->tags()->sync($ids->all());
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::limit(Str::slug($title), 200, '') ?: 'artikel';
        $slug = $base;
        $i = 2;
        while (KbArticle::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
