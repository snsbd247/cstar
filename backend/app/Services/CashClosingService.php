<?php

namespace App\Services;

use App\Models\CashClosing;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * End-of-day cash (Accounts §৮): the system shows what the person took by method, they count the cash,
 * a supervisor receives it; any shortage or excess is posted to Cash Short/Over.
 */
class CashClosingService
{
    public const NOTES = [1000, 500, 200, 100, 50, 20, 10, 5, 2, 1];

    public function __construct(private LedgerService $ledger, private AccountMap $accounts) {}

    /** @return array{by_method: array<string, float>, cash_expenses: float, expected_cash: float, payments: int} */
    public function expected(User $user, int $branchId, Carbon $date): array
    {
        $payments = Payment::where('received_by', $user->id)->where('branch_id', $branchId)->where('status', 'completed')
            ->whereDate('paid_at', $date)->get();
        $byMethod = collect(Payment::METHODS)->mapWithKeys(fn ($m) => [$m => round($payments->where('method', $m)
            ->sum(fn ($p) => $p->type === 'refund' ? -(float) $p->amount : (float) $p->amount), 2)])->all();

        $cashAccount = $this->accounts->cashFor($branchId);
        $cashExpenses = (float) Expense::where('created_by', $user->id)->where('branch_id', $branchId)->whereDate('date', $date)
            ->where('status', 'posted')->where('paid_from_account_id', $cashAccount->id)->sum('amount');

        return [
            'by_method' => $byMethod,
            'cash_expenses' => round($cashExpenses, 2),
            'expected_cash' => round($byMethod['cash'] - $cashExpenses, 2),
            'payments' => $payments->where('type', 'payment')->count(),
        ];
    }

    public function close(User $user, array $data): CashClosing
    {
        $date = Carbon::parse($data['date'] ?? today());
        if (CashClosing::where('user_id', $user->id)->where('branch_id', $data['branch_id'])->whereDate('date', $date)->exists()) {
            throw ValidationException::withMessages(['date' => 'You have already closed your cash for this day.']);
        }

        $expected = $this->expected($user, (int) $data['branch_id'], $date);
        $denominations = array_filter(array_map('intval', $data['denominations'] ?? []));
        $counted = $denominations ? array_sum(array_map(fn ($note, $n) => (int) $note * $n, array_keys($denominations), $denominations)) : (float) $data['counted_cash'];
        $difference = round($counted - $expected['expected_cash'], 2);
        if (abs($difference) > 0.001 && blank($data['reason'] ?? null)) {
            throw ValidationException::withMessages(['reason' => 'Explain why the cash is '.($difference < 0 ? 'short' : 'over').' by ৳'.number_format(abs($difference), 2).'.']);
        }

        return CashClosing::create([
            'branch_id' => $data['branch_id'],
            'user_id' => $user->id,
            'date' => $date,
            'expected' => ['by_method' => $expected['by_method'], 'cash_expenses' => $expected['cash_expenses']],
            'expected_cash' => $expected['expected_cash'],
            'counted_cash' => $counted,
            'denominations' => $denominations ?: null,
            'difference' => $difference,
            'reason' => $data['reason'] ?? null,
            'status' => 'submitted',
        ]);
    }

    /** Supervisor takes the cash; a difference is booked so the cash box matches what was handed over. */
    public function receive(CashClosing $closing, User $user): CashClosing
    {
        if ($closing->status !== 'submitted') {
            throw ValidationException::withMessages(['closing' => 'Already received.']);
        }
        if ($closing->user_id === $user->id && ! $user->isSuperAdmin()) {
            throw ValidationException::withMessages(['closing' => 'Someone else must receive your cash.']);
        }

        return DB::transaction(function () use ($closing, $user) {
            $diff = (float) $closing->difference;
            $entry = null;
            if (abs($diff) > 0.001) {
                $cash = $this->accounts->cashFor($closing->branch_id);
                $shortOver = $this->accounts->system('cash_short_over');
                $closing->loadMissing('user');
                $entry = $this->ledger->post('cash.short_over', $closing->date, $closing->branch_id,
                    'Cash '.($diff < 0 ? 'short' : 'over')." — {$closing->user->name}, {$closing->date->format('d M Y')}: {$closing->reason}", $diff < 0
                        ? [['account' => $shortOver, 'debit' => -$diff], ['account' => $cash, 'credit' => -$diff]]
                        : [['account' => $cash, 'debit' => $diff], ['account' => $shortOver, 'credit' => $diff]],
                    $closing);
            }
            $closing->update(['status' => 'received', 'received_by' => $user->id, 'received_at' => now(), 'journal_entry_id' => $entry?->id]);

            return $closing;
        });
    }

    /** After a person closes a day, their payments of that day are locked (only accounts staff may change them). */
    public static function isClosed(int $userId, Carbon $date): bool
    {
        return CashClosing::where('user_id', $userId)->whereDate('date', $date)->exists();
    }
}
