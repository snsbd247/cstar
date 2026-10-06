<?php

namespace Tests\Feature\Billing;

use App\Enums\Role;
use App\Models\Account;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\JournalLine;
use App\Models\Package;
use App\Models\Patient;
use App\Models\PatientPackage;
use App\Models\Service;
use App\Models\Therapist;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesClinicData;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use CreatesClinicData, RefreshDatabase;

    private Branch $branch;

    private Service $speech;

    private Patient $sara;

    private User $reception;

    private User $accountant;

    private User $imranUser;

    private Therapist $imran;

    private Enrollment $therapy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->branch = Branch::factory()->create();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->speech = Service::factory()->create(['name' => 'Speech Therapy', 'slug' => 'speech', 'default_price' => 1000]);
        $this->sara = Patient::factory()->create(['home_branch_id' => $this->branch->id]);
        $this->reception = $this->userWithRole(Role::Receptionist, $this->branch);
        $this->accountant = $this->userWithRole(Role::Accountant, $this->branch);
        $this->imranUser = $this->userWithRole(Role::Therapist, $this->branch);
        $this->imran = $this->therapistFor($this->speech, $this->imranUser, $this->branch);
        $this->therapy = $this->enrollTherapy($this->sara, $this->speech, $this->imran, $this->branch);
    }

    private function invoice(array $items, array $extra = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->reception)->postJson('/api/v1/invoices', [
            'patient_id' => $this->sara->id, 'branch_id' => $this->branch->id, 'items' => $items, 'issue' => true, ...$extra,
        ]);
    }

    private function pay(float $amount, array $extra = [])
    {
        return $this->actingAs($this->reception)->postJson("/api/v1/patients/{$this->sara->id}/payments", [
            'amount' => $amount, 'method' => 'cash', 'branch_id' => $this->branch->id, ...$extra,
        ]);
    }

    private function balance(string $key): float
    {
        $account = Account::where('system_key', $key)->firstOrFail();
        $lines = JournalLine::where('account_id', $account->id);
        $net = (float) $lines->sum('debit') - (float) (clone $lines)->sum('credit');

        return round($account->normal_balance === 'debit' ? $net : -$net, 2);
    }

    private function assertBooksBalance(): void
    {
        $this->assertEqualsWithDelta((float) JournalLine::sum('debit'), (float) JournalLine::sum('credit'), 0.001, 'Trial balance must be zero.');
    }

    private function appointment(string $status = 'checked_in'): Appointment
    {
        static $hour = 8;
        $hour = $hour >= 17 ? 8 : $hour + 1;
        $start = sprintf('%02d:00:00', $hour);

        return Appointment::create([
            'appointment_code' => 'APT-T-'.uniqid(), 'patient_id' => $this->sara->id, 'enrollment_id' => $this->therapy->id,
            'service_id' => $this->speech->id, 'therapist_id' => $this->imran->id, 'branch_id' => $this->branch->id,
            'date' => today()->toDateString(), 'start_time' => $start, 'end_time' => sprintf('%02d:45:00', $hour), 'type' => 'therapy', 'status' => $status,
        ]);
    }

    private function finalizeSession(Appointment $a): void
    {
        $this->actingAs($this->imranUser)->postJson("/api/v1/appointments/{$a->id}/session", [
            'observation' => 'ok', 'parent_summary' => 'Good session', 'finalize' => true,
        ])->assertSuccessful();
    }

    public function test_issuing_an_invoice_posts_a_balanced_entry_with_discount(): void
    {
        $res = $this->invoice([
            ['item_type' => 'admission', 'description' => 'Admission fee', 'unit_price' => 2000],
            ['item_type' => 'assessment', 'unit_price' => 1500, 'discount' => 150],
        ], ['discount_reason' => 'Sibling concession'])->assertCreated();

        $res->assertJsonPath('data.status', 'issued')->assertJsonPath('data.total', 3350)->assertJsonPath('data.due_total', 3350);
        $this->assertMatchesRegularExpression('/^INV-\d{4}-\d{6}$/', $res->json('data.invoice_no'));
        $this->assertSame(3350.0, $this->balance('receivable'));
        $this->assertSame(2000.0, $this->balance('income_admission'));
        $this->assertSame(1500.0, $this->balance('income_assessment'));
        $this->assertSame(150.0, $this->balance('discount_allowed'));
        $this->assertBooksBalance();
    }

    public function test_discounts_need_a_reason_and_receptionists_are_limited(): void
    {
        $this->invoice([['item_type' => 'other', 'unit_price' => 1000, 'discount' => 50]])->assertJsonValidationErrors('discount_reason');
        $this->invoice([['item_type' => 'other', 'unit_price' => 1000, 'discount' => 300]], ['discount_reason' => 'Poor family'])
            ->assertJsonValidationErrors('discount');
        $this->invoice([['item_type' => 'other', 'unit_price' => 1000, 'discount' => 300]], ['discount_reason' => 'Poor family'], $this->accountant)
            ->assertCreated();
    }

    public function test_payment_pays_oldest_invoice_first_and_keeps_the_rest_as_advance(): void
    {
        $first = $this->invoice([['item_type' => 'other', 'unit_price' => 1000]])->json('data.id');
        $second = $this->invoice([['item_type' => 'other', 'unit_price' => 500]])->json('data.id');

        $this->pay(1800)->assertCreated()->assertJsonPath('data.unallocated', 300);
        $this->assertSame('paid', Invoice::find($first)->status);
        $this->assertSame('paid', Invoice::find($second)->status);
        $cashBox = Account::where('subtype', 'cash')->where('branch_id', $this->branch->id)->firstOrFail();
        $this->assertSame(1800.0, (float) $cashBox->lines()->sum('debit'));
        $this->assertSame(300.0, $this->balance('patient_advances'));

        // The advance pays the next invoice automatically.
        $third = $this->invoice([['item_type' => 'other', 'unit_price' => 400]])->json('data');
        $this->assertSame('partially_paid', $third['status']);
        $this->assertSame(100.0, (float) $third['due_total']);
        $this->assertSame(0.0, $this->balance('patient_advances'));
        $this->assertSame(100.0, $this->balance('receivable'));
        $this->assertBooksBalance();
    }

    public function test_non_cash_payments_need_a_transaction_id(): void
    {
        $this->invoice([['item_type' => 'other', 'unit_price' => 500]]);
        $this->pay(500, ['method' => 'bkash'])->assertJsonValidationErrors('transaction_ref');
        $this->pay(500, ['method' => 'bkash', 'transaction_ref' => 'TRX123'])->assertCreated();
        $this->assertSame(500.0, $this->balance('mfs_bkash'));
    }

    public function test_voiding_an_invoice_reverses_it_and_returns_the_payment_as_advance(): void
    {
        $id = $this->invoice([['item_type' => 'other', 'unit_price' => 1000]])->json('data.id');
        $this->pay(600);

        $this->actingAs($this->reception)->postJson("/api/v1/invoices/{$id}/void", ['reason' => 'Wrong child'])->assertForbidden();
        $this->actingAs($this->accountant)->postJson("/api/v1/invoices/{$id}/void", ['reason' => 'Wrong child'])
            ->assertOk()->assertJsonPath('data.status', 'void');

        $this->assertSame(0.0, $this->balance('receivable'));
        $this->assertSame(0.0, $this->balance('income_other'));
        $this->assertSame(600.0, $this->balance('patient_advances'));
        $this->assertDatabaseHas('invoices', ['id' => $id, 'invoice_no' => Invoice::find($id)->invoice_no]); // never deleted
        $this->assertBooksBalance();

        // The advance can be refunded.
        $this->actingAs($this->accountant)->postJson("/api/v1/patients/{$this->sara->id}/refunds", [
            'amount' => 700, 'method' => 'cash', 'branch_id' => $this->branch->id, 'reason' => 'Family moved away',
        ])->assertJsonValidationErrors('amount');
        $this->actingAs($this->accountant)->postJson("/api/v1/patients/{$this->sara->id}/refunds", [
            'amount' => 600, 'method' => 'cash', 'branch_id' => $this->branch->id, 'reason' => 'Family moved away',
        ])->assertCreated();
        $this->assertSame(0.0, $this->balance('patient_advances'));
        $this->assertBooksBalance();
    }

    public function test_package_income_is_recognised_session_by_session(): void
    {
        $this->therapy->therapyEnrollment->update(['billing_mode' => 'package']);
        $package = Package::create(['name' => 'Speech 3', 'service_id' => $this->speech->id, 'sessions_count' => 3, 'validity_days' => 30, 'price' => 1000]);

        $this->actingAs($this->reception)->postJson("/api/v1/patients/{$this->sara->id}/packages", [
            'package_id' => $package->id, 'branch_id' => $this->branch->id,
        ])->assertCreated()->assertJsonPath('data.items.0.item_type', 'package');
        $this->assertSame(1000.0, $this->balance('unearned_package'));
        $this->assertSame(0.0, $this->balance('income_therapy_other'));

        $this->finalizeSession($this->appointment());
        $pp = PatientPackage::firstOrFail();
        $this->assertSame(1, $pp->used_sessions);
        $this->assertSame(333.33, $this->balance('income_therapy_other'));
        $this->assertSame(1, Invoice::count(), 'A package session is not charged again.');

        // No-show uses a session (D3); the last session takes the rounding remainder.
        $missed = $this->appointment('confirmed');
        $this->actingAs($this->reception)->postJson("/api/v1/appointments/{$missed->id}/no-show")->assertOk();
        $this->finalizeSession($this->appointment());
        $pp->refresh();
        $this->assertSame('exhausted', $pp->status);
        $this->assertSame(1000.0, $this->balance('income_therapy_other'));
        $this->assertSame(0.0, $this->balance('unearned_package'));

        // Package used up → the next session falls back to a per-session charge.
        $this->finalizeSession($this->appointment());
        $this->assertSame(2, Invoice::count());
        $this->assertBooksBalance();
    }

    public function test_expired_package_recognises_the_unused_value(): void
    {
        $package = Package::create(['name' => 'Speech 4', 'service_id' => $this->speech->id, 'sessions_count' => 4, 'validity_days' => 30, 'price' => 2000]);
        $this->actingAs($this->reception)->postJson("/api/v1/patients/{$this->sara->id}/packages", ['package_id' => $package->id, 'branch_id' => $this->branch->id]);
        PatientPackage::firstOrFail()->update(['expiry_date' => today()->subDay()]);

        $this->artisan('cstar:expire-packages')->assertSuccessful();
        $this->assertSame('expired', PatientPackage::firstOrFail()->status);
        $this->assertSame(0.0, $this->balance('unearned_package'));
        $this->assertSame(2000.0, $this->balance('income_therapy_other'));
    }

    public function test_per_session_billing_charges_each_completed_session_once(): void
    {
        $a = $this->appointment();
        $this->finalizeSession($a);
        $invoice = Invoice::with('items')->firstOrFail();
        $this->assertSame('therapy_session', $invoice->items[0]->item_type);
        $this->assertSame(1000.0, (float) $invoice->total);
        $this->assertSame(1, Invoice::count());
    }

    public function test_monthly_training_fees_are_created_once_per_month(): void
    {
        $class = $this->classFor($this->branch);
        $rafi = Patient::factory()->create(['home_branch_id' => $this->branch->id]);
        $this->enrollTraining($rafi, $class, ['monthly_fee' => 3000]);

        $month = today()->format('Y-m');
        $this->actingAs($this->reception)->postJson('/api/v1/billing/training-fees', ['month' => $month])->assertOk()->assertJsonPath('created', 1);
        $this->actingAs($this->reception)->postJson('/api/v1/billing/training-fees', ['month' => $month])->assertOk()->assertJsonPath('created', 0);
        $this->assertSame(3000.0, $this->balance('income_training'));
    }

    public function test_voiding_a_payment_reopens_the_invoice(): void
    {
        $id = $this->invoice([['item_type' => 'other', 'unit_price' => 1000]])->json('data.id');
        $paymentId = $this->pay(1000)->json('data.id');

        $this->actingAs($this->accountant)->postJson("/api/v1/payments/{$paymentId}/void", ['reason' => 'Counterfeit note'])->assertOk();
        $this->assertSame('issued', Invoice::find($id)->status);
        $this->assertSame(1000.0, $this->balance('receivable'));
        $this->assertBooksBalance();
    }

    public function test_staff_of_another_branch_cannot_see_or_bill(): void
    {
        $id = $this->invoice([['item_type' => 'other', 'unit_price' => 1000]])->json('data.id');
        $other = $this->userWithRole(Role::Receptionist, Branch::factory()->create());

        $this->actingAs($other)->getJson("/api/v1/invoices/{$id}")->assertForbidden();
        $this->actingAs($other)->getJson('/api/v1/invoices')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($this->imranUser)->getJson('/api/v1/invoices')->assertForbidden();
    }

    public function test_dashboard_shows_money_only_to_billing_staff(): void
    {
        $this->invoice([['item_type' => 'other', 'unit_price' => 1000]]);
        $this->pay(400);

        $this->actingAs($this->reception)->getJson('/api/v1/dashboard')
            ->assertOk()->assertJsonPath('data.collection_today', 400)->assertJsonPath('data.total_due', 600)->assertJsonPath('data.therapy_only', 1);
        $this->actingAs($this->imranUser)->getJson('/api/v1/dashboard')
            ->assertOk()->assertJsonMissingPath('data.collection_today')->assertJsonMissingPath('data.total_due');
    }

    public function test_account_summary_collection_and_pdfs(): void
    {
        $id = $this->invoice([['item_type' => 'other', 'unit_price' => 1000]])->json('data.id');
        $paymentId = $this->pay(1200)->json('data.id');

        $this->actingAs($this->reception)->getJson("/api/v1/patients/{$this->sara->id}/billing")
            ->assertOk()->assertJsonPath('data.due', 0)->assertJsonPath('data.advance', 200);
        $this->actingAs($this->reception)->getJson('/api/v1/billing/collection')
            ->assertOk()->assertJsonPath('data.total', 1200)->assertJsonPath('data.by_method.cash', 1200);
        $this->actingAs($this->accountant)->getJson('/api/v1/accounts/chart')->assertOk()->assertJsonPath('totals.debit', 2200);

        foreach (["/api/v1/invoices/{$id}/pdf", "/api/v1/payments/{$paymentId}/receipt"] as $url) {
            $pdf = $this->actingAs($this->reception)->get($url);
            $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
            $this->assertStringStartsWith('%PDF', $pdf->getContent());
        }
    }
}
