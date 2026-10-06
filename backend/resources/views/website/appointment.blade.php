@extends('layouts.website')
@section('title', 'Book an Appointment')
@section('description', 'Request an appointment or assessment at C-STAR. Our front desk will call you to confirm.')

@section('content')
@include('partials.page-hero', ['eyebrow' => 'Appointment', 'title' => 'Request an appointment', 'subtitle' => 'Tell us a little about your child. Our front desk will call you to confirm the date and time.'])

<section class="section pt-10">
    <div class="container-site grid gap-10 lg:grid-cols-[1.5fr_1fr]">
        <form method="POST" action="{{ route('appointment.store') }}" class="rounded-[2rem] border border-slate-100 bg-white p-6 shadow-xl shadow-slate-200/60 sm:p-8" novalidate>
            @csrf
            <div class="hidden" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

            @if ($errors->any())
                <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">Please check the highlighted fields.</div>
            @endif

            <fieldset>
                <legend class="font-display text-lg font-bold text-slate-900">1. Parent &amp; child</legend>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="form-label" for="parent_name">Parent / guardian name *</label>
                        <input class="form-control" id="parent_name" name="parent_name" value="{{ old('parent_name') }}" required autocomplete="name">
                        @error('parent_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="form-label" for="phone">Mobile number *</label>
                        <input class="form-control" id="phone" name="phone" value="{{ old('phone') }}" required inputmode="tel" placeholder="01XXXXXXXXX" autocomplete="tel">
                        @error('phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="form-label" for="child_name">Child's name *</label>
                        <input class="form-control" id="child_name" name="child_name" value="{{ old('child_name') }}" required>
                        @error('child_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="form-label" for="child_age_years">Child's age</label>
                            <input class="form-control" id="child_age_years" name="child_age_years" type="number" min="0" max="25" value="{{ old('child_age_years') }}" placeholder="Years">
                            @error('child_age_years')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="form-label" for="email">Email</label>
                            <input class="form-control" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email">
                            @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>
            </fieldset>

            <fieldset class="mt-8">
                <legend class="font-display text-lg font-bold text-slate-900">2. What you need</legend>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="form-label" for="branch_id">Branch *</label>
                        <select class="form-control" id="branch_id" name="branch_id" required>
                            @if ($publicBranches->count() > 1)<option value="">Choose a branch</option>@endif
                            @foreach ($publicBranches as $b)<option value="{{ $b->id }}" @selected(old('branch_id') == $b->id)>{{ $b->name }}</option>@endforeach
                        </select>
                        @error('branch_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="form-label" for="service_id">Service</label>
                        <select class="form-control" id="service_id" name="service_id">
                            <option value="">Not sure — please advise</option>
                            @foreach ($services as $s)<option value="{{ $s->id }}" @selected(old('service_id', $selectedService) == $s->id)>{{ $s->name }}</option>@endforeach
                        </select>
                        @error('service_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="form-label" for="preferred_therapist_id">Preferred therapist</label>
                        <select class="form-control" id="preferred_therapist_id" name="preferred_therapist_id">
                            <option value="">No preference</option>
                            @foreach ($therapists as $t)
                                <option value="{{ $t->id }}" data-services="{{ $t->services->pluck('id')->implode(',') }}" @selected(old('preferred_therapist_id', $selectedTherapist) == $t->id)>{{ $t->name }} — {{ $t->designation ?: $t->therapist_type->label() }}</option>
                            @endforeach
                        </select>
                        @error('preferred_therapist_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            </fieldset>

            <fieldset class="mt-8">
                <legend class="font-display text-lg font-bold text-slate-900">3. When suits you</legend>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="form-label" for="preferred_date">Preferred date</label>
                        <input class="form-control" id="preferred_date" name="preferred_date" type="date" min="{{ today()->toDateString() }}" max="{{ today()->addDays(89)->toDateString() }}" value="{{ old('preferred_date') }}">
                        @error('preferred_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <span class="form-label">Preferred time</span>
                        <div class="grid gap-2">
                            @foreach ($times as $value => $label)
                                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 px-4 py-2.5 text-sm has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                                    <input type="radio" name="preferred_time" value="{{ $value }}" class="accent-brand-600" @checked(old('preferred_time') === $value)> {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="form-label" for="message">Anything we should know?</label>
                        <textarea class="form-control" id="message" name="message" rows="4" placeholder="e.g. concerns about speech, previous diagnosis or therapy">{{ old('message') }}</textarea>
                        @error('message')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            </fieldset>

            <button type="submit" class="btn btn-primary mt-8 w-full sm:w-auto sm:px-10"><x-icon name="calendar" class="size-4" /> Send request</button>
            <p class="mt-3 text-xs text-slate-500">This is a request, not a confirmed booking. We will call you to confirm.</p>
        </form>

        <aside class="space-y-4">
            <div class="rounded-3xl bg-brand-50 p-6">
                <p class="font-display text-lg font-bold text-slate-900">What happens next?</p>
                <ol class="mt-4 space-y-4 text-sm text-slate-700">
                    @foreach (['We call you, usually within one working day.', 'We agree a date for the first visit or assessment.', 'Your child is registered and the team suggests a plan.'] as $i => $step)
                        <li class="flex gap-3"><span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-brand-600 text-xs font-bold text-white">{{ $i + 1 }}</span>{{ $step }}</li>
                    @endforeach
                </ol>
            </div>
            @if ($site['phone'])
                <a href="tel:{{ $site['phone'] }}" class="flex items-center gap-4 rounded-3xl border border-slate-100 p-6 hover:border-brand-200">
                    <span class="flex size-12 items-center justify-center rounded-2xl bg-sky-brand-50 text-sky-brand-700"><x-icon name="phone" /></span>
                    <span><span class="block text-sm text-slate-500">Prefer to talk?</span><span class="font-semibold text-slate-900">{{ $site['phone'] }}</span></span>
                </a>
            @endif
            <p class="font-bn rounded-3xl bg-slate-50 p-6 text-sm leading-relaxed text-slate-600">ফর্মটি পূরণ করুন, আমাদের প্রতিনিধি আপনাকে ফোন করে সময় নিশ্চিত করবেন। আপনার সন্তানের তথ্য গোপন রাখা হয়।</p>
        </aside>
    </div>
</section>
@endsection
