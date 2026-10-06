@extends('layouts.website')
@section('title', 'Branches')
@section('description', 'C-STAR centers, addresses, phone numbers and opening hours.')

@section('content')
@include('partials.page-hero', ['eyebrow' => 'Visit us', 'title' => $publicBranches->count() === 1 ? 'Our center' : 'Our branches', 'subtitle' => $site['opening_hours']])

<section class="section">
    <div class="container-site grid gap-6 md:grid-cols-2">
        @forelse ($publicBranches as $branch)
            <article class="overflow-hidden rounded-3xl border border-slate-100 bg-white shadow-sm">
                @if ($branch->map_url)
                    <a href="{{ $branch->map_url }}" target="_blank" rel="noopener" class="flex h-40 items-center justify-center gap-2 bg-gradient-to-br from-brand-100 to-sky-brand-100 font-semibold text-brand-800 hover:opacity-90">
                        <x-icon name="map-pin" class="size-6" /> Open in Google Maps
                    </a>
                @endif
                <div class="p-6">
                    <h2 class="font-display text-xl font-bold text-slate-900">{{ $branch->name }}</h2>
                    @if ($branch->name_bn)<p class="font-bn text-slate-500">{{ $branch->name_bn }}</p>@endif
                    <ul class="mt-4 space-y-2 text-slate-600">
                        @if ($branch->address)<li class="flex gap-2"><x-icon name="map-pin" class="mt-0.5 size-5 shrink-0 text-brand-600" /> {{ $branch->address }}</li>@endif
                        @if ($branch->phone)<li><a href="tel:{{ $branch->phone }}" class="flex gap-2 hover:text-brand-700"><x-icon name="phone" class="size-5 text-brand-600" /> {{ $branch->phone }}</a></li>@endif
                        @if ($branch->email)<li><a href="mailto:{{ $branch->email }}" class="flex gap-2 hover:text-brand-700"><x-icon name="mail" class="size-5 text-brand-600" /> {{ $branch->email }}</a></li>@endif
                    </ul>
                    @if ($branch->opening_hours)
                        <dl class="mt-5 grid grid-cols-2 gap-x-4 gap-y-1 rounded-2xl bg-slate-50 p-4 text-sm">
                            @foreach ($branch->opening_hours as $day => $hours)
                                <dt class="capitalize text-slate-500">{{ $day }}</dt><dd class="text-slate-800">{{ $hours ?: 'Closed' }}</dd>
                            @endforeach
                        </dl>
                    @endif
                </div>
            </article>
        @empty
            <p class="text-slate-500">Branch information is coming soon.</p>
        @endforelse
    </div>
    @if ($site['map_embed_url'])
        <div class="container-site mt-10">
            <iframe src="{{ $site['map_embed_url'] }}" class="h-80 w-full rounded-3xl border-0" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="C-STAR location map"></iframe>
        </div>
    @endif
</section>
@endsection
