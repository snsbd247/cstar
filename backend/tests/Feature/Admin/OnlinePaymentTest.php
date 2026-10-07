<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\JournalLine;
use App\Models\OnlinePayment;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\OnlinePayment\OnlinePaymentSettings;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Sprint 19: parents pay online — money is booked only after the gateway confirms it. */
class OnlinePaymentTest extends TestCase
{
    use RefreshDatabase;

    private Patient $child;

    private User $parent;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $branch = Branch::factory()->create();
        $this->child = Patient::factory()->create(['home_branch_id' => $branch->id, 'name' => 'Sara Khan']);
        $this->parent = $this->userWithRole(Role::Parent, $branch);
        $this->parent->update(['phone' => '01712345678']);
        $guardian = Guardian::factory()->create(['user_id' => $this->parent->id]);
        $this->child->guardians()->attach($guardian->id, ['relationship' => 'mother', 'is_primary' => true, 'can_access_portal' => true]);
        $admin = $this->userWithRole(Role::SuperAdmin, $branch);
        $this->invoice = app(InvoiceService::class)->createAndIssue($this->child, $branch->id, [['item_type' => 'other', 'description' => 'Speech therapy', 'quantity' => 1, 'unit_price' => 1500]], $admin);
        $this->parent = $this->parent->fresh();
    }

    private function balance(string $key): float
    {
        $account = Account::where('system_key', $key)->firstOrFail();

        return round((float) JournalLine::where('account_id', $account->id)->sum('debit') - (float) JournalLine::where('account_id', $account->id)->sum('credit'), 2);
    }

    public function test_test_gateway_pays_the_invoice_once(): void
    {
        app(OnlinePaymentSettings::class)->update(['test_enabled' => '1']);
        $this->actingAs($this->parent);
        $this->getJson("/api/v1/portal/children/{$this->child->id}/online-payment")->assertOk()->assertJsonPath('data.due', 1500)->assertJsonPath('data.gateways', ['test']);
        $this->postJson("/api/v1/portal/children/{$this->child->id}/online-payment", ['gateway' => 'test', 'amount' => 2000])->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->postJson("/api/v1/portal/children/{$this->child->id}/online-payment", ['gateway' => 'bkash', 'amount' => 500])->assertUnprocessable()->assertJsonValidationErrors('gateway');

        $tran = $this->postJson("/api/v1/portal/children/{$this->child->id}/online-payment", ['gateway' => 'test', 'amount' => 1500, 'invoice_id' => $this->invoice->id])
            ->assertCreated()->json('data.tran_id');
        $this->get("/pay/test/{$tran}")->assertOk()->assertSee('৳1,500');
        $this->post("/pay/test/{$tran}", ['decision' => 'pay'])->assertRedirect();
        $this->post("/pay/test/{$tran}", ['decision' => 'pay']); // a second click books nothing more

        $this->assertSame(1, Payment::where('method', 'online')->count());
        $this->assertSame('paid', $this->invoice->fresh()->status);
        $this->assertSame(1500.0, $this->balance('online_clearing'));
        $this->getJson("/api/v1/portal/online-payments/{$tran}")->assertOk()->assertJsonPath('data.status', 'paid')->assertJsonPath('data.receipt_no', Payment::first()->receipt_no);
    }

    public function test_sslcommerz_is_booked_only_after_validation(): void
    {
        app(OnlinePaymentSettings::class)->update(['sslcommerz_enabled' => '1', 'sslcommerz_store_id' => 'teststore', 'sslcommerz_store_password' => 'secret']);
        Http::fake([
            'sandbox.sslcommerz.com/gwprocess/*' => Http::response(['status' => 'SUCCESS', 'GatewayPageURL' => 'https://sandbox.sslcommerz.com/pay/abc', 'sessionkey' => 'k']),
            // V1 is a forged success (validation names another transaction); V2 is genuine.
            'sandbox.sslcommerz.com/validator/*' => fn ($request) => $request['val_id'] === 'V1'
                ? Http::response(['status' => 'VALID', 'tran_id' => 'WRONG', 'amount' => '1000.00', 'currency' => 'BDT'])
                : Http::response(['status' => 'VALID', 'tran_id' => OnlinePayment::sole()->tran_id, 'amount' => '1000.00', 'currency' => 'BDT', 'bank_tran_id' => 'B123', 'card_type' => 'BKASH-BKash', 'risk_level' => '0']),
        ]);
        $this->actingAs($this->parent);
        $start = $this->postJson("/api/v1/portal/children/{$this->child->id}/online-payment", ['gateway' => 'sslcommerz', 'amount' => 1000])->assertCreated();
        $this->assertSame('https://sandbox.sslcommerz.com/pay/abc', $start->json('data.redirect_url'));
        $tran = $start->json('data.tran_id');

        // A forged "success" whose validation names another transaction is refused.
        $this->post('/pay/sslcommerz/success', ['tran_id' => $tran, 'val_id' => 'V1', 'status' => 'VALID'])->assertRedirect();
        $this->assertSame('failed', OnlinePayment::sole()->status);
        $this->assertSame(0, Payment::count());

        // A genuine one goes through.
        OnlinePayment::sole()->update(['status' => 'initiated']);
        $this->post('/pay/sslcommerz/ipn', ['tran_id' => $tran, 'val_id' => 'V2', 'status' => 'VALID'])->assertOk();
        $this->post('/pay/sslcommerz/success', ['tran_id' => $tran, 'val_id' => 'V2', 'status' => 'VALID'])->assertRedirect();
        $this->assertSame('paid', OnlinePayment::sole()->status);
        $this->assertSame(1, Payment::count());
        $this->assertSame(500.0, (float) $this->invoice->fresh()->due_total);
        $this->assertSame('BKASH-BKash', OnlinePayment::sole()->instrument);
    }

    public function test_sslcommerz_amount_mismatch_goes_to_review(): void
    {
        app(OnlinePaymentSettings::class)->update(['sslcommerz_enabled' => '1', 'sslcommerz_store_id' => 's', 'sslcommerz_store_password' => 'p']);
        Http::fake(['sandbox.sslcommerz.com/gwprocess/*' => Http::response(['status' => 'SUCCESS', 'GatewayPageURL' => 'https://x.test/pay'])]);
        $this->actingAs($this->parent);
        $tran = $this->postJson("/api/v1/portal/children/{$this->child->id}/online-payment", ['gateway' => 'sslcommerz', 'amount' => 1000])->json('data.tran_id');
        Http::fake(['sandbox.sslcommerz.com/validator/*' => Http::response(['status' => 'VALID', 'tran_id' => $tran, 'amount' => '10.00', 'currency' => 'BDT'])]);
        $this->post('/pay/sslcommerz/ipn', ['tran_id' => $tran, 'val_id' => 'V', 'status' => 'VALID'])->assertOk();
        $this->assertSame('review', OnlinePayment::sole()->status);
        $this->assertSame(0, Payment::count());
    }

    public function test_bkash_execute_books_to_the_bkash_merchant_account(): void
    {
        app(OnlinePaymentSettings::class)->update(['bkash_enabled' => '1', 'bkash_app_key' => 'k', 'bkash_app_secret' => 's', 'bkash_username' => 'u', 'bkash_password' => 'p']);
        Http::fake([
            '*/token/grant' => Http::response(['id_token' => 'TOKEN', 'statusCode' => '0000']),
            '*/checkout/create' => Http::response(['paymentID' => 'PAY1', 'bkashURL' => 'https://sandbox.bka.sh/pay/PAY1', 'statusCode' => '0000']),
            '*/checkout/execute' => Http::response(['paymentID' => 'PAY1', 'trxID' => 'TRX9', 'transactionStatus' => 'Completed', 'amount' => '1500', 'currency' => 'BDT']),
        ]);
        $this->actingAs($this->parent);
        $start = $this->postJson("/api/v1/portal/children/{$this->child->id}/online-payment", ['gateway' => 'bkash', 'amount' => 1500])->assertCreated();
        $this->assertSame('https://sandbox.bka.sh/pay/PAY1', $start->json('data.redirect_url'));

        $this->get('/pay/bkash/callback?paymentID=PAY1&status=success')->assertRedirect();
        $payment = Payment::sole();
        $this->assertSame('bkash', $payment->method);
        $this->assertSame('TRX9', $payment->transaction_ref);
        $this->assertSame(1500.0, $this->balance('mfs_bkash'));
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/token/grant') && $r->header('username') === ['u']);

        // Cancelled on bKash → nothing booked.
        Http::fake(['*/checkout/create' => Http::response(['paymentID' => 'PAY2', 'bkashURL' => 'https://x'])]);
        $this->invoice->refresh();
    }

    public function test_only_own_children_and_settings_hide_secrets(): void
    {
        app(OnlinePaymentSettings::class)->update(['test_enabled' => '1']);
        $other = Patient::factory()->create();
        $this->actingAs($this->parent)->getJson("/api/v1/portal/children/{$other->id}/online-payment")->assertNotFound();

        $admin = $this->userWithRole(Role::SuperAdmin);
        $this->actingAs($admin)->putJson('/api/v1/online-payment-settings', [
            'sslcommerz_enabled' => '1', 'sslcommerz_sandbox' => '1', 'bkash_enabled' => '0', 'bkash_sandbox' => '1', 'test_enabled' => '0', 'min_amount' => 10,
        ])->assertUnprocessable();
        $this->putJson('/api/v1/online-payment-settings', [
            'sslcommerz_enabled' => '1', 'sslcommerz_sandbox' => '1', 'sslcommerz_store_id' => 'store', 'sslcommerz_store_password' => 'topsecret',
            'bkash_enabled' => '0', 'bkash_sandbox' => '1', 'test_enabled' => '0', 'min_amount' => 10,
        ])->assertOk()->assertJsonPath('data.sslcommerz_store_password', '')->assertJsonPath('data.sslcommerz_store_password_saved', true);
        $this->assertStringNotContainsString('topsecret', \App\Models\Setting::where('key', 'sslcommerz_store_password')->value('value'));
        $this->actingAs($this->userWithRole(Role::Receptionist))->getJson('/api/v1/online-payment-settings')->assertForbidden();
    }
}
