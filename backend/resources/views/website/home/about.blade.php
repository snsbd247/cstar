{{-- About + why choose us --}}
<section class="section">
    <div class="container-site grid gap-12 lg:grid-cols-2">
        <div>
            <p class="eyebrow">{{ $site['about_title'] }}</p>
            <h2 class="heading mt-2">A clear path from assessment to progress</h2>
            <div class="prose-site mt-4 text-slate-600">
                @foreach (preg_split('/\n\s*\n/', $site['about_body']) as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
            </div>
            <a href="{{ route('about') }}" class="btn btn-secondary mt-2">More about C-STAR</a>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ([
                ['clipboard', 'Assessment first', 'Every plan starts with a proper assessment of communication, development and daily skills.'],
                ['users', 'Therapy and training together', 'Therapists and trainers work as one team when a child needs both.'],
                ['heart', 'Parents as partners', 'Home programs and regular updates so practice continues at home.'],
                ['shield', 'Private and safe', "Children's records are confidential and only seen by their care team."],
            ] as [$icon, $title, $text])
                <div class="rounded-3xl bg-slate-50 p-6">
                    <span class="flex size-11 items-center justify-center rounded-2xl bg-white text-brand-700 shadow-sm"><x-icon :name="$icon" class="size-5" /></span>
                    <h3 class="mt-4 font-display font-bold text-slate-900">{{ $title }}</h3>
                    <p class="mt-1 text-sm leading-relaxed text-slate-600">{{ $text }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
