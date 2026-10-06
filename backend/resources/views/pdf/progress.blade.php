@include('pdf.partials.styles')
<table class="info">
    <tr><td class="k">Child</td><td><b>{{ $patient->name }}</b>@if($patient->name_bn) ({{ $patient->name_bn }})@endif</td><td class="k">Child ID</td><td>{{ $patient->patient_code }}</td></tr>
    <tr><td class="k">Period</td><td>{{ $from->format('d M Y') }} – {{ $to->format('d M Y') }}</td><td class="k">Branch</td><td>{{ $patient->homeBranch?->name }}</td></tr>
</table>

<h2>Programmes</h2>
@forelse($patient->enrollments as $e)
    <p>• <b>{{ $e->summary() }}</b> <span class="muted">— {{ str_replace('_', ' ', $e->status->value) }}, since {{ $e->start_date->format('d M Y') }}</span></p>
@empty
    <p class="muted">No programmes in this period.</p>
@endforelse

<h2>Activity in this period</h2>
<table class="grid">
    <tr><th>Programme</th><th>Sessions / records</th></tr>
    @foreach($sessions as $service => $list)
        <tr><td>{{ $service }} (therapy)</td><td>{{ $list->count() }} sessions</td></tr>
    @endforeach
    @if($records)<tr><td>Regular Training</td><td>{{ $records }} daily records</td></tr>@endif
    @if($sessions->isEmpty() && ! $records)<tr><td colspan="2" class="muted">Nothing recorded in this period.</td></tr>@endif
</table>

@foreach($patient->enrollments as $e)
    @foreach($e->plans as $plan)
        <h2>{{ $plan->title }}</h2>
        <p class="muted">{{ $e->summary() }}@if($plan->start_date) · started {{ $plan->start_date->format('d M Y') }}@endif @if($plan->review_date) · review {{ $plan->review_date->format('d M Y') }}@endif</p>
        <table class="grid">
            <tr><th style="width:22%">Area</th><th>Goal</th><th style="width:18%">Progress</th><th style="width:14%">Status</th></tr>
            @foreach($plan->goals as $g)
                <tr>
                    <td>{{ $g->domain }}</td>
                    <td><b>{{ $g->title }}</b>@if($g->target)<br><span class="muted">{{ $g->target }}</span>@endif</td>
                    <td>
                        {{-- mPDF drops empty cells and ignores div heights inside tables, so the bar is a 1-row table of non-breaking spaces. --}}
                        <table width="100%" style="border-collapse: collapse; margin-top: 2pt;"><tr>
                            <td style="background: #10b981; width: {{ max(1, (int) $g->progress_percent) }}%; border: none; padding: 0; font-size: 4pt;">&nbsp;</td>
                            @if((int) $g->progress_percent < 100)<td style="background: #e2e8f0; border: none; padding: 0; font-size: 4pt;">&nbsp;</td>@endif
                        </tr></table>
                        <span class="muted">{{ (int) $g->progress_percent }}%</span>
                    </td>
                    <td>{{ ucfirst(str_replace('_', ' ', $g->status)) }}</td>
                </tr>
            @endforeach
        </table>
    @endforeach
@endforeach

@if($assessments->isNotEmpty())
    <h2>Assessments in this period</h2>
    @foreach($assessments as $a)
        <p>• <b>{{ $a->type->name }}</b> <span class="muted">({{ $a->date->format('d M Y') }}, {{ $a->assessment_code }})</span></p>
        @if($a->parent_summary)<p style="margin-left: 10pt;">{{ $a->parent_summary }}</p>@endif
    @endforeach
@endif
