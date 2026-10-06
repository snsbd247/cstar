@extends('layouts.website')
@section('title', 'Services')
@section('description', 'Speech & language therapy, occupational therapy, ABA, oral placement therapy, special education, assessment and regular functional training at C-STAR.')

@section('content')
@include('partials.page-hero', ['eyebrow' => 'Services', 'title' => 'Therapy and training for every stage', 'subtitle' => 'Children can receive therapy sessions, join our regular training program, or both.'])

<section class="section">
    <div class="container-site">
        <h2 class="heading">Therapy services</h2>
        <p class="mt-2 max-w-2xl text-slate-600">One-to-one, appointment-based sessions with a qualified therapist.</p>
        <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($therapyServices as $service)
                @include('partials.service-card')
            @empty
                <p class="text-slate-500">Service details are coming soon.</p>
            @endforelse
        </div>
    </div>
</section>

@if ($trainingPrograms->isNotEmpty())
<section class="section bg-brand-50/60">
    <div class="container-site">
        <h2 class="heading">Training programs</h2>
        <p class="mt-2 max-w-2xl text-slate-600">For regular students who attend the center on a fixed schedule with a trainer.</p>
        <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($trainingPrograms as $service)
                @include('partials.service-card')
            @endforeach
        </div>
    </div>
</section>
@endif
@endsection
