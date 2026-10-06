{{-- How it works --}}
<section class="section bg-slate-50">
    <div class="container-site">
        <div class="mx-auto max-w-2xl text-center">
            <p class="eyebrow">How it works</p>
            <h2 class="heading mt-2">Four simple steps</h2>
        </div>
        <ol class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['Request an appointment', 'Fill the online form or call us. We call you back to confirm a time.'],
                ['Assessment', "A specialist assesses your child's needs and talks with you."],
                ['Personal plan', 'Therapy, regular training or both — with clear goals.'],
                ['Track progress', 'Sessions, attendance and reports, shared with you regularly.'],
            ] as $i => [$title, $text])
                <li class="relative rounded-3xl bg-white p-6 shadow-sm">
                    <span class="font-display text-4xl font-black text-brand-200">0{{ $i + 1 }}</span>
                    <h3 class="mt-2 font-display font-bold text-slate-900">{{ $title }}</h3>
                    <p class="mt-1 text-sm text-slate-600">{{ $text }}</p>
                </li>
            @endforeach
        </ol>
    </div>
</section>
