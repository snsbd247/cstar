<?php

namespace App\Services;

use App\Enums\Permission;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Manual vouchers with maker-checker (Accounts §৪):
 *   draft → submit → (≤ approval limit) posted at once / (> limit) waits → approve (another person) → posted
 *                                                                        ↘ reject (reason)
 *   posted → reverse (mirror entry; nothing is ever deleted)
 */
class VoucherService
{
    private const MONEY_SUBTYPES = ['cash', 'bank', 'mfs'];

    public function __construct(private IdGenerator $ids, private LedgerService $ledger, private AccountSettings $settings) {}

    /** @param  array{type: string, date: string, branch_id: int, narration: string, lines: list<array{account_id: int, debit?: float|string|null, credit?: float|string|null, memo?: ?string}>}  $data */
    public function create(array $data, User $user): Voucher
    {
        [$lines, $amount] = $this->checkLines($data['type'], $data['lines']);
        $this->assertOpenDate($data['date']);

        return DB::transaction(function () use ($data, $user, $lines, $amount) {
            $date = Carbon::parse($data['date']);
            $voucher = Voucher::create([
                ...Arr::only($data, ['type', 'branch_id', 'narration', 'attachment_path']),
                'voucher_no' => $this->ids->next("voucher_{$data['type']}", Voucher::TYPES[$data['type']], 5, $date->year),
                'date' => $date,
                'amount' => $amount,
                'status' => 'draft',
                'prepared_by' => $user->id,
            ]);
            $this->saveLines($voucher, $lines);

            return $voucher;
        });
    }

    public function update(Voucher $voucher, array $data): Voucher
    {
        if (! in_array($voucher->status, ['draft', 'rejected'], true)) {
            throw ValidationException::withMessages(['voucher' => 'Only a draft or rejected voucher can be edited.']);
        }
        [$lines, $amount] = $this->checkLines($voucher->type, $data['lines'] ?? $voucher->lines->map->only(['account_id', 'debit', 'credit', 'memo'])->all());
        $this->assertOpenDate($data['date'] ?? $voucher->date);

        return DB::transaction(function () use ($voucher, $data, $lines, $amount) {
            $voucher->update([...Arr::only($data, ['date', 'branch_id', 'narration']), 'amount' => $amount, 'status' => 'draft', 'reject_reason' => null]);
            $voucher->lines()->delete();
            $this->saveLines($voucher, $lines);

            return $voucher;
        });
    }

    /** Small amounts post straight away; larger ones wait for a second person (A5). */
    public function submit(Voucher $voucher, User $user): Voucher
    {
        if (! in_array($voucher->status, ['draft', 'rejected'], true)) {
            throw ValidationException::withMessages(['voucher' => 'This voucher has already been submitted.']);
        }

        if ((float) $voucher->amount <= $this->settings->approvalLimit()) {
            return $this->post($voucher, $user);
        }

        $voucher->update(['status' => 'submitted', 'submitted_at' => now()]);
        $this->syncExpense($voucher);
        app(NotificationService::class)->staffTemplate(Permission::ACCOUNTS_VOUCHER_APPROVE, $voucher->branch_id, 'voucher.submitted', [
            'voucher_no' => $voucher->voucher_no, 'amount' => '৳'.number_format((float) $voucher->amount), 'narration' => $voucher->narration, 'by' => $user->name,
        ], '/app/accounts/vouchers', $user->id);

        return $voucher;
    }

    public function approve(Voucher $voucher, User $user): Voucher
    {
        if ($voucher->status !== 'submitted') {
            throw ValidationException::withMessages(['voucher' => 'Only a submitted voucher can be approved.']);
        }
        if (! $user->can(Permission::ACCOUNTS_VOUCHER_APPROVE)) {
            throw ValidationException::withMessages(['voucher' => 'You cannot approve vouchers.']);
        }
        if ($voucher->prepared_by === $user->id && ! $user->isSuperAdmin()) {
            throw ValidationException::withMessages(['voucher' => 'Someone other than the preparer must approve this voucher.']);
        }

        return $this->post($voucher, $user);
    }

    public function reject(Voucher $voucher, string $reason, User $user): Voucher
    {
        if ($voucher->status !== 'submitted') {
            throw ValidationException::withMessages(['voucher' => 'Only a submitted voucher can be rejected.']);
        }
        $voucher->update(['status' => 'rejected', 'reject_reason' => $reason, 'approved_by' => $user->id, 'approved_at' => now()]);
        $this->syncExpense($voucher);

        return $voucher;
    }

    public function reverse(Voucher $voucher, string $reason, User $user): Voucher
    {
        if ($voucher->status !== 'posted' || ! $voucher->journalEntry) {
            throw ValidationException::withMessages(['voucher' => 'Only a posted voucher can be reversed.']);
        }

        return DB::transaction(function () use ($voucher, $reason) {
            $this->ledger->reverse($voucher->journalEntry, "{$voucher->voucher_no}: {$reason}");
            $voucher->update(['status' => 'reversed']);
            $this->syncExpense($voucher);
            AuditLogger::log('reversed', $voucher, new: ['reason' => $reason]);

            return $voucher;
        });
    }

    public function deleteDraft(Voucher $voucher): void
    {
        if (! in_array($voucher->status, ['draft', 'rejected'], true)) {
            throw ValidationException::withMessages(['voucher' => 'Posted vouchers are never deleted — reverse instead.']);
        }
        $voucher->expense?->delete();
        $voucher->delete();
    }

    private function post(Voucher $voucher, User $user): Voucher
    {
        return DB::transaction(function () use ($voucher, $user) {
            $voucher->loadMissing('lines.account');
            $entry = $this->ledger->post("voucher.{$voucher->type}", $voucher->date, $voucher->branch_id,
                "{$voucher->voucher_no} — {$voucher->narration}",
                $voucher->lines->map(fn ($l) => ['account' => $l->account, 'debit' => $l->debit, 'credit' => $l->credit, 'memo' => $l->memo])->all(),
                $voucher, $voucher->type);
            $entry?->update(['approved_by' => $user->id]);

            $voucher->update([
                'status' => 'posted', 'journal_entry_id' => $entry?->id, 'approved_by' => $user->id, 'approved_at' => now(),
                'submitted_at' => $voucher->submitted_at ?? now(),
            ]);
            $this->syncExpense($voucher);

            return $voucher;
        });
    }

    /** @return array{0: list<array<string, mixed>>, 1: float} */
    private function checkLines(string $type, array $lines): array
    {
        $clean = [];
        $accounts = Account::whereIn('id', array_column($lines, 'account_id'))->get()->keyBy('id');
        foreach ($lines as $i => $line) {
            $debit = round((float) ($line['debit'] ?? 0), 2);
            $credit = round((float) ($line['credit'] ?? 0), 2);
            if ($debit <= 0 && $credit <= 0) {
                continue;
            }
            if ($debit > 0 && $credit > 0) {
                throw ValidationException::withMessages(["lines.$i.debit" => 'A line is either a debit or a credit, not both.']);
            }
            $account = $accounts[$line['account_id']] ?? null;
            if (! $account || $account->is_group || ! $account->is_active) {
                throw ValidationException::withMessages(["lines.$i.account_id" => 'Choose an active (non-group) account.']);
            }
            $clean[] = ['account' => $account, 'debit' => $debit, 'credit' => $credit, 'memo' => $line['memo'] ?? null];
        }

        if (count($clean) < 2) {
            throw ValidationException::withMessages(['lines' => 'A voucher needs at least one debit and one credit line.']);
        }
        $dr = round(array_sum(array_column($clean, 'debit')), 2);
        $cr = round(array_sum(array_column($clean, 'credit')), 2);
        if (abs($dr - $cr) > 0.001) {
            throw ValidationException::withMessages(['lines' => 'Debit ('.number_format($dr, 2).') and credit ('.number_format($cr, 2).') must be equal.']);
        }

        // The voucher type must match the money movement.
        $money = fn (array $l) => in_array($l['account']->subtype, self::MONEY_SUBTYPES, true);
        $moneyCredit = collect($clean)->contains(fn ($l) => $l['credit'] > 0 && $money($l));
        $moneyDebit = collect($clean)->contains(fn ($l) => $l['debit'] > 0 && $money($l));
        $message = match ($type) {
            'payment' => $moneyCredit ? null : 'A payment voucher must pay from cash, bank or bKash/Nagad (credit side).',
            'receipt' => $moneyDebit ? null : 'A receipt voucher must receive into cash, bank or bKash/Nagad (debit side).',
            'contra' => collect($clean)->every($money) ? null : 'A contra voucher only moves money between cash, bank and bKash/Nagad accounts.',
            default => null,
        };
        if ($message) {
            throw ValidationException::withMessages(['type' => $message]);
        }

        return [$clean, $dr];
    }

    private function saveLines(Voucher $voucher, array $lines): void
    {
        foreach ($lines as $i => $l) {
            $voucher->lines()->create(['account_id' => $l['account']->id, 'debit' => $l['debit'], 'credit' => $l['credit'], 'memo' => $l['memo'], 'sort_order' => $i]);
        }
    }

    private function assertOpenDate(Carbon|string $date): void
    {
        $date = Carbon::parse($date);
        $closed = AccountingPeriod::where('year', $date->year)->where('month', $date->month)->where('status', 'closed')->exists();
        if ($closed) {
            throw ValidationException::withMessages(['date' => $date->format('F Y').' is closed. Use a date in an open month.']);
        }
        if ($date->isAfter(today())) {
            throw ValidationException::withMessages(['date' => 'A voucher cannot be dated in the future.']);
        }
    }

    /** An expense mirrors the state of its voucher. */
    private function syncExpense(Voucher $voucher): void
    {
        $voucher->expense()->update(['status' => $voucher->status]);
    }
}
