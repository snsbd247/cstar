{{-- @include('partials.page-hero', ['eyebrow' => '', 'title' => '', 'subtitle' => '']) --}}
<section class="relative overflow-hidden bg-gradient-to-br from-brand-50 via-white to-sky-brand-50">
    <div class="absolute -top-24 -right-24 size-72 rounded-full bg-sky-brand-100/60 blur-2xl"></div>
    <div class="absolute -bottom-24 -left-16 size-64 rounded-full bg-brand-100/70 blur-2xl"></div>
    <div class="container-site relative py-14 sm:py-20">
        @isset($eyebrow)<p class="eyebrow">{{ $eyebrow }}</p>@endisset
        <h1 class="heading mt-2 max-w-3xl sm:text-5xl">{{ $title }}</h1>
        @isset($subtitle)<p class="mt-4 max-w-2xl text-lg text-slate-600">{{ $subtitle }}</p>@endisset
    </div>
</section>
