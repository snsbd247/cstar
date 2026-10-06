<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\PdfService;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Plan §২০ reports: choose → filter → table + chart → export (PDF / CSV that opens in Excel). */
class ReportController extends Controller
{
    /** GET /reports — the reports this user may run. */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize(Permission::REPORTS_VIEW);

        return response()->json(['data' => collect(ReportService::CATALOG)
            ->filter(fn ($r) => $request->user()->can($r[2]))
            ->map(fn ($r, $key) => ['key' => $key, 'group' => $r[0], 'title' => $r[1], 'filters' => $r[3]])->values()]);
    }

    /** GET /reports/{key}?from=&to=&branch_id=&service_id=&therapist_id=&format=pdf|csv */
    public function show(Request $request, string $key, ReportService $reports): JsonResponse|Response
    {
        Gate::authorize(Permission::REPORTS_VIEW);
        abort_unless(isset(ReportService::CATALOG[$key]), 404);
        Gate::authorize(ReportService::CATALOG[$key][2]);
        $data = $request->validate([
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'service_id' => ['nullable', 'integer'], 'therapist_id' => ['nullable', 'integer'],
            'format' => ['nullable', 'in:pdf,csv'],
        ]);

        $branches = $request->user()->accessibleBranchIds();
        if ($data['branch_id'] ?? null) {
            abort_unless($request->user()->canAccessBranch((int) $data['branch_id']), 403);
            $branches = [(int) $data['branch_id']];
        }
        $from = isset($data['from']) ? Carbon::parse($data['from']) : today()->startOfMonth();
        $to = isset($data['to']) ? Carbon::parse($data['to']) : today();
        abort_if($from->diffInDays($to) > 731, 422, 'Choose a range of two years or less.');

        $report = $reports->run($key, $from, $to, $branches, $data);
        if (! isset($data['format'])) {
            return response()->json(['data' => $report]);
        }

        AuditLogger::log('exported', null, new: ['report' => $key, 'format' => $data['format'], 'from' => $from->toDateString(), 'to' => $to->toDateString()]);
        $filename = "{$key}-{$from->format('Ymd')}-{$to->format('Ymd')}";

        return $data['format'] === 'pdf'
            ? app(PdfService::class)->response('pdf.report', ['report' => $report], $report['title'], "{$filename}.pdf")
            : $this->csv($report, "{$filename}.csv");
    }

    /** UTF-8 CSV with a BOM so Excel shows Bangla names correctly. */
    private function csv(array $report, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_column($report['columns'], 'label'));
            foreach ($report['rows'] as $row) {
                fputcsv($out, array_map(fn ($c) => $row[$c['key']] ?? '', $report['columns']));
            }
            if ($report['totals']) {
                fputcsv($out, array_map(fn ($c) => $report['totals'][$c['key']] ?? '', $report['columns']));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
