<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Patient;
use App\Models\User;
use App\Services\GoLiveChecks;
use App\Services\GoLiveService;
use App\Services\OnlinePayment\OnlinePaymentSettings;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** Sprint 17 go-live tools: opening balances and importing the children already at the center. */
class GoLiveTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->branch = Branch::factory()->create(['code' => 'HQ']);
        $this->seed(ChartOfAccountsSeeder::class);
        $this->admin = $this->userWithRole(Role::SuperAdmin, $this->branch);
    }

    private function balance(string $code): float
    {
        $account = Account::where('code', $code)->firstOrFail();
        $net = (float) JournalLine::where('account_id', $account->id)->sum('debit') - (float) JournalLine::where('account_id', $account->id)->sum('credit');

        return round($account->normal_balance === 'debit' ? $net : -$net, 2);
    }

    public function test_opening_balances_post_once_and_can_be_replaced(): void
    {
        $this->actingAs($this->admin);
        $accounts = collect($this->getJson('/api/v1/go-live/opening-balances')->assertOk()->json('data.accounts'));
        $bank = $accounts->firstWhere('code', '1131')['id'];
        $loan = $accounts->firstWhere('code', '2600')['id'];
        $this->assertNull($accounts->firstWhere('code', '1200'), 'Patient receivable comes from the import, not here');

        $this->postJson('/api/v1/go-live/opening-balances', ['date' => today()->toDateString(), 'amounts' => [$bank => 500000, $loan => 100000]])->assertOk()
            ->assertJsonPath('data.accounts.'.$accounts->search(fn ($a) => $a['id'] === $bank).'.amount', 500000);
        $this->assertSame(500000.0, $this->balance('1131'));
        $this->assertSame(100000.0, $this->balance('2600'));
        $this->assertSame(400000.0, $this->balance('3300')); // equity at go-live

        // Entering again replaces the first entry instead of adding to it.
        $this->postJson('/api/v1/go-live/opening-balances', ['date' => today()->toDateString(), 'amounts' => [$bank => 450000]])->assertOk();
        $this->assertSame(450000.0, $this->balance('1131'));
        $this->assertSame(0.0, $this->balance('2600'));
        $this->assertSame(1, JournalEntry::where('event', GoLiveService::EVENT)->where('status', 'posted')->count());
        $this->assertEqualsWithDelta((float) JournalLine::sum('debit'), (float) JournalLine::sum('credit'), 0.001);

        $this->postJson('/api/v1/go-live/opening-balances', ['date' => today()->toDateString(), 'amounts' => [Account::where('code', '1200')->value('id') => 5]])
            ->assertUnprocessable()->assertJsonValidationErrors('amounts');
        $this->actingAs($this->userWithRole(Role::Receptionist, $this->branch))->getJson('/api/v1/go-live/opening-balances')->assertForbidden();
    }

    public function test_children_import_checks_first_then_registers_with_previous_dues(): void
    {
        $sibling = Patient::factory()->create(['home_branch_id' => $this->branch->id]);
        $mother = Guardian::factory()->create(['phone' => '01811111111']);
        $sibling->guardians()->attach($mother->id, ['relationship' => 'mother', 'is_primary' => true]);
        Patient::factory()->create(['name' => 'Already Here', 'date_of_birth' => '2018-01-01', 'phone' => '01722222222', 'home_branch_id' => $this->branch->id]);

        $csv = "\xEF\xBB\xBF".implode(',', GoLiveService::COLUMNS)."\n"
            ."Rahim Uddin,রহিম,21/05/2019,ছেলে,1712345678,Karima Begum,মা,,,,Mirpur,HQ,2025-03-01,F-12,,\"1,500\"\n"
            ."Second Child,,2020-02-02,female,01811111111,,mother,,,,,,,,,\n"
            ."Already Here,,2018-01-01,male,01722222222,Someone,father,,,,,,,,,\n"
            ."Bad Row,,someday,unknown,123,,,,,,,ZZ,,,,\n";
        $file = fn () => UploadedFile::fake()->createWithContent('children.csv', $csv);

        $this->actingAs($this->admin);
        $check = $this->post('/api/v1/go-live/import-children', ['file' => $file(), 'dry_run' => 1], ['Accept' => 'application/json'])->assertOk();
        $this->assertSame(['ok', 'ok', 'duplicate', 'error'], array_column($check->json('data'), 'status'));
        $this->assertCount(6, $check->json('data.3.messages')); // date, gender, phone, guardian, relationship, branch
        $this->assertSame(1, Patient::where('name', 'Already Here')->count());
        $this->assertSame(0, Patient::where('name', 'Rahim Uddin')->count(), 'A check never saves anything');

        $this->post('/api/v1/go-live/import-children', ['file' => $file(), 'dry_run' => 0], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('summary.imported', 2);
        $rahim = Patient::where('name', 'Rahim Uddin')->firstOrFail();
        $this->assertSame('01712345678', $rahim->phone); // Excel's dropped leading zero restored
        $this->assertSame('2019-05-21', $rahim->date_of_birth->toDateString());
        $this->assertStringContainsString('Old file no: F-12', $rahim->notes);
        $this->assertTrue($rahim->consents()->where('type', 'treatment')->where('granted', true)->exists());

        // Previous dues: an invoice the parent can pay, but not income of the new books.
        $invoice = Invoice::where('patient_id', $rahim->id)->firstOrFail();
        $this->assertSame(1500.0, (float) $invoice->due_total);
        $this->assertSame(1500.0, $this->balance('1200'));
        $this->assertSame(1500.0, $this->balance('3300'));

        // The second child's mother already had a sibling registered: linked, not duplicated.
        $second = Patient::where('name', 'Second Child')->firstOrFail();
        $this->assertSame($mother->id, $second->guardians()->first()->id);
        $this->assertSame(1, Guardian::where('phone', '01811111111')->count());
    }

    public function test_go_live_check_command_lists_open_items(): void
    {
        $this->artisan('cstar:go-live-check')->expectsOutputToContain('Opening balances entered')->assertExitCode(1);
    }

    public function test_a_live_gateway_left_in_sandbox_is_flagged(): void
    {
        $check = fn () => collect(app(GoLiveChecks::class)->all())->firstWhere('label', 'Online payment: no gateway left in sandbox (test) mode')['ok'];
        $payments = app(OnlinePaymentSettings::class);

        $this->assertTrue($check());                                     // online payment not used at all
        $payments->update(['bkash_enabled' => '1', 'bkash_sandbox' => '1']);
        $this->assertFalse($check());
        $payments->update(['bkash_sandbox' => '0']);
        $this->assertTrue($check());
    }
}
