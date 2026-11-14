<?php

namespace Tests\Feature\Auth;

use App\Services\UserService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordAndProfileTest extends TestCase
{
    public function test_user_with_temporary_password_is_forced_to_change_it(): void
    {
        $user = $this->makeUser(attributes: ['must_change_password' => true]);

        $this->actingAs($user)->get('/user/dashboard')->assertRedirect(route('password.edit'));
        $this->actingAs($user)->get('/profil')->assertRedirect(route('password.edit'));
        $this->actingAs($user)->get('/password')->assertOk();
    }

    public function test_user_can_change_password(): void
    {
        $user = $this->makeUser(attributes: ['must_change_password' => true]);

        $this->actingAs($user)->put('/password', [
            'current_password' => 'password123',
            'password' => 'BaruSekali99',
            'password_confirmation' => 'BaruSekali99',
        ])->assertRedirect(route('user.dashboard'));

        $user->refresh();
        $this->assertTrue(Hash::check('BaruSekali99', $user->password));
        $this->assertFalse($user->must_change_password);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.password_changed', 'user_id' => $user->id]);
    }

    public function test_change_password_requires_correct_current_password_and_strong_new_password(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->put('/password', [
            'current_password' => 'salah',
            'password' => 'pendek',
            'password_confirmation' => 'beda',
        ])->assertSessionHasErrors(['current_password', 'password']);

        $this->assertTrue(Hash::check('password123', $user->fresh()->password));
    }

    public function test_password_is_stored_hashed(): void
    {
        $user = $this->makeUser();

        $this->assertNotSame('password123', $user->getAttributes()['password']);
        $this->assertTrue(Hash::isHashed($user->getAttributes()['password']));
    }

    public function test_user_can_update_own_contact_data_but_not_role_or_username(): void
    {
        $user = $this->makeUser(attributes: ['username' => 'staf.ho']);
        $roleId = $user->role_id;

        $this->actingAs($user)->put('/profil', [
            'name' => 'Nama Baru',
            'email' => 'baru@almegajaya.com',
            'phone' => '0812-3456-7890',
            'username' => 'hacker',
            'role_id' => 999,
        ])->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('Nama Baru', $user->name);
        $this->assertSame('staf.ho', $user->username);
        $this->assertSame($roleId, $user->role_id);
    }

    public function test_profile_page_shows_units(): void
    {
        $user = $this->makeUser(unitCode: 'DR');

        $this->actingAs($user)->get('/profil')->assertOk()->assertSee('Driam Resort');
    }

    public function test_changing_password_terminates_other_sessions_but_keeps_current(): void
    {
        config(['session.driver' => 'database']);
        $user = $this->makeUser();
        DB::table('sessions')->insert([
            ['id' => 'sesi-hp-lain', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()],
            ['id' => 'sesi-saat-ini', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()],
        ]);

        app(UserService::class)->changeOwnPassword($user, 'BaruSekali99', 'sesi-saat-ini');

        $this->assertDatabaseMissing('sessions', ['id' => 'sesi-hp-lain']);
        $this->assertDatabaseHas('sessions', ['id' => 'sesi-saat-ini']);
    }

    public function test_admin_password_reset_terminates_all_sessions_of_target(): void
    {
        config(['session.driver' => 'database']);
        $user = $this->makeUser();
        DB::table('sessions')->insert(['id' => 'sesi-user', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($this->makeAdmin())->put("/admin/users/{$user->id}/password", [
            'password' => 'ResetBaru123', 'password_confirmation' => 'ResetBaru123',
        ]);

        $this->assertDatabaseMissing('sessions', ['id' => 'sesi-user']);
    }
}
