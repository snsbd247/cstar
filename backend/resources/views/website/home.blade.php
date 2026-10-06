@extends('layouts.website')

@section('content')
{{-- Hero --}}
<section class="relative overflow-hidden bg-gradient-to-br from-brand-50 via-white to-sky-brand-50">
    <div class="absolute -top-32 -right-32 size-[28rem] rounded-full bg-sky-brand-100/70 blur-3xl"></div>
    <div class="absolute -bottom-40 -left-24 size-[26rem] rounded-full bg-brand-100/80 blur-3xl"></div>
    <div class="container-site relative grid items-center gap-12 py-16 sm:py-20 lg:grid-cols-[1.1fr_1fr] lg:py-24">
        <div>
            <span class="inline-flex items-center gap-2 rounded-full bg-white px-3 py-1 text-xs font-semibold text-brand-700 shadow-sm ring-1 ring-brand-100">
                <x-icon name="sparkles" class="size-4" /> {{ $site['tagline'] }}
            </span>
            <h1 class="mt-5 font-display text-4xl leading-[1.1] font-black tracking-tight text-slate-900 sm:text-5xl lg:text-6xl">
                {{ $site['hero_title'] }}
            </h1>
            <p class="mt-5 max-w-xl text-lg leading-relaxed text-slate-600">{{ $site['hero_subtitle'] }}</p>
            <p class="font-bn mt-2 text-slate-500">স্পিচ থেরাপি, অটিজম সহায়তা ও শিশুর বিকাশ — এক জায়গায়, যত্নের সাথে।</p>
            <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                <a href="{{ route('appointment') }}" class="btn btn-primary px-7"><x-icon name="calendar" class="size-4" /> Book an Appointment</a>
                <a href="{{ route('services') }}" class="btn btn-secondary px-7">Explore Services <x-icon name="arrow-right" class="size-4" /></a>
            </div>
        </div>

        {{-- Friendly illustration: two pathways (therapy + training) around the child --}}
        <div class="relative mx-auto w-full max-w-md" aria-hidden="true">
            <div class="aspect-square rounded-[3rem] bg-gradient-to-br from-brand-500 to-sky-brand-600 p-8 shadow-2xl shadow-brand-500/30">
                <div class="grid h-full grid-cols-2 gap-4">
                    @foreach ([['mic', 'Speech', 'bg-white/95 text-sky-brand-700'], ['hand', 'OT', 'bg-sun-100 text-amber-700'], ['puzzle', 'ABA', 'bg-coral-100 text-rose-700'], ['activity', 'Training', 'bg-brand-100 text-brand-700']] as [$i, $l, $c])
                        <div class="flex flex-col items-center justify-center gap-2 rounded-3xl {{ $c }} shadow-lg">
                            <x-icon :name="$i" class="size-10" />
                            <span class="font-display text-sm font-extrabold">{{ $l }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="absolute -bottom-5 -left-5 flex items-center gap-3 rounded-2xl bg-white px-4 py-3 shadow-xl">
                <span class="flex size-10 items-center justify-center rounded-full bg-brand-100 text-brand-700"><x-icon name="heart" class="size-5" /></span>
                <span class="text-sm leading-tight"><b class="block text-slate-900">Family-centred</b><span class="text-slate-500">Home practice every week</span></span>
            </div>
        </div>
    </div>
</section>

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

{{-- Therapy services --}}
@if ($therapyServices->isNotEmpty())
<section class="section">
    <div class="container-site">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="eyebrow">Therapy services</p>
                <h2 class="heading mt-2">One-to-one therapy, planned around your child</h2>
                <p class="mt-3 max-w-2xl text-slate-600">Appointment-based sessions with a qualified therapist. Children can join therapy only, or combine it with our regular training program.</p>
            </div>
            <a href="{{ route('services') }}" class="btn btn-secondary shrink-0">All services <x-icon name="arrow-right" class="size-4" /></a>
        </div>
        <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($therapyServices as $service)
                @include('partials.service-card')
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Training programs (separate category from therapy) --}}
@if ($trainingPrograms->isNotEmpty())
<section class="section bg-gradient-to-b from-brand-50/70 to-white">
    <div class="container-site grid gap-12 lg:grid-cols-[1fr_1.3fr] lg:items-center">
        <div>
            <p class="eyebrow">Regular training program</p>
            <h2 class="heading mt-2">Daily functional training in small groups</h2>
            <p class="mt-4 text-slate-600">Children enrolled as regular students attend the center on a fixed schedule. A dedicated trainer works on motor, daily-living, communication, social and learning skills, following an individual training plan with clear goals.</p>
            <ul class="mt-6 space-y-3">
                @foreach (['Small classes with a dedicated trainer', 'Individual training plan with measurable goals', 'Daily attendance and progress shared with parents'] as $point)
                    <li class="flex gap-3"><span class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-brand-600 text-white"><x-icon name="check" class="size-3.5" /></span><span class="text-slate-700">{{ $point }}</span></li>
                @endforeach
            </ul>
            <a href="{{ route('trainers') }}" class="btn btn-primary mt-8">About the training program <x-icon name="arrow-right" class="size-4" /></a>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ($trainingPrograms as $service)
                @php [$icon, $tone] = \App\Support\ServiceIcon::for($service); @endphp
                <div class="flex items-center gap-4 rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-xl {{ $tone }}"><x-icon :name="$icon" class="size-5" /></span>
                    <span><span class="block font-semibold text-slate-900">{{ $service->name }}</span>@if ($service->name_bn)<span class="font-bn block text-sm text-slate-500">{{ $service->name_bn }}</span>@endif</span>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- About + why choose us --}}
<section class="section">
    <div class="container-site grid gap-12 lg:grid-cols-2">
        <div>
            <p class="eyebrow">{{ $site['about_title'] }}</p>
            <h2 class="heading mt-2">A clear path from assessment to progress</h2>
            <div class="prose-site mt-4 text-slate-600">
                @foreach (preg_split('/\n\s*\n/', $site['about_body']) as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
            </div>
            <a href="{{ route('about') }}" class="btn btn-secondary mt-2">More about C-STAR</a>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ([
                ['clipboard', 'Assessment first', 'Every plan starts with a proper assessment of communication, development and daily skills.'],
                ['users', 'Therapy and training together', 'Therapists and trainers work as one team when a child needs both.'],
                ['heart', 'Parents as partners', 'Home programs and regular updates so practice continues at home.'],
                ['shield', 'Private and safe', "Children's records are confidential and only seen by their care team."],
            ] as [$icon, $title, $text])
                <div class="rounded-3xl bg-slate-50 p-6">
                    <span class="flex size-11 items-center justify-center rounded-2xl bg-white text-brand-700 shadow-sm"><x-icon :name="$icon" class="size-5" /></span>
                    <h3 class="mt-4 font-display font-bold text-slate-900">{{ $title }}</h3>
                    <p class="mt-1 text-sm leading-relaxed text-slate-600">{{ $text }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- How it works --}}
<section class="section bg-slate-50">
    <div class="container-site">
        <div class="mx-auto max-w-2xl text-center">
            <p class="eyebrow">How it works</p>
            <h2 class="heading mt-2">Four simple steps</h2>
        </div>
        <ol class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['Request an appointment', 'Fill the online form or call us. We call you back to confirm a time.'],
                ['Assessment', "A specialist assesses your child's needs and talks with you."],
                ['Personal plan', 'Therapy, regular training or both — with clear goals.'],
                ['Track progress', 'Sessions, attendance and reports, shared with you regularly.'],
            ] as $i => [$title, $text])
                <li class="relative rounded-3xl bg-white p-6 shadow-sm">
                    <span class="font-display text-4xl font-black text-brand-200">0{{ $i + 1 }}</span>
                    <h3 class="mt-2 font-display font-bold text-slate-900">{{ $title }}</h3>
                    <p class="mt-1 text-sm text-slate-600">{{ $text }}</p>
                </li>
            @endforeach
        </ol>
    </div>
</section>

{{-- Therapists --}}
@if ($therapists->isNotEmpty())
<section class="section">
    <div class="container-site">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="eyebrow">Our therapists</p>
                <h2 class="heading mt-2">Meet the team</h2>
            </div>
            <a href="{{ route('therapists') }}" class="btn btn-secondary shrink-0">All therapists <x-icon name="arrow-right" class="size-4" /></a>
        </div>
        <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($therapists as $person)
                @include('partials.person-card', ['role' => $person->designation ?: $person->therapist_type->label(), 'href' => route('therapist', $person->slug)])
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Testimonials (published from the CMS only) --}}
@if ($testimonials->isNotEmpty())
<section class="section bg-gradient-to-br from-sky-brand-50 to-brand-50">
    <div class="container-site">
        <div class="mx-auto max-w-2xl text-center">
            <p class="eyebrow">Parents say</p>
            <h2 class="heading mt-2">Stories from our families</h2>
        </div>
        <div class="mt-10 grid gap-5 md:grid-cols-3">
            @foreach ($testimonials as $t)
                <figure class="flex flex-col rounded-3xl bg-white p-6 shadow-sm">
                    <x-icon name="quote" class="size-8 text-brand-200" />
                    <blockquote class="mt-3 flex-1 leading-relaxed text-slate-700">{{ $t->content }}</blockquote>
                    <figcaption class="mt-5 border-t border-slate-100 pt-4">
                        <p class="font-semibold text-slate-900">{{ $t->name }}</p>
                        @if ($t->relation)<p class="text-sm text-slate-500">{{ $t->relation }}</p>@endif
                    </figcaption>
                </figure>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Gallery --}}
@if ($gallery->isNotEmpty())
<section class="section">
    <div class="container-site">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div><p class="eyebrow">Gallery</p><h2 class="heading mt-2">Life at C-STAR</h2></div>
            <a href="{{ route('gallery') }}" class="btn btn-secondary shrink-0">View gallery</a>
        </div>
        <div class="mt-8 grid grid-cols-2 gap-3 md:grid-cols-3">
            @foreach ($gallery as $item)
                <img src="{{ $item->imageUrl() }}" alt="{{ $item->title }}" loading="lazy" class="aspect-[4/3] w-full rounded-2xl object-cover">
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Branches + FAQ --}}
<section class="section bg-slate-50">
    <div class="container-site grid gap-12 lg:grid-cols-2">
        <div>
            <p class="eyebrow">Visit us</p>
            <h2 class="heading mt-2">Our {{ $publicBranches->count() === 1 ? 'center' : 'branches' }}</h2>
            <div class="mt-6 space-y-4">
                @foreach ($publicBranches as $branch)
                    <div class="rounded-3xl bg-white p-6 shadow-sm">
                        <h3 class="font-display text-lg font-bold text-slate-900">{{ $branch->name }}</h3>
                        @if ($branch->name_bn)<p class="font-bn text-sm text-slate-500">{{ $branch->name_bn }}</p>@endif
                        <div class="mt-3 space-y-1.5 text-sm text-slate-600">
                            @if ($branch->address)<p class="flex gap-2"><x-icon name="map-pin" class="mt-0.5 size-4 shrink-0 text-brand-600" /> {{ $branch->address }}</p>@endif
                            @if ($branch->phone)<p><a href="tel:{{ $branch->phone }}" class="flex gap-2 hover:text-brand-700"><x-icon name="phone" class="size-4 text-brand-600" /> {{ $branch->phone }}</a></p>@endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        @if ($faqs->isNotEmpty())
            <div>
                <p class="eyebrow">Questions</p>
                <h2 class="heading mt-2">Frequently asked</h2>
                <div class="mt-6 space-y-3">
                    @foreach ($faqs as $faq)
                        <details class="group rounded-2xl bg-white p-5 shadow-sm">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-semibold text-slate-900">
                                {{ $faq->question }}
                                <x-icon name="chevron-down" class="size-5 shrink-0 text-slate-400 transition group-open:rotate-180" />
                            </summary>
                            <p class="mt-3 leading-relaxed text-slate-600">{{ $faq->answer }}</p>
                        </details>
                    @endforeach
                </div>
                <a href="{{ route('faq') }}" class="mt-5 inline-flex items-center gap-1 text-sm font-semibold text-brand-700">All questions <x-icon name="arrow-right" class="size-4" /></a>
            </div>
        @endif
    </div>
</section>

{{-- Notices --}}
@if ($notices->isNotEmpty())
<section class="section pb-0">
    <div class="container-site">
        <p class="eyebrow">Notice board</p>
        <div class="mt-4 grid gap-4 md:grid-cols-3">
            @foreach ($notices as $notice)
                <a href="{{ route('notice', $notice->slug) }}" class="rounded-2xl border border-slate-100 p-5 hover:border-brand-200 hover:bg-brand-50/40">
                    <p class="flex items-center gap-2 text-xs text-slate-500"><x-icon name="bell" class="size-4 text-brand-600" /> {{ ($notice->publish_at ?? $notice->created_at)->format('d M Y') }}</p>
                    <p class="mt-2 font-semibold text-slate-900">{{ $notice->title }}</p>
                </a>
            @endforeach
        </div>
    </div>
</section>
@endif
<div class="h-16"></div>
@endsection
