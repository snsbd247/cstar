<?php

namespace App\Http\Controllers\Website;

use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\AppointmentRequest;
use App\Models\ContactMessage;
use App\Models\Service;
use App\Models\Therapist;
use App\Models\User;
use App\Notifications\WebsiteEnquiryReceived;
use App\Services\IdGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Website forms. Both are rate limited (route) and carry a honeypot field ("website")
 * that real visitors never see or fill.
 */
class EnquiryController extends Controller
{
    private const PHONE = 'regex:/^01[3-9]\d{8}$/';

    public function appointmentForm(Request $request): View
    {
        return view('website.appointment', [
            'services' => Service::onWebsite()->where('is_bookable_online', true)->get(),
            'therapists' => Therapist::with('services:id')->where('status', 'active')->where('show_on_website', true)->orderBy('name')->get(),
            'times' => AppointmentRequest::TIMES,
            'selectedService' => $request->query('service'),
            'selectedTherapist' => $request->query('therapist'),
        ]);
    }

    /** Plan §২৯: visitor request → status "new" → staff notified → reception calls back. */
    public function storeAppointment(Request $request, IdGenerator $ids): RedirectResponse
    {
        $data = $request->validate([
            'website' => ['prohibited'], // honeypot
            'parent_name' => ['required', 'string', 'max:255'],
            'child_name' => ['required', 'string', 'max:255'],
            'child_age_years' => ['nullable', 'integer', 'between:0,25'],
            'phone' => ['required', 'string', self::PHONE],
            'email' => ['nullable', 'email', 'max:255'],
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'service_id' => ['nullable', 'integer', Rule::exists('services', 'id')->where('is_active', true)->where('is_bookable_online', true)],
            'preferred_therapist_id' => ['nullable', 'integer', Rule::exists('therapists', 'id')->where('status', 'active')->where('show_on_website', true)],
            'preferred_date' => ['nullable', 'date', 'after_or_equal:today', 'before:+90 days'],
            'preferred_time' => ['nullable', Rule::in(array_keys(AppointmentRequest::TIMES))],
            'message' => ['nullable', 'string', 'max:2000'],
        ], [
            'phone.regex' => 'Please enter a valid Bangladeshi mobile number (01XXXXXXXXX).',
            'website.prohibited' => 'Your request could not be sent.',
        ]);

        unset($data['website']); // honeypot: validated as empty, never stored

        $appointmentRequest = AppointmentRequest::create([
            ...$data,
            'reference' => $ids->next('appointment_request', 'REQ'),
            'status' => 'new',
            'source' => 'website',
            'ip_address' => $request->ip(),
        ]);

        $this->notifyFrontDesk($appointmentRequest->branch_id, new WebsiteEnquiryReceived(
            'appointment_request',
            "New appointment request {$appointmentRequest->reference}",
            "{$appointmentRequest->parent_name} for {$appointmentRequest->child_name} · {$appointmentRequest->phone}",
            '/app/online-requests',
        ));

        return redirect()->route('appointment.thanks')->with('reference', $appointmentRequest->reference);
    }

    public function appointmentThanks(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('reference')) {
            return redirect()->route('appointment');
        }

        return view('website.appointment-thanks', ['reference' => $request->session()->get('reference')]);
    }

    public function contactForm(): View
    {
        return view('website.contact');
    }

    public function storeContact(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'website' => ['prohibited'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'required_without:email', 'string', self::PHONE],
            'email' => ['nullable', 'required_without:phone', 'email', 'max:255'],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('is_active', true)],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:3000'],
        ], ['phone.regex' => 'Please enter a valid Bangladeshi mobile number (01XXXXXXXXX).']);

        unset($data['website']);
        $message = ContactMessage::create([...$data, 'status' => 'new', 'ip_address' => $request->ip()]);

        $this->notifyFrontDesk($message->branch_id, new WebsiteEnquiryReceived(
            'contact_message', "New message from {$message->name}", (string) str($message->message)->limit(120), '/app/cms?tab=messages',
        ));

        return redirect()->route('contact')->with('sent', true);
    }

    /** Front-desk staff of the branch (or of any branch for general messages) plus Super Admins. */
    private function notifyFrontDesk(?int $branchId, WebsiteEnquiryReceived $notification): void
    {
        $recipients = User::permission(Permission::APPOINTMENT_REQUESTS_MANAGE)
            ->where('status', 'active')
            ->where(fn ($q) => $q
                ->when($branchId, fn ($q) => $q->whereHas('branches', fn ($b) => $b->where('branches.id', $branchId)), fn ($q) => $q->whereRaw('1 = 1'))
                ->orWhereHas('roles', fn ($r) => $r->where('name', Role::SuperAdmin->value)))
            ->get();

        Notification::send($recipients, $notification);
    }
}
