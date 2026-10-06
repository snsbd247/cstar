<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Guardian;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Go-live (Sprint 17, decision A9): the books start from opening balances on the go-live date, and the
 * children already at the center are brought in from the paper register with their unpaid dues.
 * Every opening amount is balanced against Retained Earnings — the same rule vendor openings and
 * go-live fixed assets already follow.
 */
class GoLiveService
{
    public const EVENT = 'opening.balance';

    /** Child CSV columns, in template order. */
    public const COLUMNS = [
        'name', 'name_bn', 'date_of_birth', 'gender', 'phone', 'guardian_name', 'guardian_relationship', 'guardian_phone',
        'father_name', 'mother_name', 'address', 'branch_code', 'registration_date', 'old_file_no', 'notes', 'opening_due',
    ];

    public function __construct(private LedgerService $ledger, private AccountMap $accounts, private PatientService $patients, private InvoiceService $invoices) {}

    /**
     * Accounts that take an opening balance here. Left out: the balancing account, accounts that belong to
     * one party (patient dues come from the import, vendor dues from each vendor) and fixed assets (register
     * equipment under Fixed Assets).
     */
    public function openingAccounts()
    {
        $skip = collect(['retained_earnings', 'receivable', 'payable', 'patient_advances', 'unearned_package'])
            ->map(fn ($k) => $this->accounts->system($k)->id);

        return Account::where('is_group', false)->where('is_active', true)->whereIn('type', ['asset', 'liability', 'equity'])
            ->whereNotIn('id', $skip)->where('code', 'not like', '16%')->orderBy('code')->get();
    }

    public function currentEntry(): ?JournalEntry
    {
        return JournalEntry::where('event', self::EVENT)->where('status', 'posted')->with('lines')->latest('id')->first();
    }

    /**
     * Posts (or re-posts) the opening balances: one journal entry; the difference goes to Retained Earnings.
     *
     * @param  array<int, float|string|null>  $amounts  account id => amount (positive, on the account's normal side)
     */
    public function saveOpeningBalances(Carbon $date, array $amounts, User $user): ?JournalEntry
    {
        $accounts = $this->openingAccounts()->keyBy('id');
        $unknown = array_diff(array_keys(array_filter($amounts, fn ($v) => (float) $v != 0.0)), $accounts->keys()->all());
        if ($unknown) {
            throw ValidationException::withMessages(['amounts' => 'These accounts cannot take an opening balance here.']);
        }

        return DB::transaction(function () use ($date, $amounts, $user, $accounts) {
            if ($old = $this->currentEntry()) {
                $this->ledger->reverse($old, "Opening balances re-entered by {$user->name}");
            }
            $lines = [];
            $net = 0.0;
            foreach ($amounts as $id => $amount) {
                $amount = round((float) $amount, 2);
                if ($amount == 0.0) {
                    continue;
                }
                $account = $accounts[$id];
                $debit = ($account->normal_balance === 'debit') === ($amount > 0);
                $lines[] = ['account' => $account, $debit ? 'debit' : 'credit' => abs($amount), 'memo' => 'Opening balance', 'branch_id' => $account->branch_id];
                $net += $debit ? abs($amount) : -abs($amount);
            }
            if (! $lines) {
                return null;
            }
            if (round($net, 2) != 0.0) {
                $lines[] = ['account' => $this->accounts->system('retained_earnings'), $net > 0 ? 'credit' : 'debit' => abs(round($net, 2)), 'memo' => 'Balancing figure (owner’s equity at go-live)'];
            }

            return $this->ledger->post(self::EVENT, $date, null, 'Opening balances on '.$date->format('d M Y').' (go-live)', $lines, null, 'journal', keepDate: true);
        });
    }

    /** Reads the uploaded CSV (UTF-8, first row = column names) into rows keyed by column. */
    public function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        $header = null;
        $rows = [];
        while (($cells = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            if ($header === null) {
                $header = array_map(fn ($h) => Str::snake(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), $cells);

                continue;
            }
            if (count(array_filter($cells, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;
            }
            $rows[] = array_combine($header, array_pad(array_slice($cells, 0, count($header)), count($header), null));
        }
        fclose($handle);
        if (! $header || ! in_array('name', $header, true)) {
            throw ValidationException::withMessages(['file' => 'The first row must hold the column names — download the template.']);
        }

        return $rows;
    }

    /**
     * Checks every row and, unless $dryRun, registers the good ones. Returns one result per row:
     * ok / imported / duplicate / error, with messages, so the screen can show a preview first.
     */
    public function importPatients(array $rows, User $user, bool $dryRun, ?Carbon $goLiveDate = null): array
    {
        $branches = Branch::whereNull('deleted_at')->get()->keyBy(fn ($b) => strtoupper($b->code));
        $default = $user->branches()->wherePivot('is_primary', true)->first() ?? ($user->accessibleBranchIds() === null ? Branch::orderBy('id')->first() : $user->branches()->first());
        $results = [];

        foreach ($rows as $i => $raw) {
            $line = $i + 2; // row 1 is the header
            [$data, $errors] = $this->mapRow($raw, $branches, $default, $user);
            $name = $data['name'] ?? '';
            if ($errors) {
                $results[] = ['row' => $line, 'name' => $name, 'status' => 'error', 'messages' => $errors];

                continue;
            }
            $duplicate = Patient::where('name', $data['name'])->whereDate('date_of_birth', $data['date_of_birth'])->where('phone', $data['phone'])->first();
            if ($duplicate) {
                $results[] = ['row' => $line, 'name' => $name, 'status' => 'duplicate', 'messages' => ["Already registered as {$duplicate->patient_code}"]];

                continue;
            }
            if ($dryRun) {
                $results[] = ['row' => $line, 'name' => $name, 'status' => 'ok', 'messages' => array_filter([$data['_due'] > 0 ? 'Previous dues ৳'.number_format($data['_due']) : null, $data['guardian']['id'] ?? null ? 'Guardian already registered (sibling) — linked' : null])];

                continue;
            }
            try {
                $patient = DB::transaction(function () use ($data, $user, $goLiveDate) {
                    $patient = $this->patients->register(collect($data)->except(['_due'])->all(), $user);
                    if ($data['_due'] > 0) {
                        $this->invoices->createAndIssue($patient, $patient->home_branch_id, [[
                            'item_type' => InvoiceItem::OPENING_BALANCE, 'description' => 'Previous dues (before C-STAR system)', 'quantity' => 1, 'unit_price' => $data['_due'],
                        ]], $user, ['notes' => 'Imported at go-live']);
                    }

                    return $patient;
                });
                $results[] = ['row' => $line, 'name' => $name, 'status' => 'imported', 'messages' => [$patient->patient_code]];
            } catch (ValidationException $e) {
                $results[] = ['row' => $line, 'name' => $name, 'status' => 'error', 'messages' => collect($e->errors())->flatten()->all()];
            } catch (Throwable $e) {
                report($e);
                $results[] = ['row' => $line, 'name' => $name, 'status' => 'error', 'messages' => ['Could not save this row: '.$e->getMessage()]];
            }
        }

        return $results;
    }

    /** @return array{0: array, 1: list<string>} */
    private function mapRow(array $raw, $branches, ?Branch $default, User $user): array
    {
        $v = fn (string $k) => trim((string) ($raw[$k] ?? ''));
        $errors = [];

        $phone = $this->phone($v('phone'));
        $guardianPhone = $this->phone($v('guardian_phone')) ?: $phone;
        $branch = $v('branch_code') !== '' ? ($branches[strtoupper($v('branch_code'))] ?? null) : $default;
        $dob = $this->date($v('date_of_birth'));
        $registered = $v('registration_date') !== '' ? $this->date($v('registration_date')) : today();
        $gender = $this->gender($v('gender'));
        $relationship = $this->relationship($v('guardian_relationship'));
        $due = $v('opening_due') === '' ? 0.0 : (float) str_replace([',', '৳', 'Tk', 'tk'], '', strtr($v('opening_due'), array_combine(['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'], range(0, 9))));

        if ($v('name') === '') {
            $errors[] = 'Name is missing.';
        }
        if (! $dob || $dob->isFuture()) {
            $errors[] = 'Date of birth is missing or not a date (use 2019-05-21 or 21/05/2019).';
        }
        if (! $gender) {
            $errors[] = 'Gender must be male / female / other (or ছেলে / মেয়ে).';
        }
        if (! $phone) {
            $errors[] = 'Mobile number must be 11 digits starting 01 (e.g. 01712345678).';
        }
        if ($v('guardian_name') === '' && ! Guardian::where('phone', $guardianPhone)->exists()) {
            $errors[] = 'Guardian name is missing.';
        }
        if (! $relationship) {
            $errors[] = 'Guardian relationship must be one of: '.implode(', ', Guardian::RELATIONSHIPS).' (or বাবা / মা).';
        }
        if (! $branch) {
            $errors[] = "Unknown branch code \"{$v('branch_code')}\".";
        } elseif (! $user->canAccessBranch($branch->id)) {
            $errors[] = "You cannot register children in {$branch->name}.";
        }
        if (! $registered || $registered->isFuture()) {
            $errors[] = 'Registration date is not a valid past date.';
        }
        if ($due < 0) {
            $errors[] = 'Previous dues cannot be negative.';
        }

        $existingGuardian = $guardianPhone ? Guardian::where('phone', $guardianPhone)->first() : null;
        $notes = trim(implode("\n", array_filter([$v('old_file_no') ? 'Old file no: '.$v('old_file_no') : null, $v('notes') ?: null])));

        return [[
            'home_branch_id' => $branch?->id, 'name' => $v('name'), 'name_bn' => $v('name_bn') ?: null,
            'date_of_birth' => $dob?->toDateString(), 'gender' => $gender, 'phone' => $phone,
            'father_name' => $v('father_name') ?: null, 'mother_name' => $v('mother_name') ?: null, 'address' => $v('address') ?: null,
            'registration_date' => $registered?->toDateString(), 'notes' => $notes ?: null,
            'guardian' => $existingGuardian
                ? ['id' => $existingGuardian->id, 'relationship' => $relationship]
                : ['name' => $v('guardian_name'), 'phone' => $guardianPhone, 'relationship' => $relationship],
            // Children already attending gave consent on paper.
            'consents' => ['treatment' => true],
            '_due' => round($due, 2),
        ], $errors];
    }

    private function phone(string $value): ?string
    {
        $digits = preg_replace('/\D/', '', strtr($value, array_combine(['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'], range(0, 9))));
        if (str_starts_with($digits, '880')) {
            $digits = substr($digits, 2);
        } elseif (strlen($digits) === 10 && str_starts_with($digits, '1')) {
            $digits = '0'.$digits; // Excel drops the leading zero
        }

        return preg_match('/^01[3-9]\d{8}$/', $digits) ? $digits : null;
    }

    private function date(string $value): ?Carbon
    {
        if ($value === '') {
            return null;
        }
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'j/n/Y'] as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, $value);
                if ($date && $date->format($format) === $value) {
                    return $date;
                }
            } catch (Throwable) {
                // try the next format
            }
        }

        return null;
    }

    private function gender(string $value): ?string
    {
        return match (mb_strtolower($value)) {
            'male', 'm', 'boy', 'ছেলে', 'পুরুষ' => 'male',
            'female', 'f', 'girl', 'মেয়ে', 'মহিলা' => 'female',
            'other', 'অন্যান্য' => 'other',
            default => null,
        };
    }

    private function relationship(string $value): ?string
    {
        $value = mb_strtolower($value);
        $bangla = ['বাবা' => 'father', 'পিতা' => 'father', 'মা' => 'mother', 'মাতা' => 'mother', 'দাদা' => 'grandparent', 'দাদি' => 'grandparent', 'নানা' => 'grandparent', 'নানি' => 'grandparent',
            'ভাই' => 'sibling', 'বোন' => 'sibling', 'চাচা' => 'uncle', 'মামা' => 'uncle', 'চাচি' => 'aunt', 'খালা' => 'aunt', 'ফুফু' => 'aunt'];
        return in_array($value, Guardian::RELATIONSHIPS, true) ? $value : ($bangla[$value] ?? null);
    }
}
