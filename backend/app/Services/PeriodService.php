<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Month locking (Accounts §১১). A closed month takes no new entries; later corrections from billing post
 * in the current month (LedgerService), and manual vouchers must use an open month.
 */
class PeriodService
{
    public function __construct(private LedgerService $ledger) {}

    /** The fiscal year containing $date with all twelve months (created on demand). */
    public function year(Carbon $date): Collection
    {
        $startYear = $date->month >= 7 ? $date->year : $date->year - 1;
        $start = Carbon::create($startYear, 7, 1);
        for ($m = 0; $m < 12; $m++) {
            $this->ledger->openPeriodFor($start->copy()->addMonths($m));
        }
        $fy = FiscalYear::where('name', sprintf('%d-%02d', $startYear, ($startYear + 1) % 100))->firstOrFail();

        return $fy->periods()->orderBy('start_date')->get();
    }

    public function close(AccountingPeriod $period, User $user): AccountingPeriod
    {
        if (! $period->isOpen()) {
            throw ValidationException::withMessages(['period' => 'This month is already closed.']);
        }
        if ($period->end_date->gte(today())) {
            throw ValidationException::withMessages(['period' => 'A month can be closed only after it ends.']);
        }
        $earlierOpen = AccountingPeriod::where('end_date', '<', $period->start_date)->where('status', 'open')
            ->whereHas('fiscalYear', fn ($q) => $q->whereKey($period->fiscal_year_id))->exists();
        if ($earlierOpen) {
            throw ValidationException::withMessages(['period' => 'Close the earlier months first.']);
        }
        $waiting = Voucher::whereBetween('date', [$period->start_date, $period->end_date])->whereIn('status', ['draft', 'submitted'])->count();
        if ($waiting) {
            throw ValidationException::withMessages(['period' => "{$waiting} voucher(s) in this month are still draft or waiting for approval."]);
        }

        $period->update(['status' => 'closed', 'closed_by' => $user->id, 'closed_at' => now()]);
        AuditLogger::log('period.closed', $period);

        return $period;
    }

    public function reopen(AccountingPeriod $period, User $user, string $reason): AccountingPeriod
    {
        if ($period->isOpen()) {
            throw ValidationException::withMessages(['period' => 'This month is already open.']);
        }
        $laterClosed = AccountingPeriod::where('start_date', '>', $period->end_date)->where('status', 'closed')->exists();
        if ($laterClosed) {
            throw ValidationException::withMessages(['period' => 'Reopen the later months first.']);
        }

        $period->update(['status' => 'open', 'closed_by' => null, 'closed_at' => null]);
        AuditLogger::log('period.reopened', $period, new: ['reason' => $reason, 'by' => $user->id]);

        return $period;
    }
}
