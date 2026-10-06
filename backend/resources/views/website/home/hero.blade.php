{{-- Hero --}}
<section class="relative overflow-hidden bg-gradient-to-br from-brand-50 via-white to-sky-brand-50">
    <div class="absolute -top-32 -right-32 size-[28rem] rounded-full bg-sky-brand-100/70 blur-3xl"></div>
    <div class="absolute -bottom-40 -left-24 size-[26rem] rounded-full bg-brand-100/80 blur-3xl"></div>
    <div class="container-site relative grid items-center gap-12 py-16 sm:py-20 lg:grid-cols-[1.1fr_1fr] lg:py-24">
        <div>
            <span class="inline-flex items-center gap-2 rounded-full bg-white px-3 py-1 text-xs font-semibold text-brand-700 shadow-sm ring-1 ring-brand-100">
                <x-icon name="sparkles" class="size-4" /> {{ $site['tagline'] }}
            </span>
            <h1 class="mt-5 font-display text-4xl leading-[1.1] font-black tracking-tight text-slate-900 sm:text-5xl lg:text-6xl">
                {{ $site['hero_title'] }}
            </h1>
            <p class="mt-5 max-w-xl text-lg leading-relaxed text-slate-600">{{ $site['hero_subtitle'] }}</p>
            <p class="font-bn mt-2 text-slate-500">স্পিচ থেরাপি, অটিজম সহায়তা ও শিশুর বিকাশ — এক জায়গায়, যত্নের সাথে।</p>
            <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                <a href="{{ route('appointment') }}" class="btn btn-primary px-7"><x-icon name="calendar" class="size-4" /> Book an Appointment</a>
                <a href="{{ route('services') }}" class="btn btn-secondary px-7">Explore Services <x-icon name="arrow-right" class="size-4" /></a>
            </div>
        </div>

        {{-- Friendly illustration: two pathways (therapy + training) around the child --}}
        <div class="relative mx-auto w-full max-w-md" aria-hidden="true">
            <div class="aspect-square rounded-[3rem] bg-gradient-to-br from-brand-500 to-sky-brand-600 p-8 shadow-2xl shadow-brand-500/30">
                <div class="grid h-full grid-cols-2 gap-4">
                    @foreach ([['mic', 'Speech', 'bg-white/95 text-sky-brand-700'], ['hand', 'OT', 'bg-sun-100 text-amber-700'], ['puzzle', 'ABA', 'bg-coral-100 text-rose-700'], ['activity', 'Training', 'bg-brand-100 text-brand-700']] as [$i, $l, $c])
                        <div class="flex flex-col items-center justify-center gap-2 rounded-3xl {{ $c }} shadow-lg">
                            <x-icon :name="$i" class="size-10" />
                            <span class="font-display text-sm font-extrabold">{{ $l }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="absolute -bottom-5 -left-5 flex items-center gap-3 rounded-2xl bg-white px-4 py-3 shadow-xl">
                <span class="flex size-10 items-center justify-center rounded-full bg-brand-100 text-brand-700"><x-icon name="heart" class="size-5" /></span>
                <span class="text-sm leading-tight"><b class="block text-slate-900">Family-centred</b><span class="text-slate-500">Home practice every week</span></span>
            </div>
        </div>
    </div>
</section>
