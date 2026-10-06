<?php

namespace App\Support;

use App\Models\Service;

/** Picks a website icon and colour for a service from its slug. */
class ServiceIcon
{
    private const MAP = [
        'speech' => ['mic', 'bg-sky-brand-100 text-sky-brand-700'],
        'occupational' => ['hand', 'bg-brand-100 text-brand-700'],
        'aba' => ['puzzle', 'bg-sun-100 text-amber-700'],
        'oral' => ['smile', 'bg-coral-100 text-rose-700'],
        'special-education' => ['book', 'bg-violet-100 text-violet-700'],
        'parent' => ['users', 'bg-brand-100 text-brand-700'],
        'assessment' => ['clipboard', 'bg-sky-brand-100 text-sky-brand-700'],
        'functional' => ['activity', 'bg-brand-100 text-brand-700'],
        'daily-living' => ['utensils', 'bg-sun-100 text-amber-700'],
        'motor' => ['activity', 'bg-coral-100 text-rose-700'],
        'communication' => ['message', 'bg-sky-brand-100 text-sky-brand-700'],
        'social' => ['users', 'bg-violet-100 text-violet-700'],
        'learning' => ['brain', 'bg-brand-100 text-brand-700'],
    ];

    /** @return array{0: string, 1: string} [icon, tailwind colour classes] */
    public static function for(Service $service): array
    {
        foreach (self::MAP as $needle => $style) {
            if (str_contains($service->slug, $needle)) {
                return $style;
            }
        }

        return ['sparkles', 'bg-brand-100 text-brand-700'];
    }
}
