<?php

namespace App\Http\Controllers\Api\V1\Cms;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\AppointmentRequest;
use App\Models\Branch;
use App\Models\ContactMessage;
use App\Models\Faq;
use App\Models\GalleryItem;
use App\Models\Notice;
use App\Models\Service;
use App\Models\Testimonial;
use App\Models\Therapist;
use App\Models\Trainer;
use App\Services\AuditLogger;
use App\Services\SiteSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Website / CMS (Sprint 16): dashboard, pages (menu + search-engine title/description), home page
 * sections (order and visibility), SEO settings and which branches the public site lists.
 */
class WebsitePagesController extends Controller
{
    public function dashboard(): JsonResponse
    {
        Gate::authorize(Permission::CMS_MANAGE);
        $count = fn ($model, $published = 'is_published') => ['shown' => $model::where($published, true)->count(), 'total' => $model::count()];

        return response()->json(['data' => [
            'content' => [
                'services' => $count(Service::class, 'show_on_website'),
                'therapists' => $count(Therapist::class, 'show_on_website'),
                'trainers' => $count(Trainer::class, 'show_on_website'),
                'testimonials' => $count(Testimonial::class),
                'gallery' => $count(GalleryItem::class),
                'faqs' => $count(Faq::class),
                'notices' => ['shown' => Notice::onWebsite()->count(), 'total' => Notice::count()],
                'branches' => $count(Branch::class, 'show_on_website'),
            ],
            'inbox' => [
                'new_requests' => AppointmentRequest::where('status', 'new')->count(),
                'requests_week' => AppointmentRequest::where('created_at', '>=', now()->subDays(7))->count(),
                'new_messages' => ContactMessage::where('status', 'new')->count(),
            ],
            'latest_requests' => AppointmentRequest::latest()->limit(5)->get()->map(fn ($r) => [
                'id' => $r->id, 'reference' => $r->reference, 'name' => $r->child_name ?? $r->parent_name ?? null, 'status' => $r->status, 'at' => $r->created_at->toIso8601String(),
            ]),
            'seo' => ['noindex' => app(SiteSettings::class)->get('seo_noindex') === '1'],
        ]]);
    }

    public function structure(SiteSettings $settings): JsonResponse
    {
        Gate::authorize(Permission::CMS_MANAGE);

        return response()->json(['data' => $this->payload($settings)]);
    }

    /** Pages: in the top menu or not, and an optional search-engine title and description per page. */
    public function savePages(Request $request, SiteSettings $settings): JsonResponse
    {
        Gate::authorize(Permission::CMS_MANAGE);
        $data = $request->validate([
            'pages' => ['required', 'array'],
            'pages.*.key' => ['required', Rule::in(array_keys(SiteSettings::PAGES))],
            'pages.*.in_menu' => ['boolean'],
            'pages.*.title' => ['nullable', 'string', 'max:70'],
            'pages.*.description' => ['nullable', 'string', 'max:160'],
        ], ['pages.*.title.max' => 'Keep titles under 70 characters — Google cuts longer ones.', 'pages.*.description.max' => 'Keep descriptions under 160 characters.']);

        $pages = collect($data['pages']);
        $settings->update([
            'nav_hidden' => json_encode($pages->filter(fn ($p) => in_array($p['key'], SiteSettings::MENU_PAGES, true) && $p['key'] !== 'home' && ! ($p['in_menu'] ?? true))->pluck('key')->values()),
            'page_seo' => json_encode($pages->mapWithKeys(fn ($p) => [$p['key'] => array_filter(['title' => trim((string) ($p['title'] ?? '')), 'description' => trim((string) ($p['description'] ?? ''))])])->filter()->all() ?: new \stdClass),
        ]);
        AuditLogger::log('website.pages_updated');

        return response()->json(['data' => $this->payload($settings)]);
    }

    /** Page Sections: order and visibility of the home page sections. */
    public function saveSections(Request $request, SiteSettings $settings): JsonResponse
    {
        Gate::authorize(Permission::CMS_MANAGE);
        $data = $request->validate([
            'sections' => ['required', 'array', 'size:'.count(SiteSettings::HOME_SECTIONS)],
            'sections.*.key' => ['required', 'distinct', Rule::in(array_keys(SiteSettings::HOME_SECTIONS))],
            'sections.*.visible' => ['required', 'boolean'],
        ]);
        $settings->update(['home_sections' => json_encode(collect($data['sections'])->map(fn ($s) => ['key' => $s['key'], 'visible' => (bool) $s['visible']])->values())]);
        AuditLogger::log('website.sections_updated');

        return response()->json(['data' => $this->payload($settings)]);
    }

    public function saveSeo(Request $request, SiteSettings $settings): JsonResponse
    {
        Gate::authorize(Permission::CMS_MANAGE);
        $data = $request->validate([
            'seo_default_description' => ['nullable', 'string', 'max:160'],
            'seo_noindex' => ['required', 'in:0,1'],
            'google_site_verification' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'google_analytics_id' => ['nullable', 'string', 'regex:/^G-[A-Z0-9]{4,12}$/'],
        ], [
            'google_site_verification.regex' => 'Paste only the code from the content="…" part of the Search Console tag.',
            'google_analytics_id.regex' => 'A Google Analytics 4 ID looks like G-ABC123XYZ.',
        ]);
        $settings->update($data);
        AuditLogger::log('website.seo_updated', null, null, $data);

        return response()->json(['data' => $this->payload($settings)]);
    }

    /** Website → Branches: list a branch on the public site or not, and in which order. */
    public function saveBranch(Request $request, Branch $branch): JsonResponse
    {
        Gate::authorize(Permission::CMS_MANAGE);
        $branch->update($request->validate(['show_on_website' => ['required', 'boolean'], 'sort_order' => ['nullable', 'integer', 'between:0,999']]));

        return response()->json(['data' => $branch->only(['id', 'name', 'show_on_website', 'sort_order'])]);
    }

    private function payload(SiteSettings $settings): array
    {
        $seo = $settings->pageSeo();
        $hidden = $settings->navHidden();

        return [
            'pages' => collect(SiteSettings::PAGES)->map(fn ($label, $key) => [
                'key' => $key, 'label' => $label, 'path' => $key === 'home' ? '/' : '/'.$key,
                'in_menu_option' => in_array($key, SiteSettings::MENU_PAGES, true) && $key !== 'home',
                'in_menu' => in_array($key, SiteSettings::MENU_PAGES, true) && ! in_array($key, $hidden, true),
                'title' => $seo[$key]['title'] ?? '', 'description' => $seo[$key]['description'] ?? '',
            ])->values(),
            'sections' => $settings->homeSectionList(),
            'seo' => collect($settings->all())->only(['seo_default_description', 'seo_noindex', 'google_site_verification', 'google_analytics_id']),
            'branches' => Branch::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'name_bn', 'address', 'phone', 'show_on_website', 'sort_order', 'is_active']),
            'site_url' => url('/'),
        ];
    }
}
