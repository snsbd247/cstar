@extends('layouts.website')
@section('title', 'Therapists')
@section('description', 'Meet the speech & language therapists, occupational therapists, ABA therapists and special educators at C-STAR.')

@section('content')
@include('partials.page-hero', ['eyebrow' => 'Our team', 'title' => 'Therapists', 'subtitle' => 'Qualified specialists who plan and deliver one-to-one therapy for each child.'])

<section class="section">
    <div class="container-site grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
        @forelse ($therapists as $person)
            @include('partials.person-card', ['role' => $person->designation ?: $person->therapist_type->label(), 'href' => route('therapist', $person->slug)])
        @empty
            <p class="text-slate-500 sm:col-span-4">Therapist profiles are coming soon.</p>
        @endforelse
    </div>
</section>
@endsection
