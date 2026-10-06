<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Activity Logs (Plan §২১ Audit): one list over audit_logs with presets —
 *   system   = changes and business actions (create / update / delete, void, post …)
 *   login    = sign-in, sign-out, failed attempts, password changes
 *   patient  = everything recorded against a child (optionally one child)
 *   clinical = who opened clinical records (clinical profile, assessments, documents)
 * Branch admins only see what their branch staff did or what concerns their branch's children.
 */
class AuditLogController extends Controller
{
    public const LOGIN_ACTIONS = ['login', 'logout', 'login_failed', 'password_changed'];

    public function index(Request $request): JsonResponse|StreamedResponse
    {
        Gate::authorize(Permission::AUDIT_LOGS_VIEW);
        $request->validate([
            'type' => ['nullable', 'in:system,login,patient,clinical'],
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'],
            'user_id' => ['nullable', 'integer'], 'patient_id' => ['nullable', 'integer'],
            'action' => ['nullable', 'string', 'max:30'], 'model' => ['nullable', 'string', 'max:60'],
            'format' => ['nullable', 'in:csv'],
        ]);
        $query = $this->query($request);

        if ($request->input('format') === 'csv') {
            return $this->csv($request, $query);
        }

        $page = $query->with(['user:id,name', 'patient:id,name,patient_code'])->latest('id')->paginate(min($request->integer('per_page', 30), 100));

        return response()->json([
            'data' => collect($page->items())->map(fn (AuditLog $log) => $this->row($log)),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
            'summary' => $request->input('type') === 'login' ? $this->loginSummary($request) : null,
        ]);
    }

    /** Filter options: who acted, which record types appear and which actions exist. */
    public function options(Request $request): JsonResponse
    {
        Gate::authorize(Permission::AUDIT_LOGS_VIEW);
        $scoped = $this->scoped(AuditLog::query(), $request->user());

        return response()->json(['data' => [
            'users' => User::whereIn('id', (clone $scoped)->whereNotNull('user_id')->distinct()->select('user_id'))->orderBy('name')->get(['id', 'name']),
            'models' => (clone $scoped)->whereNotNull('auditable_type')->distinct()->orderBy('auditable_type')->pluck('auditable_type')
                ->map(fn ($t) => ['value' => class_basename($t), 'label' => Str::headline(class_basename($t))])->values(),
            'actions' => (clone $scoped)->distinct()->orderBy('action')->pluck('action'),
        ]]);
    }

    private function query(Request $request): Builder
    {
        $query = $this->scoped(AuditLog::query(), $request->user());

        match ($request->input('type')) {
            'login' => $query->whereIn('action', self::LOGIN_ACTIONS),
            'clinical' => $query->where('action', 'viewed'),
            'patient' => $query->whereNotNull('patient_id'),
            'system' => $query->whereNotIn('action', [...self::LOGIN_ACTIONS, 'viewed']),
            default => null,
        };

        return $query
            ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', $request->date('from')->startOfDay()))
            ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<=', $request->date('to')->endOfDay()))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->when($request->filled('patient_id'), fn ($q) => $q->where('patient_id', $request->integer('patient_id')))
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->string('action')))
            ->when($request->filled('model'), fn ($q) => $q->where('auditable_type', 'App\\Models\\'.Str::studly($request->string('model'))));
    }

    /** Super admin sees everything; others see their branches' staff and children. */
    private function scoped(Builder $query, User $viewer): Builder
    {
        $branches = $viewer->accessibleBranchIds();
        if ($branches === null) {
            return $query;
        }

        return $query->where(fn ($q) => $q
            ->whereIn('user_id', User::whereHas('branches', fn ($b) => $b->whereIn('branches.id', $branches))->select('id'))
            ->orWhereIn('patient_id', Patient::withTrashed()->whereIn('home_branch_id', $branches)->select('id')));
    }

    private function row(AuditLog $log): array
    {
        $changes = [];
        foreach (array_keys(($log->new_values ?? []) + ($log->old_values ?? [])) as $field) {
            if (in_array($field, ['created_at', 'updated_at', 'id', 'password', 'remember_token'], true)) {
                continue;
            }
            $changes[] = ['field' => Str::headline($field), 'old' => $this->short($log->old_values[$field] ?? null), 'new' => $this->short($log->new_values[$field] ?? null)];
        }

        return [
            'id' => $log->id,
            'at' => $log->created_at->toIso8601String(),
            'action' => $log->action,
            'user' => $log->user?->only(['id', 'name']),
            'model' => $log->auditable_type ? Str::headline(class_basename($log->auditable_type)) : null,
            'record_id' => $log->auditable_id,
            'record' => $this->recordLabel($log),
            'patient' => $log->patient?->only(['id', 'name', 'patient_code']),
            'changes' => array_slice($changes, 0, 12),
            'more_changes' => max(0, count($changes) - 12),
            'ip' => $log->ip_address,
            'device' => $this->device($log->user_agent),
        ];
    }

    /** A human name for the record from the logged values (invoice no., code, name …). */
    private function recordLabel(AuditLog $log): ?string
    {
        $values = ($log->new_values ?? []) + ($log->old_values ?? []);
        foreach (['invoice_no', 'receipt_no', 'voucher_no', 'bill_no', 'payment_no', 'asset_code', 'assessment_no', 'patient_code', 'employee_code', 'code', 'name', 'title', 'email'] as $key) {
            if (! empty($values[$key]) && is_scalar($values[$key])) {
                return (string) $values[$key];
            }
        }
        if (isset($values['login'])) {
            return (string) $values['login'];
        }
        // Sign-in rows point at the user themself.
        if ($log->auditable_type === User::class && $log->auditable_id === $log->user_id && $log->user) {
            return $log->user->name;
        }

        return $log->auditable_id ? '#'.$log->auditable_id : null;
    }

    private function short(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = is_scalar($value) ? (is_bool($value) ? ($value ? 'yes' : 'no') : (string) $value) : json_encode($value, JSON_UNESCAPED_UNICODE);

        return Str::limit($text, 80);
    }

    /** "Chrome on Windows" from a user agent string — enough to recognise a device. */
    private function device(?string $agent): ?string
    {
        if (! $agent) {
            return null;
        }
        $browser = collect(['Edg' => 'Edge', 'OPR' => 'Opera', 'Chrome' => 'Chrome', 'Firefox' => 'Firefox', 'Safari' => 'Safari'])->first(fn ($name, $needle) => str_contains($agent, $needle));
        $os = collect(['Android' => 'Android', 'iPhone' => 'iPhone', 'iPad' => 'iPad', 'Windows' => 'Windows', 'Mac OS' => 'Mac', 'Linux' => 'Linux'])->first(fn ($name, $needle) => str_contains($agent, $needle));

        return $browser || $os ? trim(($browser ?? 'Browser').' on '.($os ?? 'unknown')) : Str::limit($agent, 40);
    }

    /** Login History header: sign-ins, failures and addresses with repeated failures. */
    private function loginSummary(Request $request): array
    {
        $base = $this->query($request);

        return [
            'logins' => (clone $base)->where('action', 'login')->count(),
            'failed' => (clone $base)->where('action', 'login_failed')->count(),
            'people' => (clone $base)->where('action', 'login')->distinct()->count('user_id'),
            'suspicious_ips' => (clone $base)->where('action', 'login_failed')->whereNotNull('ip_address')
                ->selectRaw('ip_address, COUNT(*) as attempts, MAX(created_at) as last_at')->groupBy('ip_address')
                ->havingRaw('COUNT(*) >= 5')->orderByDesc('attempts')->limit(10)->get()
                ->map(fn ($r) => ['ip' => $r->ip_address, 'attempts' => (int) $r->attempts, 'last_at' => $r->last_at]),
        ];
    }

    private function csv(Request $request, Builder $query): StreamedResponse
    {
        AuditLogger::log('exported', null, new: ['report' => 'activity-log', 'filters' => $request->except('format')]);
        $rows = $query->with(['user:id,name', 'patient:id,name,patient_code'])->latest('id')->limit(5000)->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Time', 'User', 'Action', 'Record type', 'Record', 'Child', 'Changes', 'IP', 'Device']);
            foreach ($rows as $log) {
                $r = $this->row($log);
                fputcsv($out, [
                    $log->created_at->format('Y-m-d H:i:s'), $r['user']['name'] ?? 'System', $r['action'], $r['model'], $r['record'],
                    $r['patient'] ? "{$r['patient']['name']} ({$r['patient']['patient_code']})" : '',
                    collect($r['changes'])->map(fn ($c) => "{$c['field']}: {$c['old']} → {$c['new']}")->implode('; '), $r['ip'], $r['device'],
                ]);
            }
            fclose($out);
        }, 'activity-log-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
