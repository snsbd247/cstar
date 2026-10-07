<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\OutboundMessage;
use App\Services\AuditLogger;
use App\Services\Messaging\GreenWebGateway;
use App\Services\Messaging\Messenger;
use App\Services\Messaging\MessagingSettings;
use App\Services\NotificationTemplates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Settings → SMS & WhatsApp and Notifications → Logs (SMS / WhatsApp) — Sprint 18. */
class MessagingController extends Controller
{
    public function __construct(private MessagingSettings $settings, private Messenger $messenger) {}

    public function settings(): JsonResponse
    {
        Gate::authorize(Permission::SETTINGS_MANAGE);

        return response()->json(['data' => [
            'values' => $this->settings->forScreen(),
            'kinds' => collect(NotificationTemplates::TEMPLATES)->map(fn ($t, $key) => ['key' => $key, 'label' => $t[0], 'audience' => $t[1]])
                ->push(['key' => 'announcement', 'label' => 'Announcements (Notifications → Announcements)', 'audience' => 'parents'])->values(),
            'month' => $this->monthSummary(),
        ]]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        Gate::authorize(Permission::SETTINGS_MANAGE);
        $kinds = collect(NotificationTemplates::TEMPLATES)->keys()->push('announcement')->all();
        $data = $request->validate([
            'sms_enabled' => ['required', 'in:0,1'],
            'sms_driver' => ['required', Rule::in(['log', 'greenweb'])],
            'greenweb_token' => ['nullable', 'string', 'max:200'],
            'whatsapp_enabled' => ['required', 'in:0,1'],
            'whatsapp_driver' => ['required', Rule::in(['log', 'whatsapp_cloud'])],
            'whatsapp_phone_number_id' => ['nullable', 'string', 'max:40', 'regex:/^\d*$/'],
            'whatsapp_token' => ['nullable', 'string', 'max:1000'],
            'whatsapp_template' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/'],
            'whatsapp_language' => ['required', 'string', 'max:10'],
            'prefix' => ['nullable', 'string', 'max:20'],
            'kinds' => ['present', 'array'],
            'kinds.*' => [Rule::in($kinds)],
            'staff_sms' => ['required', 'in:0,1'],
        ]);
        if ($data['sms_enabled'] === '1' && $data['sms_driver'] === 'greenweb' && blank($data['greenweb_token'] ?? null) && $this->settings->get('greenweb_token') === '') {
            return response()->json(['message' => 'Enter the GreenWeb token first.', 'errors' => ['greenweb_token' => ['Enter the GreenWeb token (from your bdbulksms.com account).']]], 422);
        }
        $this->settings->update($data);
        AuditLogger::log('messaging.settings_updated', null, null, collect($data)->except(['greenweb_token', 'whatsapp_token'])->all()
            + ['token_changed' => filled($data['greenweb_token'] ?? null) || filled($data['whatsapp_token'] ?? null)]);

        return $this->settings();
    }

    /** Sends one message right away so the set-up can be checked from the screen. */
    public function test(Request $request): JsonResponse
    {
        Gate::authorize(Permission::SETTINGS_MANAGE);
        $data = $request->validate(['channel' => ['required', Rule::in(['sms', 'whatsapp'])], 'to' => ['required', 'string'], 'text' => ['nullable', 'string', 'max:500']]);
        $to = Messenger::mobile($data['to']);
        if (! $to) {
            return response()->json(['message' => 'Mobile number must be 11 digits starting 01.', 'errors' => ['to' => ['Mobile number must be 11 digits starting 01.']]], 422);
        }
        $message = $this->messenger->send($data['channel'], $to, ($data['text'] ?? null) ?: $this->messenger->compose('পরীক্ষামূলক বার্তা', 'C-STAR থেকে SMS ঠিকভাবে কাজ করছে।'), 'test', $request->user());

        return response()->json(['data' => $this->row($message)]);
    }

    public function balance(): JsonResponse
    {
        Gate::authorize(Permission::SETTINGS_MANAGE);
        if ($this->settings->get('sms_driver') !== 'greenweb' || $this->settings->get('greenweb_token') === '') {
            return response()->json(['data' => ['balance' => null, 'note' => 'Balance is shown for GreenWeb once its token is saved.']]);
        }

        return response()->json(['data' => ['balance' => (new GreenWebGateway($this->settings->get('greenweb_token')))->balance(), 'note' => null]]);
    }

    public function log(Request $request): JsonResponse
    {
        Gate::authorize(Permission::NOTIFICATIONS_SEND);
        $q = trim((string) $request->input('q'));
        $page = OutboundMessage::with('user:id,name')
            ->when($request->filled('channel'), fn ($m) => $m->where('channel', $request->string('channel')))
            ->when($request->filled('status'), fn ($m) => $m->where('status', $request->string('status')))
            ->when($request->filled('from'), fn ($m) => $m->where('created_at', '>=', $request->date('from')->startOfDay()))
            ->when($request->filled('to'), fn ($m) => $m->where('created_at', '<=', $request->date('to')->endOfDay()))
            ->when($q !== '', fn ($m) => $m->where(fn ($w) => $w->where('to', 'like', "%{$q}%")->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$q}%"))))
            ->latest('id')->paginate(30);

        return response()->json([
            'data' => collect($page->items())->map(fn ($m) => $this->row($m)),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
            'month' => $this->monthSummary(),
        ]);
    }

    public function resend(OutboundMessage $message): JsonResponse
    {
        Gate::authorize(Permission::NOTIFICATIONS_SEND);
        abort_unless($message->status === 'failed', 422, 'Only a failed message can be sent again.');

        return response()->json(['data' => $this->row($this->messenger->deliver($message))]);
    }

    private function monthSummary(): array
    {
        $month = OutboundMessage::where('created_at', '>=', now()->startOfMonth());

        return [
            'sent' => (clone $month)->where('status', 'sent')->count(),
            'segments' => (int) (clone $month)->where('status', 'sent')->where('channel', 'sms')->sum('segments'),
            'failed' => (clone $month)->where('status', 'failed')->count(),
        ];
    }

    private function row(OutboundMessage $m): array
    {
        return [
            'id' => $m->id, 'channel' => $m->channel, 'driver' => $m->driver, 'to' => $m->to, 'body' => $m->body, 'segments' => $m->segments,
            'kind' => $m->kind, 'user' => $m->user?->name, 'status' => $m->status, 'error' => $m->error,
            'created_at' => $m->created_at->toIso8601String(), 'sent_at' => $m->sent_at?->toIso8601String(),
        ];
    }
}
