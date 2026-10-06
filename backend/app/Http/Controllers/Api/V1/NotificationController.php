<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EnrollmentStatus;
use App\Enums\Permission;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** The signed-in user's in-app notifications (bell), announcements and notification settings. */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => $user->notifications()->latest()->limit(30)->get()->map(fn ($n) => [
                'id' => $n->id, ...$n->data, 'read_at' => $n->read_at, 'created_at' => $n->created_at,
            ]),
            'unread' => $user->unreadNotifications()->count(),
        ]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $request->user()->notifications()->whereKey($id)->firstOrFail()->markAsRead();

        return response()->json(['message' => 'Marked as read.']);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['message' => 'All marked as read.']);
    }

    // ---- Announcements ----------------------------------------------------------------------------

    public function announcements(Request $request): JsonResponse
    {
        Gate::authorize(Permission::NOTIFICATIONS_SEND);

        return response()->json(['data' => Announcement::with('sender')->latest()->limit(50)->get()->map(fn (Announcement $a) => [
            ...$a->only(['id', 'title', 'body', 'audience', 'filters', 'recipients_count', 'created_at']),
            'sent_by' => $a->sender?->name,
        ])]);
    }

    /** POST /announcements — preview=1 returns the recipient count without sending. */
    public function announce(Request $request, NotificationService $notify): JsonResponse
    {
        Gate::authorize(Permission::NOTIFICATIONS_SEND);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:2000'],
            'audience' => ['required', Rule::in(['parents', 'staff', 'everyone'])],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'training_group_id' => ['nullable', 'integer', 'exists:training_groups,id'],
            'preview' => ['boolean'],
        ]);
        $branches = $request->user()->accessibleBranchIds();
        if ($data['branch_id'] ?? null) {
            abort_unless($request->user()->canAccessBranch((int) $data['branch_id']), 403);
        }

        $recipients = $this->recipients($data, $branches);
        if (! empty($data['preview'])) {
            return response()->json(['data' => ['recipients' => $recipients->count()]]);
        }

        $count = $notify->send($recipients, 'announcement', $data['title'], $data['body'], '/');
        $announcement = Announcement::create([
            'title' => $data['title'], 'body' => $data['body'], 'audience' => $data['audience'],
            'filters' => array_filter(['branch_id' => $data['branch_id'] ?? null, 'service_id' => $data['service_id'] ?? null, 'training_group_id' => $data['training_group_id'] ?? null]),
            'recipients_count' => $count, 'sent_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $announcement], 201);
    }

    public function settings(NotificationService $notify): JsonResponse
    {
        Gate::authorize(Permission::NOTIFICATIONS_SEND);

        return response()->json(['data' => $notify->settings()]);
    }

    public function updateSettings(Request $request, NotificationService $notify): JsonResponse
    {
        Gate::authorize(Permission::SETTINGS_MANAGE);
        $notify->updateSettings($request->validate([
            'email_enabled' => ['sometimes', 'boolean'],
            'parent_reminders' => ['sometimes', 'boolean'],
            'staff_reminders' => ['sometimes', 'boolean'],
        ]));

        return response()->json(['data' => $notify->settings()]);
    }

    /** Parents of children in the chosen branch / therapy / class, and/or staff of the branch. */
    private function recipients(array $data, ?array $branches)
    {
        $branchIds = ($data['branch_id'] ?? null) ? [(int) $data['branch_id']] : $branches;
        $users = collect();

        if (in_array($data['audience'], ['parents', 'everyone'], true)) {
            $users = $users->concat(User::where('status', UserStatus::Active)->whereHas('guardian.patients', function (Builder $p) use ($data, $branchIds) {
                $p->where('guardian_patient.can_access_portal', true)
                    ->when($branchIds !== null, fn ($q) => $q->whereIn('home_branch_id', $branchIds))
                    ->when($data['service_id'] ?? null, fn ($q) => $q->whereHas('enrollments', fn ($e) => $e->whereIn('status', EnrollmentStatus::current())
                        ->whereHas('therapyEnrollment', fn ($t) => $t->where('service_id', $data['service_id']))))
                    ->when($data['training_group_id'] ?? null, fn ($q) => $q->whereHas('enrollments', fn ($e) => $e->whereIn('status', EnrollmentStatus::current())
                        ->whereHas('trainingEnrollment', fn ($t) => $t->where('training_group_id', $data['training_group_id']))));
            })->get());
        }
        if (in_array($data['audience'], ['staff', 'everyone'], true)) {
            $users = $users->concat(User::where('status', UserStatus::Active)->where('user_type', 'staff')
                ->when($branchIds !== null, fn ($q) => $q->whereHas('branches', fn ($b) => $b->whereIn('branches.id', $branchIds)))->get());
        }

        return $users->unique('id')->values();
    }
}
