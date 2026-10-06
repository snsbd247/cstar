<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_log_in_with_email_and_lands_in_admin_app(): void
    {
        $user = $this->userWithRole(Role::Receptionist);

        $this->withHeaders($this->spaHeaders())
            ->postJson('/api/v1/auth/login', ['login' => $user->email, 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.primary_role', 'receptionist')
            ->assertJsonPath('data.home_path', '/app')
            ->assertJsonFragment(['patients.create']);

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'login', 'user_id' => $user->id]);
    }

    public function test_parent_can_log_in_with_phone_and_lands_in_portal(): void
    {
        $parent = $this->userWithRole(Role::Parent);

        $this->withHeaders($this->spaHeaders())
            ->postJson('/api/v1/auth/login', ['login' => $parent->phone, 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.home_path', '/portal')
            ->assertJsonPath('data.permissions', ['portal.access']);
    }

    public function test_trainer_and_therapist_land_in_their_own_apps(): void
    {
        $this->assertSame('/trainer', $this->userWithRole(Role::Trainer)->homePath());
        $this->assertSame('/therapist', $this->userWithRole(Role::Therapist)->homePath());
    }

    public function test_wrong_password_is_rejected_and_audited(): void
    {
        $user = $this->userWithRole(Role::Trainer);

        $this->withHeaders($this->spaHeaders())
            ->postJson('/api/v1/auth/login', ['login' => $user->email, 'password' => 'wrong-password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('login');

        $this->assertGuest();
        $this->assertTrue(AuditLog::where('action', 'login_failed')->where('auditable_id', $user->id)->exists());
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        $user = $this->userWithRole(Role::Therapist);
        $user->update(['status' => UserStatus::Inactive]);

        $this->withHeaders($this->spaHeaders())
            ->postJson('/api/v1/auth/login', ['login' => $user->email, 'password' => 'password'])
            ->assertUnprocessable();

        $this->assertGuest();
    }

    public function test_login_is_rate_limited(): void
    {
        $user = $this->userWithRole(Role::Receptionist);

        for ($i = 0; $i < 5; $i++) {
            $this->withHeaders($this->spaHeaders())
                ->postJson('/api/v1/auth/login', ['login' => $user->email, 'password' => 'nope'])
                ->assertUnprocessable();
        }

        $this->withHeaders($this->spaHeaders())
            ->postJson('/api/v1/auth/login', ['login' => $user->email, 'password' => 'password'])
            ->assertTooManyRequests();
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_me_returns_the_current_user_with_branches(): void
    {
        $user = $this->userWithRole(Role::BranchAdmin);

        $this->actingAs($user)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonCount(1, 'data.branches');
    }

    public function test_user_deactivated_mid_session_is_blocked(): void
    {
        $user = $this->userWithRole(Role::Receptionist);
        $user->update(['status' => UserStatus::Suspended]);

        $this->actingAs($user)->getJson('/api/v1/auth/me')->assertForbidden();
    }

    public function test_user_can_log_out(): void
    {
        $user = $this->userWithRole(Role::Accountant);

        $this->actingAs($user)
            ->withHeaders($this->spaHeaders())
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->assertGuest('web');
    }

    public function test_user_can_change_password(): void
    {
        $user = $this->userWithRole(Role::Trainer);
        $user->update(['must_change_password' => true]);

        $this->actingAs($user)
            ->putJson('/api/v1/auth/password', [
                'current_password' => 'password',
                'password' => 'NewPass123',
                'password_confirmation' => 'NewPass123',
            ])
            ->assertOk();

        $this->assertFalse($user->fresh()->must_change_password);
    }
}
