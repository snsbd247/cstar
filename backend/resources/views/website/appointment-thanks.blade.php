@extends('layouts.website')
@section('title', 'Request received')

@section('content')
<section class="section">
    <div class="container-site max-w-xl text-center" role="status">
        <span class="mx-auto flex size-20 items-center justify-center rounded-full bg-brand-100 text-brand-700"><x-icon name="check" class="size-10" /></span>
        <h1 class="heading mt-6">Thank you — we received your request</h1>
        <p class="mt-3 text-lg text-slate-600">Our front desk will call you soon to confirm a time.</p>
        <p class="font-bn mt-1 text-slate-500">ধন্যবাদ! আমাদের প্রতিনিধি শীঘ্রই আপনাকে ফোন করবেন।</p>
        <p class="mt-6 inline-block rounded-2xl bg-slate-50 px-5 py-3 text-sm text-slate-600">Your reference: <b class="font-mono text-slate-900">{{ $reference }}</b></p>
        <div class="mt-8"><a href="{{ route('home') }}" class="btn btn-secondary">Back to home</a></div>
    </div>
</section>
@endsection
