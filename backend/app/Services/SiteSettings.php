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
    ];

    private const CACHE_KEY = 'settings.website';

    /** @return array<string, string> */
    public function all(): array
    {
        $stored = Cache::rememberForever(self::CACHE_KEY, fn () => Setting::where('group', 'website')->pluck('value', 'key')->all());

        return array_merge(self::DEFAULTS, array_intersect_key(array_filter($stored, fn ($v) => $v !== null), self::DEFAULTS));
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
