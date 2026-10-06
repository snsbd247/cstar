<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The only way money enters the books (Accounts §৩, §১৩).
 * Every entry is balanced (Σ debit = Σ credit) inside one transaction; posted entries are never edited, only reversed.
 * A date in a closed period is posted in today's period instead.
 */
class LedgerService
{
    public function __construct(private IdGenerator $ids) {}

    /**
     * @param  list<array{account: Account, debit?: float|string, credit?: float|string, service_id?: ?int, party?: ?Model, memo?: ?string, branch_id?: ?int}>  $lines
     */
    public function post(string $event, Carbon|string $date, ?int $branchId, string $narration, array $lines, ?Model $source = null, string $voucherType = 'system', bool $keepDate = false): ?JournalEntry
    {
        $lines = array_values(array_filter($lines, fn ($l) => round((float) ($l['debit'] ?? 0), 2) > 0 || round((float) ($l['credit'] ?? 0), 2) > 0));
        if ($lines === []) {
            return null; // nothing to post (e.g. a fully discounted invoice)
        }

        $debit = round(array_sum(array_map(fn ($l) => (float) ($l['debit'] ?? 0), $lines)), 2);
        $credit = round(array_sum(array_map(fn ($l) => (float) ($l['credit'] ?? 0), $lines)), 2);
        if (abs($debit - $credit) > 0.001) {
            throw new RuntimeException("Unbalanced journal for {$event}: debit {$debit} ≠ credit {$credit}.");
        }

        return DB::transaction(function () use ($event, $date, $branchId, $narration, $lines, $source, $voucherType, $keepDate) {
            // $keepDate: only the year-end closing entry may land in a closed month.
            $period = $keepDate ? $this->periodFor(Carbon::parse($date)) : $this->openPeriodFor(Carbon::parse($date));
            $postDate = Carbon::parse($date)->between($period->start_date, $period->end_date) ? Carbon::parse($date) : today();

            $entry = JournalEntry::create([
                'voucher_no' => $this->ids->next('journal', 'JV', 6, $period->year),
                'voucher_type' => $voucherType,
                'event' => $event,
                'date' => $postDate,
                'branch_id' => $branchId,
                'fiscal_year_id' => $period->fiscal_year_id,
                'accounting_period_id' => $period->id,
                'narration' => mb_substr($narration, 0, 500),
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'status' => 'posted',
                'prepared_by' => Auth::id(),
                'posted_at' => now(),
            ]);

            foreach ($lines as $line) {
                if ($line['account']->is_group) {
                    throw new RuntimeException("Cannot post to group account {$line['account']->code}.");
                }
                $entry->lines()->create([
                    'account_id' => $line['account']->id,
                    'debit' => round((float) ($line['debit'] ?? 0), 2),
                    'credit' => round((float) ($line['credit'] ?? 0), 2),
                    'branch_id' => $line['branch_id'] ?? $branchId,
                    'service_id' => $line['service_id'] ?? null,
                    'party_type' => isset($line['party']) ? $line['party']->getMorphClass() : null,
                    'party_id' => isset($line['party']) ? $line['party']->getKey() : null,
                    'memo' => $line['memo'] ?? null,
                ]);
            }

            return $entry;
        });
    }

    /** Posts the mirror image of an entry and marks the original reversed. */
    public function reverse(JournalEntry $entry, string $reason, ?Carbon $date = null): JournalEntry
    {
        if ($entry->status === 'reversed') {
            throw new RuntimeException("Journal {$entry->voucher_no} is already reversed.");
        }

        return DB::transaction(function () use ($entry, $reason, $date) {
            $entry->loadMissing('lines.account', 'lines.party');
            $reversal = $this->post(
                "{$entry->event}.reversed", $date ?? today(), $entry->branch_id, "Reversal of {$entry->voucher_no}: {$reason}",
                $entry->lines->map(fn ($l) => [
                    'account' => $l->account, 'debit' => $l->credit, 'credit' => $l->debit, 'branch_id' => $l->branch_id,
                    'service_id' => $l->service_id, 'party' => $l->party, 'memo' => $l->memo,
                ])->all(),
                $entry->source_type ? $entry->source : null,
            );
            $reversal->update(['reversal_of_id' => $entry->id]);
            $entry->update(['status' => 'reversed']);

            return $reversal;
        });
    }

    /** Reverses every posted entry for a source record (e.g. all postings of a voided invoice). */
    public function reverseAllFor(Model $source, string $reason, ?string $eventPrefix = null): void
    {
        JournalEntry::where('source_type', $source->getMorphClass())->where('source_id', $source->getKey())
            ->where('status', 'posted')->whereNull('reversal_of_id')
            ->when($eventPrefix, fn ($q) => $q->where('event', 'like', "{$eventPrefix}%"))
            ->orderBy('id')->get()
            ->each(fn (JournalEntry $e) => $this->reverse($e, $reason));
    }

    /** Fiscal year July–June (A1); periods are created on demand. Closed period → today's period. */
    public function openPeriodFor(Carbon $date): AccountingPeriod
    {
        $period = $this->periodFor($date);

        return $period->isOpen() ? $period : $this->periodFor(today());
    }

    private function periodFor(Carbon $date): AccountingPeriod
    {
        $existing = AccountingPeriod::where('year', $date->year)->where('month', $date->month)->first();
        if ($existing) {
            return $existing;
        }

        $startYear = $date->month >= 7 ? $date->year : $date->year - 1;
        $fy = FiscalYear::firstOrCreate(['name' => sprintf('%d-%02d', $startYear, ($startYear + 1) % 100)], [
            'start_date' => Carbon::create($startYear, 7, 1), 'end_date' => Carbon::create($startYear + 1, 6, 30), 'status' => 'open',
        ]);

        return AccountingPeriod::firstOrCreate(['year' => $date->year, 'month' => $date->month], [
            'fiscal_year_id' => $fy->id,
            'start_date' => $date->copy()->startOfMonth(), 'end_date' => $date->copy()->endOfMonth(), 'status' => 'open',
        ]);
    }
}
