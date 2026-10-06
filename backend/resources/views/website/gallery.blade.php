@extends('layouts.website')
@section('title', 'Gallery')

@section('content')
@include('partials.page-hero', ['eyebrow' => 'Gallery', 'title' => 'Life at C-STAR', 'subtitle' => 'Moments from therapy, training and events. Photos of children are shared only with their parents\' consent.'])

<section class="section">
    <div class="container-site">
        @if ($items->isEmpty())
            <div class="flex flex-col items-center rounded-3xl bg-slate-50 px-6 py-16 text-center">
                <x-icon name="image" class="size-10 text-slate-300" />
                <p class="mt-3 text-slate-500">Photos will be added soon.</p>
            </div>
        @else
            <div class="columns-1 gap-4 sm:columns-2 lg:columns-3">
                @foreach ($items as $item)
                    <figure class="mb-4 break-inside-avoid overflow-hidden rounded-3xl bg-slate-100">
                        <img src="{{ $item->imageUrl() }}" alt="{{ $item->title }}" loading="lazy" class="w-full">
                        <figcaption class="px-4 py-3 text-sm text-slate-600">{{ $item->title }}</figcaption>
                    </figure>
                @endforeach
            </div>
        @endif
    </div>
</section>
@endsection
