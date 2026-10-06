<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\NotificationTemplates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Notifications menu (Notification Center, Templates, Logs) and Users → Branch Access (Sprint 16).
 */
class NotificationAdminController extends Controller
{
    /** Notification Center: the signed-in person's own notifications, all of them, page by page. */
    public function center(Request $request): JsonResponse
    {
        $page = $request->user()->notifications()
            ->when($request->input('filter') === 'unread', fn ($q) => $q->whereNull('read_at'))
            ->latest()->paginate(25);

        return response()->json([
            'data' => collect($page->items())->map(fn ($n) => ['id' => $n->id, ...$n->data, 'read_at' => $n->read_at, 'created_at' => $n->created_at]),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
            'unread' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    /** Notification Logs: every in-app notification sent to anyone, with whether it was read. */
    public function logs(Request $request): JsonResponse
    {
        Gate::authorize(Permission::NOTIFICATIONS_SEND);
        $branches = $request->user()->accessibleBranchIds();
        $q = trim((string) $request->input('q'));

        $page = DB::table('notifications')
            ->join('users', 'users.id', '=', 'notifications.notifiable_id')
            ->where('notifications.notifiable_type', User::class)
            ->when($branches !== null, fn ($n) => $n->where(fn ($w) => $w
                ->whereExists(fn ($b) => $b->from('branch_user')->whereColumn('branch_user.user_id', 'users.id')->whereIn('branch_user.branch_id', $branches))
                ->orWhereExists(fn ($p) => $p->from('guardians')->join('guardian_patient', 'guardian_patient.guardian_id', '=', 'guardians.id')
                    ->join('patients', 'patients.id', '=', 'guardian_patient.patient_id')
                    ->whereColumn('guardians.user_id', 'users.id')->whereIn('patients.home_branch_id', $branches))))
            ->when($request->input('audience') === 'parents', fn ($n) => $n->where('users.user_type', UserType::Parent->value))
            ->when($request->input('audience') === 'staff', fn ($n) => $n->where('users.user_type', UserType::Staff->value))
            ->when($request->filled('kind'), fn ($n) => $n->where('notifications.data', 'like', '%"kind":"'.$request->string('kind').'"%'))
            ->when($request->input('read') === 'unread', fn ($n) => $n->whereNull('notifications.read_at'))
            ->when($request->filled('from'), fn ($n) => $n->where('notifications.created_at', '>=', $request->date('from')->startOfDay()))
            ->when($request->filled('to'), fn ($n) => $n->where('notifications.created_at', '<=', $request->date('to')->endOfDay()))
            ->when($q !== '', fn ($n) => $n->where(fn ($w) => $w->where('users.name', 'like', "%{$q}%")->orWhere('users.phone', 'like', "%{$q}%")))
            ->orderByDesc('notifications.created_at')
            ->select('notifications.id', 'notifications.data', 'notifications.read_at', 'notifications.created_at', 'users.id as user_id', 'users.name', 'users.user_type', 'users.email')
            ->paginate(30);

        return response()->json([
            'data' => collect($page->items())->map(function ($n) {
                $data = json_decode($n->data, true) ?: [];

                return [
                    'id' => $n->id, 'kind' => $data['kind'] ?? null, 'title' => $data['title'] ?? '', 'body' => $data['body'] ?? '',
                    'to' => ['id' => $n->user_id, 'name' => $n->name, 'type' => $n->user_type, 'has_email' => filled($n->email)],
                    'read_at' => $n->read_at, 'created_at' => $n->created_at,
                ];
            }),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
            'kinds' => array_keys(NotificationTemplates::TEMPLATES),
        ]);
    }

    public function templates(NotificationTemplates $templates): JsonResponse
    {
        Gate::authorize(Permission::NOTIFICATIONS_SEND);

        return response()->json(['data' => $templates->all()]);
    }

    public function saveTemplate(Request $request, string $key, NotificationTemplates $templates): JsonResponse
    {
        Gate::authorize(Permission::SETTINGS_MANAGE);
        abort_unless(isset(NotificationTemplates::TEMPLATES[$key]), 404);
        $data = $request->validate(['title' => ['nullable', 'string', 'max:120'], 'body' => ['nullable', 'string', 'max:500']]);

        // Only the placeholders of this message can be used — anything else would be sent as raw {text}.
        $allowed = NotificationTemplates::TEMPLATES[$key][4];
        foreach (['title', 'body'] as $field) {
            preg_match_all('/\{([a-z_]+)\}/', (string) ($data[$field] ?? ''), $found);
            if ($unknown = array_diff($found[1], $allowed)) {
                throw ValidationException::withMessages([$field => 'Unknown placeholder: {'.implode('}, {', $unknown).'}. Available: {'.implode('}, {', $allowed).'}.']);
            }
        }
        $templates->save($key, $data['title'] ?? null, $data['body'] ?? null);
        AuditLogger::log('notification.template_updated', null, null, ['template' => $key, ...$data]);
        $preview = $templates->render($key, collect($allowed)->mapWithKeys(fn ($p) => [$p => '{'.$p.'}'])->all());

        return response()->json(['data' => collect($templates->all())->firstWhere('key', $key), 'preview' => $preview]);
    }

    /** Users → Branch Access: which branches each staff member can work in. */
    public function branchAccess(Request $request): JsonResponse
    {
        Gate::authorize(Permission::USERS_VIEW);
        $mine = $request->user()->accessibleBranchIds();

        return response()->json([
            'branches' => Branch::when($mine !== null, fn ($q) => $q->whereIn('id', $mine))->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'code']),
            'users' => User::where('user_type', UserType::Staff->value)->with(['roles:id,name', 'branches:id'])
                ->when($mine !== null, fn ($q) => $q->whereHas('branches', fn ($b) => $b->whereIn('branches.id', $mine)))
                ->orderBy('name')->get(['id', 'name', 'email', 'status'])
                ->map(fn (User $u) => [
                    'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'status' => $u->status,
                    'roles' => $u->roles->pluck('name'), 'all_branches' => $u->hasRole(Role::SuperAdmin->value),
                    'branch_ids' => $u->branches->pluck('id'), 'primary_id' => $u->branches->firstWhere('pivot.is_primary', true)?->id,
                ]),
        ]);
    }

    public function saveBranchAccess(Request $request, User $user): JsonResponse
    {
        Gate::authorize('update', $user);
        $mine = $request->user()->accessibleBranchIds();
        $data = $request->validate([
            'branch_ids' => ['required', 'array', 'min:1'],
            'branch_ids.*' => ['integer', 'distinct', Rule::exists('branches', 'id')->whereNull('deleted_at')],
            'primary_id' => ['nullable', 'integer'],
        ], ['branch_ids.required' => 'Everyone needs at least one branch.', 'branch_ids.min' => 'Everyone needs at least one branch.']);

        // A branch admin may only grant or remove their own branches; access to other branches stays as it was.
        $keep = $mine === null ? [] : $user->branches()->whereNotIn('branches.id', $mine)->pluck('branches.id')->all();
        if ($mine !== null && array_diff($data['branch_ids'], $mine)) {
            throw ValidationException::withMessages(['branch_ids' => 'You can only give access to your own branches.']);
        }
        $ids = array_values(array_unique([...$data['branch_ids'], ...$keep]));
        $primary = in_array($data['primary_id'] ?? null, $ids, true) ? $data['primary_id'] : $ids[0];
        $before = $user->branches()->pluck('branches.id')->all();
        $user->branches()->sync(collect($ids)->mapWithKeys(fn ($id) => [$id => ['is_primary' => $id === $primary]]));
        AuditLogger::log('user.branch_access', $user, ['branch_ids' => $before], ['branch_ids' => $ids, 'primary' => $primary]);

        return response()->json(['data' => ['branch_ids' => $ids, 'primary_id' => $primary]]);
    }
}
