@extends('layouts.website')
@section('title', 'Training Program & Trainers')
@section('description', 'Regular functional training at C-STAR: daily living, motor, communication, social and learning skills in small classes with a dedicated trainer.')

@section('content')
@include('partials.page-hero', ['eyebrow' => 'Regular training program', 'title' => 'Learning by doing, every day', 'subtitle' => 'Regular students attend the center on a fixed schedule in a small class. A trainer follows an individual training plan for each child and records daily progress.'])

<section class="section">
    <div class="container-site grid gap-12 lg:grid-cols-2">
        <div>
            <h2 class="heading">What children work on</h2>
            <div class="mt-6 grid gap-3 sm:grid-cols-2">
                @foreach ($trainingPrograms as $service)
                    @php [$icon, $tone] = \App\Support\ServiceIcon::for($service); @endphp
                    <div class="flex items-center gap-3 rounded-2xl border border-slate-100 p-4">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-xl {{ $tone }}"><x-icon :name="$icon" class="size-5" /></span>
                        <span class="font-medium text-slate-800">{{ $service->name }}</span>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="rounded-3xl bg-brand-50 p-8">
            <h2 class="font-display text-2xl font-bold text-slate-900">Training is different from therapy</h2>
            <ul class="mt-5 space-y-4 text-slate-700">
                <li class="flex gap-3"><x-icon name="users" class="mt-0.5 size-5 shrink-0 text-brand-600" /><span><b>Trainers</b> run daily classes for physical and functional skills; <b>therapists</b> give one-to-one clinical therapy sessions.</span></li>
                <li class="flex gap-3"><x-icon name="calendar" class="mt-0.5 size-5 shrink-0 text-brand-600" /><span>Students follow a class schedule with daily attendance; therapy patients come for booked appointments.</span></li>
                <li class="flex gap-3"><x-icon name="heart" class="mt-0.5 size-5 shrink-0 text-brand-600" /><span>A child can be in a training class and also take therapy — both teams share one plan.</span></li>
            </ul>
        </div>
    </div>
</section>

@if ($trainers->isNotEmpty())
<section class="section bg-slate-50">
    <div class="container-site">
        <h2 class="heading">Our trainers</h2>
        <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($trainers as $person)
                @include('partials.person-card', ['role' => 'Trainer', 'href' => null])
            @endforeach
        </div>
    </div>
</section>
@endif
@endsection
