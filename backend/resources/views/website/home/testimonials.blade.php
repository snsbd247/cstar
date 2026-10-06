{{-- Testimonials (published from the CMS only) --}}
@if ($testimonials->isNotEmpty())
<section class="section bg-gradient-to-br from-sky-brand-50 to-brand-50">
    <div class="container-site">
        <div class="mx-auto max-w-2xl text-center">
            <p class="eyebrow">Parents say</p>
            <h2 class="heading mt-2">Stories from our families</h2>
        </div>
        <div class="mt-10 grid gap-5 md:grid-cols-3">
            @foreach ($testimonials as $t)
                <figure class="flex flex-col rounded-3xl bg-white p-6 shadow-sm">
                    <x-icon name="quote" class="size-8 text-brand-200" />
                    <blockquote class="mt-3 flex-1 leading-relaxed text-slate-700">{{ $t->content }}</blockquote>
                    <figcaption class="mt-5 border-t border-slate-100 pt-4">
                        <p class="font-semibold text-slate-900">{{ $t->name }}</p>
                        @if ($t->relation)<p class="text-sm text-slate-500">{{ $t->relation }}</p>@endif
                    </figcaption>
                </figure>
            @endforeach
        </div>
    </div>
</section>
@endif
