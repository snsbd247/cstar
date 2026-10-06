<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Patient;
use App\Models\Service;
use App\Models\TherapySession;
use App\Models\User;
use App\Services\BillingSettings;
use App\Services\ChargeService;
use App\Services\InvoiceService;
use App\Services\PackageService;
use App\Services\PaymentService;
use Illuminate\Database\Seeder;

/**
 * Local demo for Sprint 10 — ALL PRICES ARE SAMPLES until C-STAR gives the real price list:
 *  - service prices, four therapy packages, admission ৳2,000, training fee ৳3,500/month
 *  - Ayan: Speech package (sessions already used from it), OT per session, training fee — all paid
 *  - Sara: admission + per-session speech charges, partly paid → shows a due
 *  - Rafi: this month's training fee unpaid → shows on the due list
 */
class DemoBillingSeeder extends Seeder
{
    private const PRICES = [
        'speech-language-therapy' => 1000, 'occupational-therapy' => 1000, 'aba-therapy' => 1200,
        'oral-placement-therapy' => 1000, 'special-education' => 900, 'parent-guidance' => 800, 'assessment-evaluation' => 2000,
    ];

    public function run(BillingSettings $settings, InvoiceService $invoices, PackageService $packages, PaymentService $payments, ChargeService $charges): void
    {
        if (Package::exists()) {
            return;
        }
        $reception = User::where('email', 'reception@cstar.test')->firstOrFail();
        $branch = Branch::where('code', 'HQ')->first() ?? Branch::firstOrFail();
        auth()->setUser($reception);

        foreach (self::PRICES as $slug => $price) {
            Service::where('slug', $slug)->update(['default_price' => $price]);
        }
        $settings->update(['admission_fee' => '2000', 'training_monthly_fee' => '3500']);

        $speech = Service::where('slug', 'speech-language-therapy')->firstOrFail();
        $ot = Service::where('slug', 'occupational-therapy')->firstOrFail();
        $aba = Service::where('slug', 'aba-therapy')->firstOrFail();
        $speech8 = Package::create(['name' => 'Speech Therapy — 8 sessions', 'name_bn' => 'স্পিচ থেরাপি — ৮ সেশন', 'service_id' => $speech->id, 'sessions_count' => 8, 'validity_days' => 45, 'price' => 7200, 'description' => 'Save ৳800 compared with paying per session.']);
        Package::create(['name' => 'Speech Therapy — 12 sessions', 'name_bn' => 'স্পিচ থেরাপি — ১২ সেশন', 'service_id' => $speech->id, 'sessions_count' => 12, 'validity_days' => 60, 'price' => 10200]);
        Package::create(['name' => 'Occupational Therapy — 8 sessions', 'name_bn' => 'অকুপেশনাল থেরাপি — ৮ সেশন', 'service_id' => $ot->id, 'sessions_count' => 8, 'validity_days' => 45, 'price' => 7200]);
        Package::create(['name' => 'ABA Therapy — 12 sessions', 'name_bn' => 'এবিএ থেরাপি — ১২ সেশন', 'service_id' => $aba->id, 'sessions_count' => 12, 'validity_days' => 60, 'price' => 13000]);

        $ayan = Patient::where('name', 'Ayan Rahman')->first();
        $sara = Patient::where('name', 'Sara Islam')->first();
        $rafi = Patient::where('name', 'Rafi Ahmed')->first();
        if (! $ayan || ! $sara || ! $rafi) {
            return;
        }

        // Admission fees (Sara's is paid only in part below).
        foreach ([$ayan, $sara, $rafi] as $child) {
            $invoices->createAndIssue($child, $branch->id, [InvoiceService::line('admission', 'Registration & admission fee', 2000)], $reception);
        }

        // Ayan's speech therapy runs on a package.
        $ayanSpeech = Enrollment::where('patient_id', $ayan->id)->whereHas('therapyEnrollment', fn ($q) => $q->where('service_id', $speech->id))->with('therapyEnrollment')->first();
        $ayanSpeech?->therapyEnrollment->update(['billing_mode' => 'package']);
        $packages->sell($ayan, $speech8, ['branch_id' => $branch->id, 'enrollment_id' => $ayanSpeech?->id], $reception);

        // Sessions already written before billing existed: use the package or charge per session.
        TherapySession::with('appointment')->where('status', 'final')->orderBy('date')->orderBy('id')->get()
            ->each(fn (TherapySession $s) => $charges->sessionCompleted($s->appointment, $s, $reception));

        // This month's Regular Training fees (Ayan and Rafi).
        $charges->generateTrainingFees(today()->startOfMonth(), $reception, $branch->id);

        // Payments: Ayan clears everything (cash + bKash), Sara pays part, Rafi pays only admission.
        $dueOf = fn (Patient $p) => (float) Invoice::where('patient_id', $p->id)->open()->sum('due_total');
        $payments->receive($ayan, ['amount' => 7200, 'method' => 'bkash', 'transaction_ref' => 'DEMO8F3K2L', 'branch_id' => $branch->id, 'payer_name' => 'Nusrat Jahan (mother)'], $reception);
        $payments->receive($ayan, ['amount' => $dueOf($ayan), 'method' => 'cash', 'branch_id' => $branch->id, 'payer_name' => 'Nusrat Jahan (mother)'], $reception);
        $payments->receive($sara, ['amount' => 3000, 'method' => 'cash', 'branch_id' => $branch->id], $reception);
        $payments->receive($rafi, ['amount' => 2000, 'method' => 'nagad', 'transaction_ref' => 'DEMO7Q1W9E', 'branch_id' => $branch->id], $reception);
    }
}
