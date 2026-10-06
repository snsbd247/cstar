@extends('layouts.website')
@section('title', 'Frequently Asked Questions')

@section('content')
@include('partials.page-hero', ['eyebrow' => 'FAQ', 'title' => 'Frequently asked questions', 'subtitle' => 'Can\'t find your answer? Call us or send a message — we are happy to help.'])

<section class="section">
    <div class="container-site max-w-3xl">
        @forelse ($faqs as $category => $items)
            <h2 class="mt-10 mb-4 font-display text-xl font-bold text-slate-900 first:mt-0">{{ $category }}</h2>
            <div class="space-y-3">
                @foreach ($items as $faq)
                    <details class="group rounded-2xl border border-slate-100 bg-white p-5 shadow-sm open:border-brand-200">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-semibold text-slate-900">
                            {{ $faq->question }}
                            <x-icon name="chevron-down" class="size-5 shrink-0 text-slate-400 transition group-open:rotate-180" />
                        </summary>
                        <p class="mt-3 leading-relaxed whitespace-pre-line text-slate-600">{{ $faq->answer }}</p>
                    </details>
                @endforeach
            </div>
        @empty
            <p class="text-slate-500">Questions and answers are coming soon.</p>
        @endforelse
        <div class="mt-10 flex flex-col gap-3 sm:flex-row">
            <a href="{{ route('contact') }}" class="btn btn-secondary">Ask a question</a>
        </div>
    </div>
</section>
@endsection
