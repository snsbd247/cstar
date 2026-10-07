<?php

namespace App\Http\Controllers\Api\V1\Accounts;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Branch;
use App\Models\CashClosing;
use App\Models\Voucher;
use App\Services\AuditLogger;
use App\Services\FinancialReportService;
use App\Services\PdfService;
use App\Services\XlsxWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Financial reports (Accounts §১২): trial balance, general ledger / cash & bank book, day book,
 * income statement and balance sheet — JSON for the screen, ?format=pdf for printing, ?format=xlsx for Excel.
 */
class ReportController extends Controller
{
    public function __construct(private FinancialReportService $reports) {}

    public function dashboard(Request $request): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_VIEW);
        $branchId = $this->branch($request);
        $branches = $request->user()->accessibleBranchIds();

        return response()->json(['data' => [
            ...$this->reports->dashboard($branchId),
            'pending_vouchers' => Voucher::where('status', 'submitted')->when($branches !== null, fn ($q) => $q->whereIn('branch_id', $branches))->count(),
            'pending_closings' => CashClosing::where('status', 'submitted')->when($branches !== null, fn ($q) => $q->whereIn('branch_id', $branches))->count(),
        ]]);
    }

    public function trialBalance(Request $request): JsonResponse|Response
    {
        Gate::authorize(Permission::ACCOUNTS_REPORTS);
        $to = $request->date('to') ?? today();
        $data = ['to' => $to->toDateString(), ...$this->reports->trialBalance($to, $request->date('from'), $this->branch($request))];

        return $this->respond($request, 'trial-balance', 'Trial Balance', $data);
    }

    public function ledger(Request $request): JsonResponse|Response
    {
        Gate::authorize(Permission::ACCOUNTS_REPORTS);
        $request->validate(['account_id' => ['required', 'integer', 'exists:accounts,id']]);
        $account = Account::findOrFail($request->integer('account_id'));
        abort_if($account->is_group, 422, 'Choose a single account, not a group.');
        [$from, $to] = $this->range($request);

        return $this->respond($request, 'ledger', "Ledger — {$account->code} {$account->name}", $this->reports->ledger($account, $from, $to, $this->branch($request)));
    }

    public function dayBook(Request $request): JsonResponse|Response
    {
        Gate::authorize(Permission::ACCOUNTS_REPORTS);
        $date = $request->date('date') ?? today();

        return $this->respond($request, 'day-book', 'Day Book', ['date' => $date->toDateString(), 'entries' => $this->reports->dayBook($date, $this->branch($request))]);
    }

    public function incomeStatement(Request $request): JsonResponse|Response
    {
        Gate::authorize(Permission::ACCOUNTS_REPORTS);
        [$from, $to] = $this->range($request);

        return $this->respond($request, 'income-statement', 'Income Statement', $this->reports->incomeStatement($from, $to, $this->branch($request)));
    }

    public function balanceSheet(Request $request): JsonResponse|Response
    {
        Gate::authorize(Permission::ACCOUNTS_REPORTS);

        return $this->respond($request, 'balance-sheet', 'Balance Sheet', $this->reports->balanceSheet($request->date('as_of') ?? today(), $this->branch($request)));
    }

    public function cashFlow(Request $request): JsonResponse|Response
    {
        Gate::authorize(Permission::ACCOUNTS_REPORTS);
        [$from, $to] = $this->range($request);

        return $this->respond($request, 'cash-flow', 'Cash Flow Statement', $this->reports->cashFlow($from, $to, $this->branch($request)));
    }

    /** Default range: this month. */
    private function range(Request $request): array
    {
        $from = $request->date('from') ?? today()->startOfMonth();
        $to = $request->date('to') ?? today();
        abort_if($to->lt($from), 422, 'The end date is before the start date.');

        return [$from, $to];
    }

    /** Branch filter; staff limited to some branches must pick one of theirs (null = all, super admin only). */
    private function branch(Request $request): ?int
    {
        $branches = $request->user()->accessibleBranchIds();
        $id = $request->integer('branch_id') ?: null;
        if ($id) {
            abort_unless($request->user()->canAccessBranch($id), 403);

            return $id;
        }

        return $branches === null ? null : ($branches[0] ?? null);
    }

    private function respond(Request $request, string $view, string $title, array $data): JsonResponse|Response
    {
        $format = $request->query('format');
        if (! in_array($format, ['pdf', 'xlsx'], true)) {
            return response()->json(['data' => $data]);
        }

        $branchId = $this->branch($request);
        AuditLogger::log('exported', null, new: ['report' => $view, 'format' => $format, 'filters' => $request->query()]);
        $vars = ['data' => $data, 'branch' => $branchId ? Branch::find($branchId)?->name : 'All branches'];
        $filename = "{$view}-".Carbon::now()->format('Ymd');

        if ($format === 'xlsx') {
            // Excel = the same tables as the PDF, with amounts as real numbers.
            $bytes = app(XlsxWriter::class)->fromHtml(view("pdf.accounts.{$view}", $vars)->render(), [$title, $vars['branch']], $title);

            return response($bytes, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => "attachment; filename=\"{$filename}.xlsx\"",
            ]);
        }

        return app(PdfService::class)->response("pdf.accounts.{$view}", $vars, $title, "{$filename}.pdf");
    }
}
