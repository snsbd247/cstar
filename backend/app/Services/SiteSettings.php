<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Website settings editable from the CMS (group "website").
 * Contact details and statistics default to empty: the site hides anything not filled in,
 * so nothing unverified is ever shown to the public.
 */
class SiteSettings
{
    public const DEFAULTS = [
        'tagline' => 'Every child grows at their own pace.',
        'hero_title' => 'Speech therapy, autism support and child development — in one caring place',
        'hero_subtitle' => 'Individual therapy and regular functional training for children with autism, speech delay and other developmental needs, with families involved at every step.',
        'about_title' => 'About C-STAR',
        'about_body' => "Center for Speech Therapy & Autism Rehabilitation (C-STAR) helps children communicate, learn and live more independently.\n\nEvery child starts with an assessment. Our therapists and trainers then build an individual plan that may combine one-to-one therapy sessions with regular group training at the center, and families receive home practice so progress continues every day.",
        'mission' => '',
        'vision' => '',
        'phone' => '',
        'whatsapp' => '',
        'email' => '',
        'address' => '',
        'opening_hours' => 'Saturday – Thursday, 9:00 AM – 6:00 PM',
        'facebook_url' => '',
        'youtube_url' => '',
        'map_embed_url' => '',
        'stat_children' => '',
        'stat_years' => '',
        // Sprint 16 — Website / CMS → SEO, Pages and Page Sections.
        'seo_default_description' => '',
        'seo_noindex' => '0',              // 1 = ask search engines not to list the site (testing period)
        'google_site_verification' => '',
        'google_analytics_id' => '',
        'nav_hidden' => '[]',              // JSON list of page keys left out of the top menu
        'page_seo' => '{}',                // JSON {page: {title, description}}
        'home_sections' => '',             // JSON [{key, visible}] in display order; empty = default
    ];

    /** Public pages that can be renamed for search engines; the first eight are in the top menu. */
    public const PAGES = [
        'home' => 'Home', 'about' => 'About', 'services' => 'Services', 'therapists' => 'Therapists', 'trainers' => 'Training',
        'branches' => 'Branches', 'faq' => 'FAQ', 'contact' => 'Contact', 'gallery' => 'Gallery', 'notices' => 'Notices', 'appointment' => 'Book an appointment',
    ];

    public const MENU_PAGES = ['home', 'about', 'services', 'therapists', 'trainers', 'branches', 'faq', 'contact'];

    public const HOME_SECTIONS = [
        'hero' => 'Hero banner', 'stats' => 'Numbers strip', 'therapy' => 'Therapy services', 'training' => 'Regular training program',
        'about' => 'About & why choose us', 'steps' => 'How it works', 'therapists' => 'Therapists', 'testimonials' => 'Parent testimonials',
        'gallery' => 'Gallery', 'visit' => 'Branches & FAQ', 'notices' => 'Notice board',
    ];

    private const CACHE_KEY = 'settings.website';

    /** @return array<string, string> */
    public function all(): array
    {
        $stored = Cache::rememberForever(self::CACHE_KEY, fn () => Setting::where('group', 'website')->pluck('value', 'key')->all());

        return array_merge(self::DEFAULTS, array_intersect_key(array_filter($stored, fn ($v) => $v !== null), self::DEFAULTS));
    }

    /** @return list<array{key: string, label: string, visible: bool}> sections in display order (new sections appear at the end). */
    public function homeSectionList(): array
    {
        $saved = collect(json_decode($this->get('home_sections'), true) ?: [])->filter(fn ($s) => isset(self::HOME_SECTIONS[$s['key'] ?? '']))->keyBy('key');
        $order = $saved->keys()->merge(array_keys(self::HOME_SECTIONS))->unique();

        return $order->map(fn ($key) => ['key' => $key, 'label' => self::HOME_SECTIONS[$key], 'visible' => (bool) ($saved[$key]['visible'] ?? true)])->values()->all();
    }

    /** @return list<string> keys of the home sections to show, in order */
    public function homeSections(): array
    {
        return collect($this->homeSectionList())->where('visible', true)->pluck('key')->all();
    }

    /** @return array<string, array{title: string, description: string}> */
    public function pageSeo(): array
    {
        return json_decode($this->get('page_seo'), true) ?: [];
    }

    /** @return list<string> */
    public function navHidden(): array
    {
        return json_decode($this->get('nav_hidden'), true) ?: [];
    }

    public function get(string $key): string
    {
        return (string) ($this->all()[$key] ?? '');
    }

    /** @param  array<string, string|null>  $values */
    public function update(array $values): void
    {
        foreach (array_intersect_key($values, self::DEFAULTS) as $key => $value) {
            Setting::updateOrCreate(['group' => 'website', 'key' => $key], ['value' => $value ?? '']);
        }

        Cache::forget(self::CACHE_KEY);
    }
}
