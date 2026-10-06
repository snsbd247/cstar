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
