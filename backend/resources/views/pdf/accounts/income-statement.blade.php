@include('pdf.partials.styles')
<p class="muted">{{ $branch }} · {{ \Illuminate\Support\Carbon::parse($data['from'])->format('d M Y') }} – {{ \Illuminate\Support\Carbon::parse($data['to'])->format('d M Y') }}</p>
@foreach(['income' => 'Income', 'expense' => 'Expenses'] as $key => $label)
    <h2>{{ $label }}</h2>
    <table class="grid">
        @forelse($data[$key]['rows'] as $r)
            <tr><td style="width:12%">{{ $r['account']['code'] }}</td><td>{{ $r['account']['name'] }}</td><td style="width:22%; text-align:right">{{ number_format($r['amount'], 2) }}</td></tr>
        @empty
            <tr><td colspan="3" class="muted">None in this period.</td></tr>
        @endforelse
        <tr><td></td><td><b>Total {{ strtolower($label) }}</b></td><td style="text-align:right"><b>{{ number_format($data[$key]['total'], 2) }}</b></td></tr>
    </table>
@endforeach
<div class="box" style="font-size:12pt;">
    Net {{ $data['net_profit'] >= 0 ? 'profit' : 'loss' }}: <b style="color:{{ $data['net_profit'] >= 0 ? '#047857' : '#b91c1c' }}">Tk {{ number_format(abs($data['net_profit']), 2) }}</b>
</div>
