{{-- Gallery --}}
@if ($gallery->isNotEmpty())
<section class="section">
    <div class="container-site">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div><p class="eyebrow">Gallery</p><h2 class="heading mt-2">Life at C-STAR</h2></div>
            <a href="{{ route('gallery') }}" class="btn btn-secondary shrink-0">View gallery</a>
        </div>
        <div class="mt-8 grid grid-cols-2 gap-3 md:grid-cols-3">
            @foreach ($gallery as $item)
                <img src="{{ $item->imageUrl() }}" alt="{{ $item->title }}" loading="lazy" class="aspect-[4/3] w-full rounded-2xl object-cover">
            @endforeach
        </div>
    </div>
</section>
@endif
