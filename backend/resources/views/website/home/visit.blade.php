{{-- Branches + FAQ --}}
<section class="section bg-slate-50">
    <div class="container-site grid gap-12 lg:grid-cols-2">
        <div>
            <p class="eyebrow">Visit us</p>
            <h2 class="heading mt-2">Our {{ $publicBranches->count() === 1 ? 'center' : 'branches' }}</h2>
            <div class="mt-6 space-y-4">
                @foreach ($publicBranches as $branch)
                    <div class="rounded-3xl bg-white p-6 shadow-sm">
                        <h3 class="font-display text-lg font-bold text-slate-900">{{ $branch->name }}</h3>
                        @if ($branch->name_bn)<p class="font-bn text-sm text-slate-500">{{ $branch->name_bn }}</p>@endif
                        <div class="mt-3 space-y-1.5 text-sm text-slate-600">
                            @if ($branch->address)<p class="flex gap-2"><x-icon name="map-pin" class="mt-0.5 size-4 shrink-0 text-brand-600" /> {{ $branch->address }}</p>@endif
                            @if ($branch->phone)<p><a href="tel:{{ $branch->phone }}" class="flex gap-2 hover:text-brand-700"><x-icon name="phone" class="size-4 text-brand-600" /> {{ $branch->phone }}</a></p>@endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        @if ($faqs->isNotEmpty())
            <div>
                <p class="eyebrow">Questions</p>
                <h2 class="heading mt-2">Frequently asked</h2>
                <div class="mt-6 space-y-3">
                    @foreach ($faqs as $faq)
                        <details class="group rounded-2xl bg-white p-5 shadow-sm">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-semibold text-slate-900">
                                {{ $faq->question }}
                                <x-icon name="chevron-down" class="size-5 shrink-0 text-slate-400 transition group-open:rotate-180" />
                            </summary>
                            <p class="mt-3 leading-relaxed text-slate-600">{{ $faq->answer }}</p>
                        </details>
                    @endforeach
                </div>
                <a href="{{ route('faq') }}" class="mt-5 inline-flex items-center gap-1 text-sm font-semibold text-brand-700">All questions <x-icon name="arrow-right" class="size-4" /></a>
            </div>
        @endif
    </div>
</section>
