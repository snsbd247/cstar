{{-- Notices --}}
@if ($notices->isNotEmpty())
<section class="section pb-0">
    <div class="container-site">
        <p class="eyebrow">Notice board</p>
        <div class="mt-4 grid gap-4 md:grid-cols-3">
            @foreach ($notices as $notice)
                <a href="{{ route('notice', $notice->slug) }}" class="rounded-2xl border border-slate-100 p-5 hover:border-brand-200 hover:bg-brand-50/40">
                    <p class="flex items-center gap-2 text-xs text-slate-500"><x-icon name="bell" class="size-4 text-brand-600" /> {{ ($notice->publish_at ?? $notice->created_at)->format('d M Y') }}</p>
                    <p class="mt-2 font-semibold text-slate-900">{{ $notice->title }}</p>
                </a>
            @endforeach
        </div>
    </div>
</section>
@endif
