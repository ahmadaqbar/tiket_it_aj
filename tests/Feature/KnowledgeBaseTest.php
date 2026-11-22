<?php

namespace Tests\Feature;

use App\Enums\KbStatus;
use App\Models\KbArticle;
use App\Models\User;
use App\Services\KnowledgeBaseService;
use App\Services\SettingService;
use Tests\TestCase;

class KnowledgeBaseTest extends TestCase
{
    private function article(User $author, array $data = [], bool $publish = true): KbArticle
    {
        return app(KnowledgeBaseService::class)->create(array_merge([
            'title' => 'Printer kasir tidak mencetak struk',
            'category_id' => $this->category('Hardware')->id,
            'subcategory_id' => $this->subcategory('Hardware', 'Printer')->id,
            'problem' => 'Printer thermal kasir tidak mengeluarkan struk.',
            'symptoms' => 'Lampu error berkedip merah',
            'steps' => "1. Cek kertas\n2. Matikan dan nyalakan printer\n3. Cek kabel USB",
            'resolution' => 'Ganti gulungan kertas thermal.',
            'visibility' => 'PUBLIC',
            'tags' => 'printer, kasir, Epson',
            'publish' => $publish,
        ], $data), $author);
    }

    public function test_it_can_create_publish_edit_and_archive_article_with_audit(): void
    {
        $it = $this->makeIt();

        $this->actingAs($it)->post('/kb', [
            'title' => 'Reset password email karyawan', 'problem' => 'User lupa password email.',
            'steps' => "1. Buka panel email\n2. Reset password", 'visibility' => 'INTERNAL', 'tags' => 'email, password',
        ])->assertRedirect();

        $article = KbArticle::firstOrFail();
        $this->assertSame(KbStatus::DRAFT, $article->status);
        $this->assertSame('reset-password-email-karyawan', $article->slug);
        $this->assertSame(['email', 'password'], $article->tags->pluck('name')->all());

        $this->actingAs($it)->post("/kb/{$article->slug}/publish")->assertSessionHasNoErrors();
        $this->assertSame(KbStatus::PUBLISHED, $article->fresh()->status);
        $this->assertNotNull($article->fresh()->published_at);

        $this->actingAs($it)->put("/kb/{$article->slug}", [
            'title' => 'Reset password email', 'problem' => 'User lupa password.', 'steps' => '1. Reset', 'visibility' => 'INTERNAL', 'tags' => 'email',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Reset password email', $article->fresh()->title);
        $this->assertSame($article->slug, $article->fresh()->slug, 'Slug stabil agar tautan tidak rusak');

        $this->actingAs($it)->post("/kb/{$article->slug}/archive");
        $this->assertSame(KbStatus::ARCHIVED, $article->fresh()->status);

        foreach (['kb.created', 'kb.published', 'kb.updated', 'kb.archived'] as $action) {
            $this->assertDatabaseHas('audit_logs', ['action' => $action, 'entity_id' => $article->id]);
        }
    }

    public function test_slug_is_unique(): void
    {
        $it = $this->makeIt();
        $a = $this->article($it);
        $b = $this->article($it);

        $this->assertSame('printer-kasir-tidak-mencetak-struk', $a->slug);
        $this->assertSame('printer-kasir-tidak-mencetak-struk-2', $b->slug);
    }

    public function test_user_sees_only_published_public_articles(): void
    {
        $it = $this->makeIt();
        $public = $this->article($it, ['title' => 'Artikel publik untuk user']);
        $internal = $this->article($it, ['title' => 'Artikel internal IT saja', 'visibility' => 'INTERNAL']);
        $draft = $this->article($it, ['title' => 'Artikel draft belum terbit'], publish: false);
        $archived = $this->article($it, ['title' => 'Artikel lama diarsipkan']);
        app(KnowledgeBaseService::class)->archive($archived, $it);
        $user = $this->makeUser();

        $this->actingAs($user)->get('/kb')->assertOk()
            ->assertSee($public->title)->assertDontSee($internal->title)->assertDontSee($draft->title)->assertDontSee($archived->title);

        $this->actingAs($user)->get("/kb/{$public->slug}")->assertOk();
        foreach ([$internal, $draft, $archived] as $hidden) {
            $this->actingAs($user)->get("/kb/{$hidden->slug}")->assertNotFound();
        }
    }

    public function test_it_sees_internal_and_draft_articles(): void
    {
        $it = $this->makeIt();
        $internal = $this->article($it, ['title' => 'Artikel internal IT saja', 'visibility' => 'INTERNAL']);
        $draft = $this->article($it, ['title' => 'Artikel draft belum terbit'], publish: false);

        $this->actingAs($this->makeAdmin())->get('/kb')->assertSee($internal->title)->assertSee($draft->title);
        $this->actingAs($this->makeIt())->get("/kb/{$draft->slug}")->assertOk();
    }

    public function test_user_cannot_create_or_edit_articles(): void
    {
        $article = $this->article($this->makeIt());
        $user = $this->makeUser();

        $this->actingAs($user)->get('/kb/baru')->assertForbidden();
        $this->actingAs($user)->post('/kb', ['title' => 'Coba', 'problem' => 'x', 'steps' => 'x', 'visibility' => 'PUBLIC'])->assertForbidden();
        $this->actingAs($user)->put("/kb/{$article->slug}", ['title' => 'Diubah'])->assertForbidden();
        $this->actingAs($user)->post("/kb/{$article->slug}/archive")->assertForbidden();
        $this->assertSame(KbStatus::PUBLISHED, $article->fresh()->status);
    }

    public function test_search_by_symptom_and_tag_filter(): void
    {
        $it = $this->makeIt();
        $printer = $this->article($it);
        $wifi = $this->article($it, ['title' => 'WiFi tamu tidak dapat IP', 'category_id' => $this->category('Network & Internet')->id,
            'subcategory_id' => $this->subcategory('Network & Internet', 'WiFi')->id, 'problem' => 'Perangkat tamu tidak mendapat alamat IP.',
            'symptoms' => 'Terhubung tanpa internet', 'steps' => '1. Cek DHCP pool', 'tags' => 'wifi, dhcp']);

        $results = fn ($q) => $this->actingAs($it)->get('/kb?'.$q)->viewData('articles')->pluck('id')->all();

        $this->assertSame([$printer->id], $results('q=lampu+error+berkedip'));
        $this->assertSame([$wifi->id], $results('q=DHCP'));
        $this->assertSame([$wifi->id], $results('tag=dhcp'));
        $this->assertSame([$printer->id], $results('category_id='.$this->category('Hardware')->id));
    }

    public function test_view_count_increments_once_per_session_and_not_for_author(): void
    {
        $author = $this->makeIt();
        $article = $this->article($author);
        $reader = $this->makeUser();

        $this->actingAs($author)->get("/kb/{$article->slug}");
        $this->assertSame(0, $article->fresh()->view_count);

        $this->actingAs($reader)->get("/kb/{$article->slug}");
        $this->actingAs($reader)->get("/kb/{$article->slug}");
        $this->assertSame(1, $article->fresh()->view_count);
    }

    public function test_related_articles_on_ticket_detail_respect_visibility(): void
    {
        $it = $this->makeIt();
        $printer = $this->article($it, ['title' => 'Panduan printer kasir', 'visibility' => 'INTERNAL']);
        $this->article($it, ['title' => 'Draft printer rahasia'], publish: false);
        $ticket = $this->makeTicket($this->makeUser(), ['subcategory_id' => $this->subcategory('Hardware', 'Printer')->id]);

        $this->actingAs($it)->get("/it/tiket/{$ticket->public_id}")->assertSee('Panduan printer kasir')->assertDontSee('Draft printer rahasia');

        // Untuk user, internal tidak termasuk artikel terkait.
        $this->assertCount(0, app(KnowledgeBaseService::class)->relatedForTicket($ticket, $ticket->reporter));
        $this->assertCount(1, app(KnowledgeBaseService::class)->relatedForTicket($ticket, $it));
        $this->assertTrue(app(KnowledgeBaseService::class)->relatedForTicket($ticket, $it)->contains('id', $printer->id));
    }

    public function test_create_article_from_ticket_prefills_without_internal_notes(): void
    {
        $it = $this->makeIt();
        $ticket = $this->makeTicket($this->makeUser(), ['title' => 'Scanner tidak terdeteksi', 'description' => 'Scanner di admin tidak terbaca di PC.']);
        $this->actingAs($it)->post("/tiket/{$ticket->public_id}/balasan", ['message' => 'Password admin PC: rahasia', 'visibility' => 'INTERNAL']);
        $this->actingAs($it)->post("/tiket/{$ticket->public_id}/balasan", ['message' => 'Driver scanner diinstal ulang, sudah normal.', 'visibility' => 'PUBLIC']);

        $this->actingAs($it)->get("/kb/baru?from_ticket={$ticket->public_id}")->assertOk()
            ->assertSee('Scanner tidak terdeteksi')->assertSee('Driver scanner diinstal ulang')->assertDontSee('Password admin PC');
    }

    public function test_public_help_center_can_be_disabled_for_users(): void
    {
        $article = $this->article($this->makeIt());
        app(SettingService::class)->set('kb.public_enabled', false);

        $this->actingAs($this->makeUser())->get('/kb')->assertForbidden();
        $this->actingAs($this->makeUser())->get("/kb/{$article->slug}")->assertNotFound();
        $this->actingAs($this->makeIt())->get('/kb')->assertOk();
    }

    public function test_article_content_is_escaped(): void
    {
        $article = $this->article($this->makeIt(), ['problem' => '<script>alert("xss")</script>']);

        $this->actingAs($this->makeUser())->get("/kb/{$article->slug}")
            ->assertDontSee('<script>alert("xss")</script>', false)->assertSee('&lt;script&gt;', false);
    }

    public function test_subcategory_must_match_category(): void
    {
        $this->actingAs($this->makeIt())->post('/kb', [
            'title' => 'Artikel salah kategori', 'problem' => 'x', 'steps' => 'x', 'visibility' => 'PUBLIC',
            'category_id' => $this->category('Hardware')->id, 'subcategory_id' => $this->subcategory('Network & Internet', 'WiFi')->id,
        ])->assertSessionHasErrors('subcategory_id');
    }
}
