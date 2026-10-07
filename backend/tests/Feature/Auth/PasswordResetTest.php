<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\OutboundMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Sprint 22 (D5): a forgotten password is reset with a 6-digit SMS code. */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private function lastCode(): string
    {
        preg_match('/(\d{6})/', OutboundMessage::latest('id')->value('body'), $m);

        return $m[1];
    }

    public function test_code_by_sms_resets_the_password_once(): void
    {
        $parent = $this->userWithRole(Role::Parent, Branch::factory()->create());
        $parent->update(['phone' => '01712345678']);

        // Unknown numbers get the same answer and no SMS.
        $this->postJson('/api/v1/auth/forgot', ['phone' => '01999999999'])->assertOk();
        $this->assertSame(0, OutboundMessage::count());

        $this->postJson('/api/v1/auth/forgot', ['phone' => '+8801712345678'])->assertOk()->assertJsonPath('minutes', 10);
        $this->assertSame('01712345678', OutboundMessage::sole()->to);
        $code = $this->lastCode();
        $wrong = $code === '111111' ? '222222' : '111111';

        $this->postJson('/api/v1/auth/reset', ['phone' => '01712345678', 'code' => $wrong, 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123'])
            ->assertJsonValidationErrors('code');
        $this->postJson('/api/v1/auth/reset', ['phone' => '01712345678', 'code' => $code, 'password' => 'short', 'password_confirmation' => 'short'])
            ->assertJsonValidationErrors('password');
        $this->postJson('/api/v1/auth/reset', ['phone' => '01712345678', 'code' => $code, 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123'])->assertOk();
        $this->assertTrue(Hash::check('NewPass123', $parent->fresh()->password));

        // The code works only once.
        $this->postJson('/api/v1/auth/reset', ['phone' => '01712345678', 'code' => $code, 'password' => 'Other1234', 'password_confirmation' => 'Other1234'])
            ->assertJsonValidationErrors('code');
        $this->postJson('/api/v1/auth/login', ['login' => '01712345678', 'password' => 'NewPass123'], $this->spaHeaders())->assertOk();
    }

    public function test_five_wrong_codes_burn_the_code(): void
    {
        $user = $this->userWithRole(Role::Receptionist, Branch::factory()->create());
        $user->update(['phone' => '01812345678']);
        $this->postJson('/api/v1/auth/forgot', ['phone' => '01812345678'])->assertOk();
        $code = $this->lastCode();
        $wrong = $code === '111111' ? '222222' : '111111';
        foreach (range(1, 5) as $i) {
            $this->postJson('/api/v1/auth/reset', ['phone' => '01812345678', 'code' => $wrong, 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123']);
        }
        $this->postJson('/api/v1/auth/reset', ['phone' => '01812345678', 'code' => $code, 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123'])
            ->assertJsonValidationErrors('code');
    }
}
