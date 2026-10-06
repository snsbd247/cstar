@extends('layouts.website')
@section('title', $therapist->name)
@section('description', ($therapist->designation ?: $therapist->therapist_type->label()).' at C-STAR. '.$therapist->bio)

@section('content')
<section class="bg-gradient-to-br from-brand-50 via-white to-sky-brand-50">
    <div class="container-site grid gap-10 py-14 sm:py-20 md:grid-cols-[280px_1fr] md:items-center">
        <div class="aspect-square overflow-hidden rounded-[2.5rem] bg-gradient-to-br from-brand-100 to-sky-brand-100 shadow-xl">
            @if ($therapist->photoUrl())
                <img src="{{ $therapist->photoUrl() }}" alt="{{ $therapist->name }}" class="size-full object-cover">
            @else
                <span class="flex size-full items-center justify-center font-display text-7xl font-black text-brand-700/60">{{ collect(explode(' ', $therapist->name))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('') }}</span>
            @endif
        </div>
        <div>
            <a href="{{ route('therapists') }}" class="text-sm text-slate-500 hover:text-slate-800">&larr; All therapists</a>
            <h1 class="heading mt-3 sm:text-5xl">{{ $therapist->name }}</h1>
            <p class="mt-2 text-lg font-medium text-brand-700">{{ $therapist->designation ?: $therapist->therapist_type->label() }}</p>
            <div class="mt-4 flex flex-wrap gap-2 text-sm">
                @if ($therapist->qualification)<span class="rounded-full bg-white px-3 py-1 shadow-sm">{{ $therapist->qualification }}</span>@endif
                @if ($therapist->experience_years)<span class="rounded-full bg-white px-3 py-1 shadow-sm">{{ $therapist->experience_years }}+ years experience</span>@endif
            </div>
            <a href="{{ route('appointment', ['therapist' => $therapist->id]) }}" class="btn btn-primary mt-7"><x-icon name="calendar" class="size-4" /> Request an appointment</a>
        </div>
    </div>
</section>

<section class="section">
    <div class="container-site grid gap-10 lg:grid-cols-[1.6fr_1fr]">
        <div class="prose-site text-lg text-slate-600">
            @forelse (preg_split('/\n\s*\n/', (string) $therapist->bio, -1, PREG_SPLIT_NO_EMPTY) as $paragraph)
                <p>{{ $paragraph }}</p>
            @empty
                <p>{{ $therapist->name }} is part of the C-STAR therapy team.</p>
            @endforelse
        </div>
        @if ($therapist->services->isNotEmpty())
            <aside class="rounded-3xl bg-slate-50 p-6">
                <p class="font-display text-lg font-bold text-slate-900">Services</p>
                <ul class="mt-3 space-y-2">
                    @foreach ($therapist->services as $s)
                        <li><a href="{{ route('service', $s->slug) }}" class="flex items-center gap-2 text-slate-700 hover:text-brand-700"><x-icon name="check" class="size-4 text-brand-600" /> {{ $s->name }}</a></li>
                    @endforeach
                </ul>
            </aside>
        @endif
    </div>
</section>
@endsection
