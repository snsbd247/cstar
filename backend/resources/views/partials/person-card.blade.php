{{-- Therapist or trainer card. $person, $role (subtitle), $href (nullable) --}}
@php
    $initials = collect(explode(' ', $person->name))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');
    $photo = $person->photoUrl();
@endphp
<{{ $href ? 'a' : 'div' }} @if ($href) href="{{ $href }}" @endif class="group overflow-hidden rounded-3xl border border-slate-100 bg-white shadow-sm transition {{ $href ? 'hover:-translate-y-1 hover:shadow-lg' : '' }}">
    <div class="relative aspect-[4/3] overflow-hidden bg-gradient-to-br from-brand-100 to-sky-brand-100">
        @if ($photo)
            <img src="{{ $photo }}" alt="{{ $person->name }}" class="size-full object-cover transition duration-500 group-hover:scale-105" loading="lazy">
        @else
            <span class="flex size-full items-center justify-center font-display text-5xl font-black text-brand-700/60">{{ $initials }}</span>
        @endif
    </div>
    <div class="p-5">
        <h3 class="font-display text-lg font-bold text-slate-900">{{ $person->name }}</h3>
        <p class="text-sm font-medium text-brand-700">{{ $role }}</p>
        @if ($person->qualification)<p class="mt-1 text-sm text-slate-500">{{ $person->qualification }}</p>@endif
        @if ($person->experience_years)<p class="mt-2 text-xs text-slate-500">{{ $person->experience_years }}+ years experience</p>@endif
    </div>
</{{ $href ? 'a' : 'div' }}>
