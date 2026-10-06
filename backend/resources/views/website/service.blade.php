@extends('layouts.website')
@section('title', $service->name)
@section('description', $service->short_description ?: $service->name.' at C-STAR')

@section('content')
@php [$icon, $tone] = \App\Support\ServiceIcon::for($service); @endphp
<section class="bg-gradient-to-br from-brand-50 via-white to-sky-brand-50">
    <div class="container-site py-14 sm:py-20">
        <a href="{{ route('services') }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-800">&larr; All services</a>
        <div class="mt-6 flex flex-col gap-6 sm:flex-row sm:items-center">
            <span class="flex size-16 shrink-0 items-center justify-center rounded-3xl {{ $tone }}"><x-icon :name="$icon" class="size-8" /></span>
            <div>
                <p class="eyebrow">{{ $service->category->value === 'training' ? 'Training program' : 'Therapy service' }}</p>
                <h1 class="heading mt-1 sm:text-5xl">{{ $service->name }}</h1>
                @if ($service->name_bn)<p class="font-bn mt-1 text-lg text-slate-500">{{ $service->name_bn }}</p>@endif
            </div>
        </div>
        @if ($service->short_description)<p class="mt-6 max-w-3xl text-lg text-slate-600">{{ $service->short_description }}</p>@endif
    </div>
</section>

<section class="section">
    <div class="container-site grid gap-12 lg:grid-cols-[1.6fr_1fr]">
        <div class="prose-site text-slate-600">
            @if ($service->image_path)
                <img src="{{ $service->imageUrl() }}" alt="{{ $service->name }}" class="mb-8 aspect-video w-full rounded-3xl object-cover">
            @endif
            @forelse (preg_split('/\n\s*\n/', (string) $service->description, -1, PREG_SPLIT_NO_EMPTY) as $paragraph)
                <p>{!! nl2br(e($paragraph)) !!}</p>
            @empty
                <p>Please contact us to learn more about {{ $service->name }} at C-STAR.</p>
            @endforelse
        </div>

        <aside class="space-y-5">
            <div class="rounded-3xl bg-slate-50 p-6">
                <p class="font-display text-lg font-bold text-slate-900">At a glance</p>
                <dl class="mt-3 space-y-2 text-sm">
                    @if ($service->default_duration_min)
                        <div class="flex justify-between"><dt class="text-slate-500">Session length</dt><dd class="font-medium text-slate-800">{{ $service->default_duration_min }} minutes</dd></div>
                    @endif
                    <div class="flex justify-between"><dt class="text-slate-500">Starts with</dt><dd class="font-medium text-slate-800">Assessment</dd></div>
                </dl>
                @if ($service->is_bookable_online)
                    <a href="{{ route('appointment', ['service' => $service->id]) }}" class="btn btn-primary mt-5 w-full"><x-icon name="calendar" class="size-4" /> Book this service</a>
                @else
                    <a href="{{ route('appointment') }}" class="btn btn-primary mt-5 w-full">Request an assessment</a>
                @endif
            </div>

            @if ($therapists->isNotEmpty())
                <div class="rounded-3xl border border-slate-100 p-6">
                    <p class="font-display text-lg font-bold text-slate-900">Therapists for this service</p>
                    <ul class="mt-3 space-y-3">
                        @foreach ($therapists as $t)
                            <li><a href="{{ route('therapist', $t->slug) }}" class="flex items-center gap-3 hover:text-brand-700">
                                <span class="flex size-10 items-center justify-center rounded-full bg-brand-100 font-bold text-brand-700">{{ mb_substr($t->name, 0, 1) }}</span>
                                <span><span class="block font-medium text-slate-900">{{ $t->name }}</span><span class="block text-xs text-slate-500">{{ $t->designation ?: $t->therapist_type->label() }}</span></span>
                            </a></li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </aside>
    </div>
</section>

@if ($related->isNotEmpty())
<section class="container-site pb-16">
    <h2 class="font-display text-2xl font-bold text-slate-900">Related services</h2>
    <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($related as $service)
            @include('partials.service-card')
        @endforeach
    </div>
</section>
@endif
@endsection
