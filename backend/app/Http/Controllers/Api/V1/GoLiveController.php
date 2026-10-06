<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Services\AuditLogger;
use App\Services\GoLiveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Settings → Go-live (Sprint 17): opening balances (decision A9) and importing the children already at the center. */
class GoLiveController extends Controller
{
    public function __construct(private GoLiveService $goLive) {}

    public function openingBalances(): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_COA_MANAGE);
        $entry = $this->goLive->currentEntry();
        $amounts = $entry ? $entry->lines->groupBy('account_id')->map(fn ($lines) => round($lines->sum(fn ($l) => (float) $l->debit - (float) $l->credit), 2)) : collect();

        return response()->json(['data' => [
            'entry' => $entry ? ['voucher_no' => $entry->voucher_no, 'date' => $entry->date->toDateString()] : null,
            'accounts' => $this->goLive->openingAccounts()->map(fn (Account $a) => [
                'id' => $a->id, 'code' => $a->code, 'name' => $a->name, 'type' => $a->type, 'subtype' => $a->subtype,
                // Shown on the account's normal side: assets as debit, liabilities and equity as credit.
                'amount' => isset($amounts[$a->id]) ? ($a->normal_balance === 'debit' ? $amounts[$a->id] : -$amounts[$a->id]) : null,
            ])->values(),
        ]]);
    }

    public function saveOpeningBalances(Request $request): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_COA_MANAGE);
        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'amounts' => ['present', 'array'],
            'amounts.*' => ['nullable', 'numeric', 'between:-100000000,100000000'],
        ]);
        $entry = $this->goLive->saveOpeningBalances(Carbon::parse($data['date']), $data['amounts'], $request->user());
        AuditLogger::log('golive.opening_balances', $entry, null, ['date' => $data['date']]);

        return $this->openingBalances();
    }

    /** CSV template with one example row (UTF-8 with BOM so Excel keeps Bangla). */
    public function template(): StreamedResponse
    {
        Gate::authorize(Permission::PATIENTS_CREATE);

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, GoLiveService::COLUMNS);
            fputcsv($out, ['Example Child', 'উদাহরণ শিশু', '2019-05-21', 'male', '01712345678', 'Example Mother', 'mother', '', '', '', 'Mirpur, Dhaka', '', '2025-03-01', 'F-123', 'Delete this example row', '1500']);
            fclose($out);
        }, 'cstar-children-import-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** POST with dry_run=1 checks the file and shows what will happen; dry_run=0 registers the good rows. */
    public function importPatients(Request $request): JsonResponse
    {
        Gate::authorize(Permission::PATIENTS_CREATE);
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'], 'dry_run' => ['required', 'boolean']]);
        @set_time_limit(300);
        $rows = $this->goLive->readCsv($request->file('file')->getRealPath());
        abort_if(count($rows) > 2000, 422, 'Up to 2,000 children per file — split the list.');

        $results = $this->goLive->importPatients($rows, $request->user(), $request->boolean('dry_run'));
        if (! $request->boolean('dry_run')) {
            AuditLogger::log('golive.patients_imported', null, null, ['rows' => count($rows), 'imported' => collect($results)->where('status', 'imported')->count()]);
        }

        return response()->json(['data' => $results, 'summary' => collect($results)->countBy('status')]);
    }
}
