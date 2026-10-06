@extends('layouts.website')
@section('title', 'Contact')
@section('description', 'Contact C-STAR — phone, email, address and message form.')

@section('content')
@include('partials.page-hero', ['eyebrow' => 'Contact', 'title' => 'We would love to hear from you', 'subtitle' => 'Questions about therapy, training or fees? Send us a message and our front desk will reply.'])

<section class="section">
    <div class="container-site grid gap-10 lg:grid-cols-[1fr_1.3fr]">
        <div class="space-y-4">
            @if ($site['phone'])
                <a href="tel:{{ $site['phone'] }}" class="flex items-center gap-4 rounded-3xl bg-brand-50 p-5 hover:bg-brand-100">
                    <span class="flex size-12 items-center justify-center rounded-2xl bg-white text-brand-700"><x-icon name="phone" /></span>
                    <span><span class="block text-sm text-slate-500">Call us</span><span class="font-semibold text-slate-900">{{ $site['phone'] }}</span></span>
                </a>
            @endif
            @if ($site['email'])
                <a href="mailto:{{ $site['email'] }}" class="flex items-center gap-4 rounded-3xl bg-sky-brand-50 p-5 hover:bg-sky-brand-100">
                    <span class="flex size-12 items-center justify-center rounded-2xl bg-white text-sky-brand-700"><x-icon name="mail" /></span>
                    <span><span class="block text-sm text-slate-500">Email</span><span class="font-semibold text-slate-900">{{ $site['email'] }}</span></span>
                </a>
            @endif
            <div class="flex items-center gap-4 rounded-3xl bg-slate-50 p-5">
                <span class="flex size-12 items-center justify-center rounded-2xl bg-white text-slate-700"><x-icon name="clock" /></span>
                <span><span class="block text-sm text-slate-500">Opening hours</span><span class="font-semibold text-slate-900">{{ $site['opening_hours'] }}</span></span>
            </div>
            @foreach ($publicBranches as $branch)
                @if ($branch->address)
                    <div class="flex items-start gap-4 rounded-3xl border border-slate-100 p-5">
                        <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-brand-50 text-brand-700"><x-icon name="map-pin" /></span>
                        <span><span class="block font-semibold text-slate-900">{{ $branch->name }}</span><span class="text-sm text-slate-600">{{ $branch->address }}</span></span>
                    </div>
                @endif
            @endforeach
        </div>

        <div class="rounded-[2rem] border border-slate-100 bg-white p-6 shadow-xl shadow-slate-200/60 sm:p-8">
            @if (session('sent'))
                <div class="flex flex-col items-center py-10 text-center" role="status">
                    <span class="flex size-16 items-center justify-center rounded-full bg-brand-100 text-brand-700"><x-icon name="check" class="size-8" /></span>
                    <h2 class="mt-4 font-display text-2xl font-bold text-slate-900">Message sent</h2>
                    <p class="mt-2 text-slate-600">Thank you. Our front desk will get back to you soon.</p>
                    <p class="font-bn text-slate-500">ধন্যবাদ, আমরা শীঘ্রই আপনার সাথে যোগাযোগ করব।</p>
                </div>
            @else
                <h2 class="font-display text-2xl font-bold text-slate-900">Send a message</h2>
                <form method="POST" action="{{ route('contact.store') }}" class="mt-6 grid gap-4 sm:grid-cols-2" novalidate>
                    @csrf
                    <div class="hidden" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
                    <div class="sm:col-span-2">
                        <label class="form-label" for="name">Your name</label>
                        <input class="form-control" id="name" name="name" value="{{ old('name') }}" required autocomplete="name">
                        @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="form-label" for="phone">Mobile</label>
                        <input class="form-control" id="phone" name="phone" value="{{ old('phone') }}" inputmode="tel" placeholder="01XXXXXXXXX" autocomplete="tel">
                        @error('phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="form-label" for="email">Email</label>
                        <input class="form-control" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email">
                        @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    @if ($publicBranches->count() > 1)
                        <div class="sm:col-span-2">
                            <label class="form-label" for="branch_id">Branch</label>
                            <select class="form-control" id="branch_id" name="branch_id">
                                <option value="">Any branch</option>
                                @foreach ($publicBranches as $b)<option value="{{ $b->id }}" @selected(old('branch_id') == $b->id)>{{ $b->name }}</option>@endforeach
                            </select>
                        </div>
                    @endif
                    <div class="sm:col-span-2">
                        <label class="form-label" for="subject">Subject</label>
                        <input class="form-control" id="subject" name="subject" value="{{ old('subject') }}">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="form-label" for="message">Message</label>
                        <textarea class="form-control" id="message" name="message" rows="5" required>{{ old('message') }}</textarea>
                        @error('message')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-2">
                        <button class="btn btn-primary w-full sm:w-auto" type="submit">Send message <x-icon name="arrow-right" class="size-4" /></button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</section>
@endsection
