<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Models\Appointment;
use App\Models\Enrollment;
use App\Models\InvoiceItem;
use App\Models\Service;
use App\Models\TherapySession;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Automatic charges (Plan §১৯ "চার্জের উৎস"):
 *   therapy session completed → package session, or a per-session invoice
 *   no-show / late cancel     → package session when the setting says so (D3)
 *   assessment completed      → assessment fee invoice
 *   1st of the month          → Regular Training fee invoice (D4: fixed monthly fee; never twice for a month)
 */
class ChargeService
{
    public function __construct(
        private InvoiceService $invoices,
        private PackageService $packages,
        private BillingSettings $settings,
    ) {}

    public function sessionCompleted(Appointment $appointment, TherapySession $session, User $user): void
    {
        $appointment->loadMissing(['service', 'patient', 'enrollment.therapyEnrollment']);
        $mode = $appointment->enrollment?->therapyEnrollment?->billing_mode ?? 'per_session';

        if ($mode === 'monthly') {
            return;
        }
        if ($mode === 'package' && $this->packages->consume($appointment, 'session', $session, $user)) {
            return;
        }
        // Per session — or a package child whose package has run out.
        $this->chargeOnce($appointment, 'therapy_session', $appointment->service, $user, ['therapy_session_id' => $session->id]);
    }

    /** D3: a no-show, or a cancellation within 24 hours, uses a package session. */
    public function appointmentMissed(Appointment $appointment, User $user): void
    {
        $reason = $appointment->status->value === 'no_show' ? 'no_show' : 'late_cancel';
        $deducts = $reason === 'no_show'
            ? $this->settings->flag('no_show_deducts_package')
            : $appointment->is_late_cancellation && $this->settings->flag('late_cancel_deducts_package');

        if ($deducts && $appointment->type === 'therapy') {
            $this->packages->consume($appointment, $reason, null, $user);
        }
    }

    public function assessmentCompleted(Appointment $appointment, User $user): void
    {
        $this->chargeOnce($appointment, 'assessment', $appointment->service, $user);
    }

    /** Creates the month's training fee invoices. Returns how many were created. */
    public function generateTrainingFees(Carbon $month, User $user, ?int $branchId = null): int
    {
        $period = $month->format('Y-m');
        $fallback = (float) $this->settings->get('training_monthly_fee');
        $created = 0;

        Enrollment::with(['patient', 'trainingEnrollment.trainingGroup'])
            ->where('type', EnrollmentType::Training)
            ->where('status', EnrollmentStatus::Active)
            ->whereDate('start_date', '<=', $month->copy()->endOfMonth())
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->get()
            ->each(function (Enrollment $enrollment) use ($period, $month, $fallback, $user, &$created) {
                $fee = (float) ($enrollment->trainingEnrollment?->monthly_fee ?? 0) ?: $fallback;
                if ($fee <= 0 || InvoiceService::alreadyBilled($enrollment->id, $period)) {
                    return;
                }
                $class = $enrollment->trainingEnrollment?->trainingGroup?->name;
                $this->invoices->createAndIssue($enrollment->patient, $enrollment->branch_id, [
                    InvoiceService::line('training_fee', "Regular Training fee — {$month->format('F Y')}".($class ? " ({$class})" : ''), $fee, [
                        'enrollment_id' => $enrollment->id, 'billing_period' => $period,
                    ]),
                ], $user, ['due_date' => $month->copy()->startOfMonth()->addDays(9)]);
                $created++;
            });

        return $created;
    }

    private function chargeOnce(Appointment $appointment, string $type, ?Service $service, User $user, array $extra = []): void
    {
        $price = (float) ($service?->default_price ?? 0);
        $alreadyCharged = InvoiceItem::where('appointment_id', $appointment->id)
            ->whereHas('invoice', fn ($q) => $q->where('status', '!=', 'void'))->exists();
        if ($price <= 0 || $alreadyCharged) {
            return; // no price set yet, or charged before
        }

        $this->invoices->createAndIssue($appointment->patient, $appointment->branch_id, [
            InvoiceService::line($type, "{$service->name} — ".$appointment->date->format('d M Y'), $price, [
                'service_id' => $service->id, 'appointment_id' => $appointment->id, 'enrollment_id' => $appointment->enrollment_id, ...$extra,
            ]),
        ], $user);
    }
}
