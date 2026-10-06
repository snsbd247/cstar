@include('pdf.partials.styles')
<p class="muted">{{ $branch }} · {{ \Illuminate\Support\Carbon::parse($data['from'])->format('d M Y') }} – {{ \Illuminate\Support\Carbon::parse($data['to'])->format('d M Y') }}</p>
<table class="info" style="width:60%;"><tr><td class="k" style="width:60%">Cash, bank &amp; mobile money at start</td><td style="text-align:right">{{ number_format($data['opening'], 2) }}</td></tr></table>
@foreach(['operating' => 'Operating activities', 'investing' => 'Investing activities (equipment, furniture)', 'financing' => 'Financing activities (owner, loans)'] as $key => $label)
    <h2>{{ $label }}</h2>
    <table class="grid">
        @forelse($data['sections'][$key]['rows'] as $r)
            <tr><td>{{ $r['label'] }}</td><td style="width:25%; text-align:right">{{ number_format($r['amount'], 2) }}</td></tr>
        @empty
            <tr><td colspan="2" class="muted">None</td></tr>
        @endforelse
        <tr><td><b>Net cash from {{ $key }}</b></td><td style="text-align:right"><b>{{ number_format($data['sections'][$key]['total'], 2) }}</b></td></tr>
    </table>
@endforeach
<table class="info" style="width:60%; margin-top:8pt;">
    <tr><td class="k" style="width:60%">Net change</td><td style="text-align:right">{{ number_format($data['net_change'], 2) }}</td></tr>
    <tr><td class="k"><b>Cash, bank &amp; mobile money at end</b></td><td style="text-align:right"><b>{{ number_format($data['closing'], 2) }}</b></td></tr>
</table>
