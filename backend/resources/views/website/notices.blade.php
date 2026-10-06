@extends('layouts.website')
@section('title', 'Notices')

@section('content')
@include('partials.page-hero', ['eyebrow' => 'Notice board', 'title' => 'Notices & announcements'])

<section class="section">
    <div class="container-site max-w-3xl space-y-4">
        @forelse ($notices as $notice)
            <a href="{{ route('notice', $notice->slug) }}" class="block rounded-2xl border border-slate-100 p-6 transition hover:border-brand-200 hover:bg-brand-50/40">
                <p class="flex items-center gap-2 text-xs text-slate-500"><x-icon name="bell" class="size-4 text-brand-600" /> {{ ($notice->publish_at ?? $notice->created_at)->format('d M Y') }}</p>
                <h2 class="mt-2 font-display text-lg font-bold text-slate-900">{{ $notice->title }}</h2>
                <p class="mt-1 line-clamp-2 text-slate-600">{{ $notice->body }}</p>
            </a>
        @empty
            <p class="text-slate-500">No notices right now.</p>
        @endforelse
        {{ $notices->links() }}
    </div>
</section>
@endsection
