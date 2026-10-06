{{-- Trust strip: only real numbers (CMS) or counts taken from the system --}}
@php
    $stats = array_values(array_filter([
        $site['stat_children'] ? [$site['stat_children'], 'Children supported'] : null,
        $site['stat_years'] ? [$site['stat_years'], 'Years of experience'] : null,
        $therapistCount ? [$therapistCount, 'Specialist therapists'] : null,
        $publicBranches->count() ? [$publicBranches->count(), $publicBranches->count() === 1 ? 'Center' : 'Branches'] : null,
    ]));
@endphp
@if (count($stats))
    <section class="border-y border-slate-100 bg-white">
        <div class="container-site grid grid-cols-2 gap-6 py-8 text-center {{ [1 => "sm:grid-cols-1", 2 => "sm:grid-cols-2", 3 => "sm:grid-cols-3", 4 => "sm:grid-cols-4"][count($stats)] }}">
            @foreach ($stats as [$value, $label])
                <div><p class="font-display text-3xl font-black text-brand-700">{{ $value }}</p><p class="text-sm text-slate-500">{{ $label }}</p></div>
            @endforeach
        </div>
    </section>
@endif
