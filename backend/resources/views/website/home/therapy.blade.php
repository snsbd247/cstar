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
