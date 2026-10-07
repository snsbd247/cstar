@php
    $nav = [
        ['home', 'Home'], ['about', 'About'], ['services', 'Services'], ['therapists', 'Therapists'],
        ['trainers', 'Training'], ['branches', 'Branches'], ['faq', 'FAQ'], ['contact', 'Contact'],
    ];
    $siteSettings = app(\App\Services\SiteSettings::class);
    $hiddenPages = $siteSettings->navHidden();
    $nav = array_values(array_filter($nav, fn ($item) => $item[0] === 'home' || ! in_array($item[0], $hiddenPages, true)));
    $pageSeo = $siteSettings->pageSeo()[request()->route()?->getName() ?? ''] ?? [];
    $title = trim($__env->yieldContent('title'));
    $fullTitle = ! empty($pageSeo['title']) ? $pageSeo['title'] : ($title ? "$title | C-STAR" : 'C-STAR — Center for Speech Therapy & Autism Rehabilitation');
    $description = ($pageSeo['description'] ?? '') ?: (trim($__env->yieldContent('description')) ?: ($site['seo_default_description'] ?: $site['hero_subtitle']));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $fullTitle }}</title>
    <meta name="description" content="{{ \Illuminate\Support\Str::limit(strip_tags($description), 160) }}">
    <link rel="canonical" href="{{ url()->current() }}">
    @if ($site['seo_noindex'] === '1')
        <meta name="robots" content="noindex, nofollow">
    @endif
    @if ($site['google_site_verification'])
        <meta name="google-site-verification" content="{{ $site['google_site_verification'] }}">
    @endif
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="C-STAR">
    <meta property="og:title" content="{{ $fullTitle }}">
    <meta property="og:description" content="{{ \Illuminate\Support\Str::limit(strip_tags($description), 200) }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta name="theme-color" content="#059669">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600&family=Inter:wght@400;500;600;700&family=Nunito:wght@700;800;900&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if ($site['google_analytics_id'] && $site['seo_noindex'] !== '1')
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $site['google_analytics_id'] }}"></script>
        <script>window.dataLayer = window.dataLayer || []; function gtag(){dataLayer.push(arguments);} gtag('js', new Date()); gtag('config', @json($site['google_analytics_id']));</script>
    @endif
    <script type="application/ld+json">
        {!! json_encode(array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'MedicalClinic',
            'name' => 'Center for Speech Therapy & Autism Rehabilitation (C-STAR)',
            'alternateName' => 'C-STAR',
            'url' => url('/'),
            'telephone' => $site['phone'] ?: null,
            'email' => $site['email'] ?: null,
            'address' => $site['address'] ?: null,
            'medicalSpecialty' => ['SpeechPathology', 'OccupationalTherapy', 'Pediatric'],
            'areaServed' => 'Bangladesh',
        ]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}
    </script>
    @stack('head')
</head>
<body class="flex min-h-screen flex-col">
<a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2">Skip to content</a>

{{-- Top contact strip --}}
@if ($site['phone'] || $site['opening_hours'])
    <div class="hidden bg-brand-900 text-sm text-brand-50 sm:block">
        <div class="container-site flex h-9 items-center justify-between">
            <span class="flex items-center gap-2"><x-icon name="clock" class="size-4" /> {{ $site['opening_hours'] }}</span>
            <span class="flex items-center gap-5">
                @if ($site['phone'])<a href="tel:{{ $site['phone'] }}" class="flex items-center gap-1.5 hover:text-white"><x-icon name="phone" class="size-4" /> {{ $site['phone'] }}</a>@endif
                @if ($site['email'])<a href="mailto:{{ $site['email'] }}" class="flex items-center gap-1.5 hover:text-white"><x-icon name="mail" class="size-4" /> {{ $site['email'] }}</a>@endif
            </span>
        </div>
    </div>
@endif

<header class="sticky top-0 z-40 border-b border-slate-100 bg-white/90 backdrop-blur">
    <div class="container-site flex h-16 items-center justify-between gap-4 lg:h-20">
        <a href="{{ route('home') }}" class="flex items-center gap-2.5" aria-label="C-STAR home">
            <span class="flex size-10 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-500 to-sky-brand-600 text-white shadow-md shadow-brand-500/30">
                <x-icon name="star" class="size-5 fill-current" />
            </span>
            <span class="leading-tight">
                <span class="block font-display text-xl font-black tracking-tight text-slate-900">C-STAR</span>
                <span class="hidden text-[11px] text-slate-500 sm:block">Speech Therapy &amp; Autism Rehabilitation</span>
            </span>
        </a>

        <nav class="hidden items-center gap-1 lg:flex" aria-label="Main">
            @foreach ($nav as [$route, $label])
                <a href="{{ route($route) }}" @class([
                    'rounded-full px-3.5 py-2 text-sm font-medium transition',
                    'bg-brand-50 text-brand-700' => request()->routeIs($route, "$route.*") || ($route === 'services' && request()->routeIs('service')) || ($route === 'therapists' && request()->routeIs('therapist')),
                    'text-slate-600 hover:text-slate-900' => ! request()->routeIs($route),
                ])>{{ $label }}</a>
            @endforeach
        </nav>

        <div class="flex items-center gap-2">
            <a href="{{ route('appointment') }}" class="btn btn-primary hidden sm:inline-flex">
                <x-icon name="calendar" class="size-4" /> Book Appointment
            </a>
            <button type="button" class="rounded-full p-2.5 text-slate-700 hover:bg-slate-100 lg:hidden" data-menu-toggle aria-expanded="false" aria-controls="mobile-menu" aria-label="Open menu">
                <x-icon name="menu" class="size-6" />
            </button>
        </div>
    </div>

    <div id="mobile-menu" data-menu class="hidden border-t border-slate-100 bg-white lg:hidden">
        <nav class="container-site grid gap-1 py-4" aria-label="Mobile">
            @foreach ($nav as [$route, $label])
                <a href="{{ route($route) }}" class="rounded-xl px-4 py-3 text-base font-medium text-slate-700 hover:bg-slate-50">{{ $label }}</a>
            @endforeach
            <a href="{{ route('appointment') }}" class="btn btn-primary mt-2">Book Appointment</a>
            <a href="{{ url('/login') }}" class="btn btn-secondary">Staff &amp; Parent Login</a>
        </nav>
    </div>
</header>

<main id="main" class="flex-1">
    @yield('content')
</main>

{{-- Appointment call-to-action shown on every page except the form itself --}}
@unless (request()->routeIs('appointment*'))
    <section class="container-site pb-16">
        <div class="relative overflow-hidden rounded-[2rem] bg-gradient-to-br from-brand-600 via-brand-700 to-sky-brand-700 px-6 py-12 text-center text-white sm:px-12">
            <div class="absolute -top-16 -left-16 size-56 rounded-full bg-white/10"></div>
            <div class="absolute -right-10 -bottom-20 size-64 rounded-full bg-white/10"></div>
            <div class="relative">
                <h2 class="font-display text-3xl font-extrabold sm:text-4xl">Not sure where to start?</h2>
                <p class="mx-auto mt-3 max-w-xl text-brand-50">Book an assessment. Our team will understand your child's needs and suggest the right therapy or training plan.</p>
                <p class="font-bn mt-1 text-brand-100">আপনার সন্তানের জন্য সঠিক পরামর্শ পেতে আজই যোগাযোগ করুন।</p>
                <div class="mt-7 flex flex-col justify-center gap-3 sm:flex-row">
                    <a href="{{ route('appointment') }}" class="btn btn-light"><x-icon name="calendar" class="size-4" /> Request an Appointment</a>
                    @if ($site['phone'])
                        <a href="tel:{{ $site['phone'] }}" class="btn border border-white/40 text-white hover:bg-white/10"><x-icon name="phone" class="size-4" /> Call {{ $site['phone'] }}</a>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endunless

<footer class="bg-slate-900 text-slate-300">
    <div class="container-site grid gap-10 py-14 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <p class="font-display text-2xl font-black text-white">C-STAR</p>
            <p class="mt-1 text-sm">Center for Speech Therapy &amp; Autism Rehabilitation</p>
            <p class="mt-4 text-sm text-slate-400">{{ $site['tagline'] }}</p>
            <div class="mt-4 flex gap-2">
                @if ($site['facebook_url'])<a href="{{ $site['facebook_url'] }}" class="rounded-full bg-white/10 p-2 hover:bg-white/20" aria-label="Facebook" rel="noopener" target="_blank"><x-icon name="facebook" class="size-4" /></a>@endif
                @if ($site['youtube_url'])<a href="{{ $site['youtube_url'] }}" class="rounded-full bg-white/10 p-2 hover:bg-white/20" aria-label="YouTube" rel="noopener" target="_blank"><x-icon name="youtube" class="size-4" /></a>@endif
            </div>
        </div>
        <div>
            <p class="font-semibold text-white">Therapy services</p>
            <ul class="mt-3 space-y-2 text-sm">
                @foreach ($footerServices as $s)
                    <li><a href="{{ route('service', $s->slug) }}" class="hover:text-white">{{ $s->name }}</a></li>
                @endforeach
            </ul>
        </div>
        <div>
            <p class="font-semibold text-white">Quick links</p>
            <ul class="mt-3 space-y-2 text-sm">
                <li><a href="{{ route('trainers') }}" class="hover:text-white">Training programs</a></li>
                <li><a href="{{ route('gallery') }}" class="hover:text-white">Gallery</a></li>
                <li><a href="{{ route('notices') }}" class="hover:text-white">Notices</a></li>
                <li><a href="{{ route('faq') }}" class="hover:text-white">FAQ</a></li>
                <li><a href="{{ url('/login') }}" class="hover:text-white">Staff &amp; parent login</a></li>
            </ul>
        </div>
        <div>
            <p class="font-semibold text-white">Contact</p>
            <ul class="mt-3 space-y-3 text-sm">
                @if ($site['address'])<li class="flex gap-2"><x-icon name="map-pin" class="mt-0.5 size-4 shrink-0" /> {{ $site['address'] }}</li>@endif
                @if ($site['phone'])<li><a href="tel:{{ $site['phone'] }}" class="flex gap-2 hover:text-white"><x-icon name="phone" class="size-4" /> {{ $site['phone'] }}</a></li>@endif
                @if ($site['email'])<li><a href="mailto:{{ $site['email'] }}" class="flex gap-2 hover:text-white"><x-icon name="mail" class="size-4" /> {{ $site['email'] }}</a></li>@endif
                <li class="flex gap-2"><x-icon name="clock" class="mt-0.5 size-4 shrink-0" /> {{ $site['opening_hours'] }}</li>
            </ul>
        </div>
    </div>
    <div class="border-t border-white/10">
        <div class="container-site flex flex-col gap-2 py-5 text-xs text-slate-500 sm:flex-row sm:justify-between">
            <p>&copy; {{ now()->year }} C-STAR. All rights reserved.</p>
            <p>Children's information is kept private and confidential.</p>
        </div>
    </div>
</footer>

@if ($site['whatsapp'])
    <a href="https://wa.me/{{ preg_replace('/\D/', '', $site['whatsapp']) }}" target="_blank" rel="noopener"
       class="fixed right-4 bottom-4 z-40 flex size-14 items-center justify-center rounded-full bg-[#25D366] text-white shadow-lg shadow-black/20 transition hover:scale-105" aria-label="Chat on WhatsApp">
        <x-icon name="whatsapp" class="size-7" />
    </a>
@endif
</body>
</html>
