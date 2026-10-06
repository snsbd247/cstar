@extends('layouts.website')
@section('title', 'About')
@section('description', $site['about_body'])

@section('content')
@include('partials.page-hero', ['eyebrow' => 'About C-STAR', 'title' => $site['about_title'], 'subtitle' => $site['tagline']])

<section class="section">
    <div class="container-site grid gap-12 lg:grid-cols-[1.4fr_1fr]">
        <div class="prose-site text-lg text-slate-600">
            @foreach (preg_split('/\n\s*\n/', $site['about_body']) as $paragraph)
                <p>{{ $paragraph }}</p>
            @endforeach

            <h2>How we work with every child</h2>
            <p>Each child has one profile with us. Depending on what the assessment shows, a child may join:</p>
            <ul>
                <li><b>Therapy</b> — one-to-one sessions such as speech &amp; language therapy, occupational therapy or ABA, booked as appointments with a therapist;</li>
                <li><b>Regular training</b> — attending the center on a fixed schedule in a small class with a trainer, working on functional and daily-living skills;</li>
                <li><b>or both</b> — with therapists and trainers sharing one plan for the child.</li>
            </ul>
        </div>
        <div class="space-y-4">
            @if ($site['mission'])
                <div class="rounded-3xl bg-brand-50 p-6"><p class="eyebrow">Our mission</p><p class="mt-2 text-slate-700">{{ $site['mission'] }}</p></div>
            @endif
            @if ($site['vision'])
                <div class="rounded-3xl bg-sky-brand-50 p-6"><p class="eyebrow text-sky-brand-600">Our vision</p><p class="mt-2 text-slate-700">{{ $site['vision'] }}</p></div>
            @endif
            <div class="rounded-3xl border border-slate-100 p-6">
                <p class="font-display text-lg font-bold text-slate-900">Who we help</p>
                <ul class="mt-3 space-y-2 text-sm text-slate-600">
                    @foreach (['Autism spectrum disorder (ASD)', 'Speech and language delay', 'ADHD and attention difficulties', 'Cerebral palsy and motor difficulties', 'Down syndrome and developmental delay', 'Feeding and oral-motor difficulties'] as $item)
                        <li class="flex gap-2"><x-icon name="check" class="mt-0.5 size-4 shrink-0 text-brand-600" /> {{ $item }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</section>
@endsection
