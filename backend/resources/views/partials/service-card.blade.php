@php [$icon, $tone] = \App\Support\ServiceIcon::for($service); @endphp
<a href="{{ route('service', $service->slug) }}" class="group flex flex-col rounded-3xl border border-slate-100 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:border-brand-200 hover:shadow-lg hover:shadow-brand-500/10">
    <span class="flex size-12 items-center justify-center rounded-2xl {{ $tone }}">
        <x-icon :name="$icon" class="size-6" />
    </span>
    <h3 class="mt-4 font-display text-lg font-bold text-slate-900">{{ $service->name }}</h3>
    @if ($service->name_bn)<p class="font-bn text-sm text-slate-500">{{ $service->name_bn }}</p>@endif
    @if ($service->short_description)<p class="mt-2 flex-1 text-sm leading-relaxed text-slate-600">{{ $service->short_description }}</p>@endif
    <span class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-brand-700">
        Learn more <x-icon name="arrow-right" class="size-4 transition group-hover:translate-x-1" />
    </span>
</a>
