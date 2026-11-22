<?php

namespace Tests\Feature\Security;

use App\Models\KbArticle;
use App\Services\KnowledgeBaseService;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Matriks akses otomatis untuk SELURUH rute GET aplikasi — rute baru otomatis ikut teruji.
 */
class AccessMatrixTest extends TestCase
{
    private const PUBLIC_URIS = ['login', 'up', 'manifest.webmanifest', 'branding/logo', 'qr/{token}'];

    /** @return array<string, string> uri => url siap pakai */
    private function getRoutes(): array
    {
        Storage::fake('local');
        $owner = $this->makeUser();
        $ticket = $this->makeTicket($owner, files: [UploadedFile::fake()->image('a.png')]);
        $article = app(KnowledgeBaseService::class)->create(['title' => 'Artikel uji', 'problem' => 'x', 'steps' => 'x', 'visibility' => 'INTERNAL', 'publish' => true], $this->makeIt());
        $params = [
            'ticket' => $ticket->public_id, 'attachment' => $ticket->attachments()->first()->id, 'article' => $article->slug,
            'user' => $owner->id, 'unit' => $this->unit()->id, 'id' => 'tidak-ada',
        ];

        return collect(RouteFacade::getRoutes()->getRoutes())
            ->filter(fn (Route $r) => in_array('GET', $r->methods(), true) && ! str_starts_with($r->uri(), '_') && ! str_starts_with($r->uri(), 'livewire') && ! str_starts_with($r->uri(), 'storage'))
            ->mapWithKeys(function (Route $r) use ($params) {
                $url = preg_replace_callback('/\{(\w+)\??\}/', fn ($m) => $params[$m[1]] ?? 'x', $r->uri());

                return [$r->uri() => '/'.ltrim($url, '/')];
            })->all();
    }

    public function test_guests_are_redirected_to_login_on_every_non_public_route(): void
    {
        foreach ($this->getRoutes() as $uri => $url) {
            if (in_array($uri, self::PUBLIC_URIS, true)) {
                continue;
            }
            auth()->logout();
            $this->get($url)->assertRedirect('/login');
        }
    }

    public function test_user_role_can_never_open_it_or_admin_pages(): void
    {
        $routes = $this->getRoutes();
        $user = $this->makeUser();
        $checked = 0;

        foreach ($routes as $uri => $url) {
            if (str_starts_with($uri, 'it/') || str_starts_with($uri, 'admin/') || in_array($uri, ['kb/baru', 'kb/tanya', 'kb/{article}/ubah'], true)) {
                $status = $this->actingAs($user)->get($url)->getStatusCode();
                $this->assertContains($status, [403, 404], "USER mendapat {$status} pada {$url}");
                $checked++;
            }
        }
        $this->assertGreaterThanOrEqual(25, $checked, 'Semua rute it/ dan admin/ ikut diperiksa');
    }

    public function test_it_role_can_never_open_admin_pages(): void
    {
        $it = $this->makeIt();

        foreach ($this->getRoutes() as $uri => $url) {
            if (str_starts_with($uri, 'admin/')) {
                $this->assertSame(403, $this->actingAs($it)->get($url)->getStatusCode(), "IT dapat membuka {$url}");
            }
        }
    }

    public function test_security_headers_are_present(): void
    {
        $this->get('/login')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_internal_kb_article_is_hidden_from_user_by_slug(): void
    {
        $article = KbArticle::query()->first() ?? app(KnowledgeBaseService::class)->create(['title' => 'Internal', 'problem' => 'x', 'steps' => 'x', 'visibility' => 'INTERNAL', 'publish' => true], $this->makeIt());

        $this->actingAs($this->makeUser())->get("/kb/{$article->slug}")->assertNotFound();
    }
}
