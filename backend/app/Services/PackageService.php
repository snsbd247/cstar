<?php

namespace App\Services;

use App\Enums\Permission;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\PackageUsage;
use App\Models\Patient;
use App\Models\PatientPackage;
use App\Models\TherapySession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Therapy packages (decision A2: income only when a session happens).
 *   Sold:      invoice line → Cr Unearned Package Revenue
 *   Consumed:  Dr Unearned / Cr Therapy Income (price ÷ sessions; last session takes the rounding remainder)
 *   Expired:   remaining unearned value → Therapy Income
 */
class PackageService
{
    public function __construct(
        private InvoiceService $invoices,
        private LedgerService $ledger,
        private AccountMap $accounts,
        private TimelineService $timeline,
    ) {}

    public function sell(Patient $patient, Package $package, array $data, User $user): Invoice
    {
        if ($package->is_active === false) {
            throw ValidationException::withMessages(['package_id' => 'This package is no longer offered.']);
        }

        return $this->invoices->createAndIssue($patient, (int) $data['branch_id'], [[
            'item_type' => 'package',
            'package_id' => $package->id,
            'service_id' => $package->service_id,
            'enrollment_id' => $data['enrollment_id'] ?? null,
            'description' => "{$package->name} ({$package->sessions_count} sessions, {$package->validity_days} days)",
            'quantity' => 1,
            'unit_price' => (float) $package->price,
            'discount' => (float) ($data['discount'] ?? 0),
        ]], $user, ['discount_reason' => $data['discount_reason'] ?? null]);
    }

    /** Uses one session of the child's package for this therapy (reason: session | no_show | late_cancel). */
    public function consume(Appointment $appointment, string $reason, ?TherapySession $session, ?User $user): ?PackageUsage
    {
        return DB::transaction(function () use ($appointment, $reason, $session, $user) {
            $package = PatientPackage::where('patient_id', $appointment->patient_id)
                ->where('service_id', $appointment->service_id)
                ->where('status', 'active')
                ->whereDate('expiry_date', '>=', $appointment->date)
                ->whereColumn('used_sessions', '<', 'total_sessions')
                ->orderBy('expiry_date')->orderBy('id')
                ->lockForUpdate()->first();
            if (! $package) {
                return null;
            }
            if (PackageUsage::where('appointment_id', $appointment->id)->where('quantity', '>', 0)->exists()) {
                return null; // already counted
            }

            $value = $package->valueOfNextSession();
            $usage = $package->usages()->create([
                'appointment_id' => $appointment->id,
                'therapy_session_id' => $session?->id,
                'reason' => $reason,
                'quantity' => 1,
                'value' => $value,
                'recorded_by' => $user?->id,
            ]);
            $package->increment('used_sessions');
            if ($package->remaining() === 0) {
                $package->update(['status' => 'exhausted']);
            }
            // Plan §১৮: renewal alert when two sessions are left, and when the package is used up.
            if (in_array($package->remaining(), [2, 0], true)) {
                $package->loadMissing(['package', 'patient']);
                $left = $package->remaining();
                $notify = app(NotificationService::class);
                $notify->parentsTemplate($package->patient, $left ? 'package.low' : 'package.finished',
                    ['package' => $package->package->name_bn ?: $package->package->name, 'left' => NotificationService::bnNumber($left)], '/portal/billing', 'package.low');
                $notify->staffTemplate(Permission::INVOICES_MANAGE, $package->branch_id, 'package.renewal',
                    ['child' => $package->patient->name, 'package' => $package->package->name, 'left' => $left], "/app/patients/{$package->patient_id}?tab=billing", kind: 'package.low');
            }

            $appointment->loadMissing(['service', 'patient']);
            $this->ledger->post('package.consumed', $appointment->date, $appointment->branch_id,
                "Package session ({$reason}) — {$appointment->patient->name}, {$appointment->service->name} ".$appointment->date->format('d M'), [
                    ['account' => $this->accounts->system('unearned_package'), 'debit' => $value, 'service_id' => $appointment->service_id, 'party' => $appointment->patient],
                    ['account' => $this->accounts->therapyIncome($appointment->service), 'credit' => $value, 'service_id' => $appointment->service_id],
                ], $usage);

            return $usage;
        });
    }

    /** Daily: packages past their expiry recognise the remaining value and close (A2, Accounts §৩). */
    public function expireDue(): int
    {
        $count = 0;
        PatientPackage::with(['patient', 'service'])->where('status', 'active')->whereDate('expiry_date', '<', today())->get()
            ->each(function (PatientPackage $package) use (&$count) {
                DB::transaction(function () use ($package) {
                    $value = $package->unearnedValue();
                    $package->update(['status' => 'expired']);
                    $this->ledger->post('package.expired', today(), $package->branch_id,
                        "Package expired with {$package->remaining()} unused sessions — {$package->patient->name}", [
                            ['account' => $this->accounts->system('unearned_package'), 'debit' => $value, 'service_id' => $package->service_id, 'party' => $package->patient],
                            ['account' => $this->accounts->therapyIncome($package->service), 'credit' => $value, 'service_id' => $package->service_id, 'memo' => 'expired package'],
                        ], $package);
                    $this->timeline->record($package->patient, 'package.expired', "Package expired: {$package->service->name} ({$package->remaining()} sessions unused)", $package,
                        branchId: $package->branch_id, visibility: 'parent');
                });
                $count++;
            });

        return $count;
    }
}
