<?php

namespace App\Http\Controllers\Website;

use App\Enums\ServiceCategory;
use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\GalleryItem;
use App\Models\Notice;
use App\Models\Service;
use App\Models\Testimonial;
use App\Models\Therapist;
use App\Models\Trainer;
use App\Services\SiteSettings;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** Public website pages (decision D1: server-rendered Blade for SEO). */
class WebsiteController extends Controller
{
    public function home(): View
    {
        return view('website.home', [
            'therapyServices' => Service::onWebsite()->where('category', '!=', ServiceCategory::Training)->get(),
            'trainingPrograms' => Service::onWebsite()->where('category', ServiceCategory::Training)->get(),
            'therapists' => $this->publicTherapists()->limit(4)->get(),
            'testimonials' => Testimonial::published()->limit(3)->get(),
            'gallery' => GalleryItem::published()->limit(6)->get(),
            'faqs' => Faq::published()->limit(5)->get(),
            'notices' => Notice::onWebsite()->limit(3)->get(),
            'therapistCount' => $this->publicTherapists()->count(),
            'homeSections' => app(SiteSettings::class)->homeSections(),
        ]);
    }

    public function about(): View
    {
        return view('website.about');
    }

    public function services(): View
    {
        return view('website.services', [
            'therapyServices' => Service::onWebsite()->where('category', '!=', ServiceCategory::Training)->get(),
            'trainingPrograms' => Service::onWebsite()->where('category', ServiceCategory::Training)->get(),
        ]);
    }

    public function service(string $slug): View
    {
        $service = Service::onWebsite()->where('slug', $slug)->firstOrFail();

        return view('website.service', [
            'service' => $service,
            'therapists' => $this->publicTherapists()->whereHas('services', fn ($q) => $q->whereKey($service->id))->get(),
            'related' => Service::onWebsite()->where('category', $service->category)->whereKeyNot($service->id)->limit(3)->get(),
        ]);
    }

    public function therapists(): View
    {
        return view('website.therapists', ['therapists' => $this->publicTherapists()->with('services')->get()]);
    }

    public function therapist(string $slug): View
    {
        $therapist = $this->publicTherapists()->with(['services' => fn ($q) => $q->onWebsite()])->where('slug', $slug)->firstOrFail();

        return view('website.therapist', ['therapist' => $therapist]);
    }

    public function trainers(): View
    {
        return view('website.trainers', [
            'trainers' => Trainer::where('status', 'active')->where('show_on_website', true)->orderBy('sort_order')->orderBy('name')->get(),
            'trainingPrograms' => Service::onWebsite()->where('category', ServiceCategory::Training)->get(),
        ]);
    }

    public function branches(): View
    {
        return view('website.branches');
    }

    public function gallery(): View
    {
        return view('website.gallery', ['items' => GalleryItem::published()->get()]);
    }

    public function faq(): View
    {
        return view('website.faq', ['faqs' => Faq::published()->get()->groupBy(fn (Faq $f) => $f->category ?: 'General')]);
    }

    public function notices(): View
    {
        return view('website.notices', ['notices' => Notice::onWebsite()->paginate(10)]);
    }

    public function notice(string $slug): View
    {
        return view('website.notice', ['notice' => Notice::onWebsite()->where('slug', $slug)->firstOrFail()]);
    }

    public function sitemap(): Response
    {
        $urls = collect([
            route('home'), route('about'), route('services'), route('therapists'), route('trainers'),
            route('branches'), route('gallery'), route('faq'), route('contact'), route('appointment'), route('notices'),
        ])
            ->merge(Service::onWebsite()->pluck('slug')->map(fn ($s) => route('service', $s)))
            ->merge($this->publicTherapists()->pluck('slug')->map(fn ($s) => route('therapist', $s)))
            ->merge(Notice::onWebsite()->pluck('slug')->map(fn ($s) => route('notice', $s)));

        return response()->view('website.sitemap', ['urls' => $urls], 200, ['Content-Type' => 'application/xml']);
    }

    /** Keeps search engines out of the staff/parent apps and points them to the sitemap (absolute URL). */
    public function robots(): Response
    {
        if (app(SiteSettings::class)->get('seo_noindex') === '1') {
            return response("User-agent: *\nDisallow: /\n", 200, ['Content-Type' => 'text/plain']);
        }
        $body = "User-agent: *\n"
            .collect(['/app', '/trainer', '/therapist', '/portal', '/login', '/api/', '/appointment/thank-you'])->map(fn ($p) => "Disallow: $p")->implode("\n")
            ."\n\nSitemap: ".route('sitemap')."\n";

        return response($body, 200, ['Content-Type' => 'text/plain']);
    }

    private function publicTherapists()
    {
        return Therapist::where('status', 'active')->where('show_on_website', true)->orderBy('sort_order')->orderBy('name');
    }
}
