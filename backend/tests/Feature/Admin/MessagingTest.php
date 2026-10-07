<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Guardian;
use App\Models\OutboundMessage;
use App\Models\Patient;
use App\Models\Setting;
use App\Services\Messaging\GreenWebGateway;
use App\Services\Messaging\Messenger;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Sprint 18: SMS through GreenWeb (and WhatsApp), test mode, logs. */
class MessagingTest extends TestCase
{
    use RefreshDatabase;

    private function settings(array $overrides = []): array
    {
        return [
            'sms_enabled' => '1', 'sms_driver' => 'greenweb', 'greenweb_token' => 'secret-token-123', 'whatsapp_enabled' => '0', 'whatsapp_driver' => 'log',
            'whatsapp_template' => 'cstar_notice', 'whatsapp_language' => 'bn', 'prefix' => 'C-STAR', 'kinds' => ['payment.received', 'voucher.submitted'], 'staff_sms' => '0', ...$overrides,
        ];
    }

    private function parentOf(Patient $child): \App\Models\User
    {
        $parent = $this->userWithRole(Role::Parent, Branch::find($child->home_branch_id));
        $parent->update(['phone' => '01712345678']);
        $guardian = Guardian::factory()->create(['user_id' => $parent->id]);
        $child->guardians()->attach($guardian->id, ['relationship' => 'mother', 'is_primary' => true, 'can_access_portal' => true]);

        return $parent;
    }

    public function test_parent_messages_go_to_greenweb_and_the_token_stays_secret(): void
    {
        Http::fake([GreenWebGateway::SEND_URL => Http::response([['to' => '01712345678', 'message' => 'x', 'status' => 'SENT', 'statusmsg' => 'SMS Sent Successfully']])]);
        $admin = $this->userWithRole(Role::SuperAdmin);
        $this->actingAs($admin)->putJson('/api/v1/messaging/settings', $this->settings())->assertOk()
            ->assertJsonPath('data.values.greenweb_token', '')->assertJsonPath('data.values.greenweb_token_saved', true);
        $this->assertStringNotContainsString('secret-token-123', Setting::where('key', 'greenweb_token')->value('value'), 'Stored encrypted');

        $child = Patient::factory()->create(['name' => 'Sara Khan']);
        $this->parentOf($child);
        app(NotificationService::class)->parentsTemplate($child, 'payment.received', ['amount' => '৳৫০০', 'receipt_no' => 'RCP-1']);
        // A kind that is not ticked never goes by SMS.
        app(NotificationService::class)->parentsTemplate($child, 'invoice.issued', ['invoice_no' => 'INV-1', 'amount' => '৳৫০০']);

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $r) => $r['token'] === 'secret-token-123' && $r['to'] === '01712345678' && str_starts_with($r['message'], 'C-STAR: পেমেন্ট গ্রহণ করা হয়েছে — ৳৫০০'));
        $message = OutboundMessage::firstOrFail();
        $this->assertSame('sent', $message->status);
        $this->assertSame('payment.received', $message->kind);
        $this->assertSame(Messenger::segments($message->body), $message->segments);

        $log = $this->getJson('/api/v1/messaging/log')->assertOk();
        $this->assertSame(1, $log->json('month.sent'));
    }

    public function test_failures_are_logged_and_can_be_sent_again(): void
    {
        Http::fakeSequence(GreenWebGateway::SEND_URL)->push('Error: Invalid token', 200)->push([['status' => 'SENT']]);
        $admin = $this->userWithRole(Role::SuperAdmin);
        $this->actingAs($admin)->putJson('/api/v1/messaging/settings', $this->settings())->assertOk();

        $first = $this->postJson('/api/v1/messaging/test', ['channel' => 'sms', 'to' => '+880 1812-345678'])->assertOk();
        $this->assertSame('failed', $first->json('data.status'));
        $this->assertStringContainsString('Invalid token', $first->json('data.error'));
        $this->assertSame('01812345678', $first->json('data.to'));

        $this->postJson("/api/v1/messaging/log/{$first->json('data.id')}/resend")->assertOk()->assertJsonPath('data.status', 'sent');
        $this->postJson("/api/v1/messaging/log/{$first->json('data.id')}/resend")->assertStatus(422);
        $this->postJson('/api/v1/messaging/test', ['channel' => 'sms', 'to' => '12345'])->assertUnprocessable();
    }

    public function test_test_mode_staff_rule_and_permissions(): void
    {
        Http::fake();
        $branch = Branch::factory()->create();
        $admin = $this->userWithRole(Role::SuperAdmin, $branch);
        $this->actingAs($admin)->putJson('/api/v1/messaging/settings', $this->settings(['sms_driver' => 'log']))->assertOk();

        // Staff messages stay in-app unless "staff_sms" is on.
        $approver = $this->userWithRole(Role::BranchAdmin, $branch);
        $approver->update(['phone' => '01912345678']);
        app(NotificationService::class)->send($approver->fresh(), 'voucher.submitted', 'Voucher needs approval', '৳9,000', '/app');
        $this->assertSame(0, OutboundMessage::count());

        $this->putJson('/api/v1/messaging/settings', $this->settings(['sms_driver' => 'log', 'staff_sms' => '1']))->assertOk();
        app(NotificationService::class)->send($approver->fresh(), 'voucher.submitted', 'Voucher needs approval', '৳9,000', '/app');
        $this->assertSame('sent', OutboundMessage::sole()->status);
        Http::assertNothingSent(); // test mode never calls a gateway

        // Turning GreenWeb on without a token is refused.
        Setting::where('key', 'greenweb_token')->delete();
        app(\App\Services\Messaging\MessagingSettings::class)->update([]);
        $this->putJson('/api/v1/messaging/settings', $this->settings(['greenweb_token' => '']))->assertUnprocessable()->assertJsonValidationErrors('greenweb_token');

        $this->actingAs($approver)->getJson('/api/v1/messaging/settings')->assertForbidden();
        $this->actingAs($approver)->getJson('/api/v1/messaging/log')->assertOk();
    }

    public function test_segments_follow_bangla_and_english_lengths(): void
    {
        $this->assertSame(1, Messenger::segments(str_repeat('a', 160)));
        $this->assertSame(2, Messenger::segments(str_repeat('a', 161)));
        $this->assertSame(1, Messenger::segments(str_repeat('ক', 70)));
        $this->assertSame(2, Messenger::segments(str_repeat('ক', 71)));
    }
}
