@extends('layouts.website')
@section('title', $notice->title)
@section('description', $notice->body)

@section('content')
<article class="section">
    <div class="container-site max-w-3xl">
        <a href="{{ route('notices') }}" class="text-sm text-slate-500 hover:text-slate-800">&larr; All notices</a>
        <p class="mt-6 text-sm text-slate-500">{{ ($notice->publish_at ?? $notice->created_at)->format('d F Y') }}</p>
        <h1 class="heading mt-2">{{ $notice->title }}</h1>
        <div class="prose-site mt-6 text-lg whitespace-pre-line text-slate-700">{{ $notice->body }}</div>
    </div>
</article>
@endsection
