<?php

namespace App\Services;

use App\Models\Account;
use App\Models\BankReconciliation;
use App\Models\BankStatementLine;
use App\Models\JournalLine;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Bank / bKash / Nagad reconciliation (Accounts §৯):
 *   statement lines (imported CSV or typed) → auto-match to unreconciled book lines (same amount, ±5 days)
 *   → bank-only items (charges, interest) become an adjustment entry
 *   → complete when statement balance = book balance − items not yet on the statement.
 */
class ReconciliationService
{
    public function __construct(private LedgerService $ledger, private AccountMap $accounts) {}

    /** Parses "date,description,amount" or "date,description,debit,credit" CSV (header row optional). */
    public function import(BankReconciliation $rec, string $csv): int
    {
        $this->assertDraft($rec);
        $count = 0;
        foreach (preg_split('/\r\n|\r|\n/', trim(preg_replace('/^\xEF\xBB\xBF/', '', $csv))) as $i => $line) {
            $cols = str_getcsv($line);
            if (count($cols) < 3) {
                continue;
            }
            try {
                $date = Carbon::parse(trim($cols[0]));
            } catch (\Throwable) {
                if ($i === 0) {
                    continue; // header
                }
                throw ValidationException::withMessages(['file' => 'Line '.($i + 1).': the date "'.$cols[0].'" is not valid.']);
            }
            $num = fn ($v) => (float) str_replace([',', '৳', ' '], '', (string) $v);
            $amount = count($cols) >= 4 ? $num($cols[3] ?? 0) - $num($cols[2]) : $num($cols[2]); // debit (out), credit (in)
            if (abs($amount) < 0.005) {
                continue;
            }
            $rec->lines()->create(['date' => $date, 'description' => mb_substr(trim($cols[1]), 0, 255), 'amount' => round($amount, 2), 'status' => 'unmatched']);
            $count++;
        }

        return $count;
    }

    /** Pairs each unmatched statement line with one unreconciled book line of the same amount within 5 days. */
    public function autoMatch(BankReconciliation $rec): int
    {
        $this->assertDraft($rec);
        $taken = BankStatementLine::whereNotNull('journal_line_id')->pluck('journal_line_id')->all();
        $book = $this->openBookLines($rec)->reject(fn ($l) => in_array($l->id, $taken, true))->values();
        $matched = 0;

        foreach ($rec->lines()->where('status', 'unmatched')->get() as $line) {
            $hit = $book->search(fn ($l) => abs($this->signed($l) - (float) $line->amount) < 0.005
                && abs($l->entry->date->diffInDays($line->date, false)) <= 5);
            if ($hit !== false) {
                $line->update(['journal_line_id' => $book[$hit]->id, 'status' => 'matched']);
                $book->forget($hit);
                $matched++;
            }
        }

        return $matched;
    }

    public function match(BankStatementLine $line, ?int $journalLineId): BankStatementLine
    {
        $rec = BankReconciliation::findOrFail($line->bank_reconciliation_id);
        $this->assertDraft($rec);
        if ($journalLineId) {
            $book = $this->openBookLines($rec)->firstWhere('id', $journalLineId)
                ?? throw ValidationException::withMessages(['journal_line_id' => 'That book entry is not open for this account.']);
            if (abs($this->signed($book) - (float) $line->amount) > 0.005) {
                throw ValidationException::withMessages(['journal_line_id' => 'The amounts are different.']);
            }
        }
        $line->update(['journal_line_id' => $journalLineId, 'status' => $journalLineId ? 'matched' : 'unmatched']);

        return $line;
    }

    /** Bank-only items: charges (money out) or interest/other income (money in) booked to the ledger. */
    public function adjust(BankStatementLine $line, User $user): BankStatementLine
    {
        $rec = BankReconciliation::with('account')->findOrFail($line->bank_reconciliation_id);
        $this->assertDraft($rec);
        if ($line->status !== 'unmatched') {
            throw ValidationException::withMessages(['line' => 'Only an unmatched line can be booked.']);
        }

        return DB::transaction(function () use ($line, $rec) {
            $amount = abs((float) $line->amount);
            $out = (float) $line->amount < 0;
            $entry = $this->ledger->post($out ? 'bank.charge' : 'bank.income', $line->date, $rec->account->branch_id,
                ($out ? 'Bank charge' : 'Bank receipt').": {$line->description}", $out
                    ? [['account' => $this->accounts->system('bank_charges'), 'debit' => $amount], ['account' => $rec->account, 'credit' => $amount]]
                    : [['account' => $rec->account, 'debit' => $amount], ['account' => $this->accounts->system('income_other'), 'credit' => $amount]],
                $rec, 'journal');
            $bookLine = $entry->lines()->where('account_id', $rec->account_id)->first();
            $line->update(['journal_line_id' => $bookLine?->id, 'status' => 'adjusted']);

            return $line;
        });
    }

    /** Book balance, statement figures and what is still outstanding. */
    public function summary(BankReconciliation $rec): array
    {
        $book = round((float) JournalLine::where('account_id', $rec->account_id)
            ->whereHas('entry', fn ($e) => $e->whereDate('date', '<=', $rec->statement_date))
            ->selectRaw('COALESCE(SUM(debit) - SUM(credit), 0) as b')->value('b'), 2);
        $matchedIds = $rec->lines()->whereNotNull('journal_line_id')->pluck('journal_line_id')->all();
        $outstanding = $this->openBookLines($rec)->reject(fn ($l) => in_array($l->id, $matchedIds, true))->values();
        $outstandingTotal = round($outstanding->sum(fn ($l) => $this->signed($l)), 2);
        $unmatchedStatement = $rec->lines()->where('status', 'unmatched')->get();

        return [
            'book_balance' => $book,
            'statement_balance' => (float) $rec->statement_balance,
            'outstanding_total' => $outstandingTotal,
            'adjusted_book' => round($book - $outstandingTotal, 2),
            'unmatched_statement_total' => round((float) $unmatchedStatement->sum('amount'), 2),
            'difference' => round((float) $rec->statement_balance - ($book - $outstandingTotal), 2),
            'outstanding' => $outstanding->map(fn ($l) => [
                'id' => $l->id, 'date' => $l->entry->date->toDateString(), 'voucher_no' => $l->entry->voucher_no,
                'narration' => $l->entry->narration, 'amount' => $this->signed($l),
            ]),
        ];
    }

    public function complete(BankReconciliation $rec, User $user): BankReconciliation
    {
        $this->assertDraft($rec);
        $summary = $this->summary($rec);
        if ($rec->lines()->where('status', 'unmatched')->exists()) {
            throw ValidationException::withMessages(['reconciliation' => 'Match or book every statement line first.']);
        }
        if (abs($summary['difference']) > 0.005) {
            throw ValidationException::withMessages(['reconciliation' => 'The statement and the books differ by ৳'.number_format($summary['difference'], 2).'.']);
        }

        return DB::transaction(function () use ($rec, $user, $summary) {
            JournalLine::whereIn('id', $rec->lines()->pluck('journal_line_id')->filter())->update(['bank_reconciliation_id' => $rec->id]);
            $rec->update(['status' => 'completed', 'book_balance' => $summary['book_balance'], 'completed_by' => $user->id, 'completed_at' => now()]);

            return $rec;
        });
    }

    /** Book lines on this account up to the statement date not reconciled in an earlier statement. */
    public function openBookLines(BankReconciliation $rec): Collection
    {
        return JournalLine::with('entry')->where('account_id', $rec->account_id)->whereNull('bank_reconciliation_id')
            ->whereHas('entry', fn ($e) => $e->whereDate('date', '<=', $rec->statement_date))
            ->get()->sortBy(fn ($l) => [$l->entry->date->format('Y-m-d'), $l->id])->values();
    }

    private function signed(JournalLine $l): float
    {
        return round((float) $l->debit - (float) $l->credit, 2);
    }

    private function assertDraft(BankReconciliation $rec): void
    {
        if ($rec->status !== 'draft') {
            throw ValidationException::withMessages(['reconciliation' => 'This reconciliation is completed.']);
        }
    }

    public static function moneyAccounts()
    {
        return Account::whereIn('subtype', ['bank', 'mfs'])->where('is_group', false)->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'subtype']);
    }
}
