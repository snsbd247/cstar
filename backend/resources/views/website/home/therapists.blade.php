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
