@include('pdf.partials.styles')
@php
    $p = $a->patient;
    $findings = collect($a->type->sections)->filter(fn ($s) => filled($a->section_findings[$s['key']] ?? null));
@endphp
<table class="info">
    <tr><td class="k">Child</td><td><b>{{ $p->name }}</b>@if($p->name_bn) ({{ $p->name_bn }})@endif</td><td class="k">Child ID</td><td>{{ $p->patient_code }}</td></tr>
    <tr>
        <td class="k">Date of birth</td>
        <td>@if($p->date_of_birth){{ $p->date_of_birth->format('d M Y') }} <span class="muted">(age {{ $p->date_of_birth->diff($a->date)->format('%y y %m m') }})</span>@endif</td>
        <td class="k">Gender</td><td>{{ ucfirst((string) $p->gender) }}</td>
    </tr>
    <tr><td class="k">Assessment</td><td>{{ $a->type->name }}</td><td class="k">Report no.</td><td>{{ $a->assessment_code }}</td></tr>
    <tr><td class="k">Date</td><td>{{ $a->date->format('d M Y') }}</td><td class="k">Assessed by</td><td>{{ $a->therapist->name }}</td></tr>
    <tr><td class="k">Guardian</td><td colspan="3">{{ $p->guardians->map(fn ($g) => $g->name.' ('.$g->pivot->relationship.')')->join(', ') }}</td></tr>
</table>
@if(! $a->isFinal())<p class="draft">DRAFT — not yet finalized by the therapist.</p>@endif

@if($a->chief_complaint)<h2>Reason for assessment</h2><p>{!! nl2br(e($a->chief_complaint)) !!}</p>@endif
@if($a->background)<h2>Background &amp; history</h2><p>{!! nl2br(e($a->background)) !!}</p>@endif

@if($findings->isNotEmpty())
    <h2>Findings</h2>
    @foreach($findings as $s)
        <h3>{{ $s['label'] }}</h3>
        <p>{!! nl2br(e($a->section_findings[$s['key']])) !!}</p>
    @endforeach
@endif

@if($a->summary)<h2>Summary</h2><div class="box">{!! nl2br(e($a->summary)) !!}</div>@endif

@if($a->recommendationItems->isNotEmpty() || $a->recommendations)
    <h2>Recommendations</h2>
    @if($a->recommendationItems->isNotEmpty())
        <table class="grid">
            <tr><th>Programme</th><th>Frequency</th><th>Priority</th><th>Note</th></tr>
            @foreach($a->recommendationItems as $r)
                <tr>
                    <td>{{ $r->enrollment_type === 'training' ? 'Regular Training' : ($r->service?->name ?? 'Therapy') }}</td>
                    <td>{{ $r->frequency }}</td>
                    <td>{{ ucfirst($r->priority) }}</td>
                    <td>{{ $r->note }}</td>
                </tr>
            @endforeach
        </table>
    @endif
    @if($a->recommendations)<p>{!! nl2br(e($a->recommendations)) !!}</p>@endif
@endif

@if($a->parent_summary)<h2>For the family</h2><div class="box">{!! nl2br(e($a->parent_summary)) !!}</div>@endif

<div class="sign"><b>{{ $a->therapist->name }}</b><br><span class="muted">{{ $a->branch?->name }}</span></div>
