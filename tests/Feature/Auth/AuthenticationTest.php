<?php

namespace Tests\Feature\Auth;

use App\Enums\RoleCode;
use App\Models\AuditLog;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    public function test_login_page_is_accessible_and_there_is_no_public_registration(): void
    {
        $this->get('/login')->assertOk()->assertSee('Masuk ke akun Anda');
        $this->get('/register')->assertNotFound();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/user/dashboard')->assertRedirect('/login');
        $this->get('/admin/users')->assertRedirect('/login');
    }

    public function test_user_can_login_with_username(): void
    {
        $user = $this->makeUser(attributes: ['username' => 'staf.ho']);

        $this->post('/login', ['login' => 'staf.ho', 'password' => 'password123'])
            ->assertRedirect(route('user.dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_user_can_login_with_email(): void
    {
        $user = $this->makeUser(attributes: ['email' => 'staf@almegajaya.com']);

        $this->post('/login', ['login' => 'staf@almegajaya.com', 'password' => 'password123']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_it_staff_is_redirected_to_it_dashboard(): void
    {
        $it = $this->makeIt(['username' => 'it.budi']);

        $this->post('/login', ['login' => 'it.budi', 'password' => 'password123'])
            ->assertRedirect(route('it.dashboard'));
    }

    public function test_login_fails_with_wrong_password_and_is_audited(): void
    {
        $this->makeUser(attributes: ['username' => 'staf.ho']);

        $this->from('/login')->post('/login', ['login' => 'staf.ho', 'password' => 'salah'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['login' => 'Username/email atau password salah.']);

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login_failed', 'user_id' => null]);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $this->makeUser(attributes: ['username' => 'nonaktif', 'is_active' => false]);

        $this->post('/login', ['login' => 'nonaktif', 'password' => 'password123'])
            ->assertSessionHasErrors('login');

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login_blocked']);
    }

    public function test_login_is_rate_limited_after_five_failed_attempts(): void
    {
        $this->makeUser(attributes: ['username' => 'staf.ho']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['login' => 'staf.ho', 'password' => 'salah']);
        }

        // Percobaan ke-6 ditolak walau password benar.
        $this->post('/login', ['login' => 'staf.ho', 'password' => 'password123'])
            ->assertSessionHasErrors('login');
        $this->assertGuest();
        $this->assertStringContainsString('Terlalu banyak percobaan', session('errors')->first('login'));

        RateLimiter::clear('staf.ho|127.0.0.1');
    }

    public function test_logout_invalidates_session_and_is_audited(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
        $this->assertTrue(AuditLog::where('action', 'auth.logout')->where('user_id', $user->id)->exists());
    }

    public function test_successful_login_is_audited_with_ip_and_user_agent(): void
    {
        $user = $this->makeUser(attributes: ['username' => 'staf.ho']);

        $this->withHeader('User-Agent', 'PHPUnit Browser')
            ->post('/login', ['login' => 'staf.ho', 'password' => 'password123']);

        $log = AuditLog::where('action', 'auth.login')->firstOrFail();
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame('User', $log->entity_type);
        $this->assertSame('127.0.0.1', $log->ip_address);
        $this->assertSame('PHPUnit Browser', $log->user_agent);
    }

    public function test_deactivated_user_is_logged_out_on_next_request(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user)->get('/user/dashboard')->assertOk();

        $user->update(['is_active' => false]);

        $this->actingAs($user->fresh())->get('/user/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_authenticated_user_visiting_login_is_redirected_home(): void
    {
        $this->actingAs($this->makeUser(RoleCode::IT))->get('/login')->assertRedirect(route('it.dashboard'));
    }
}
