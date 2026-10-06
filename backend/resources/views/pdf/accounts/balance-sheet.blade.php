@include('pdf.partials.styles')
<p class="muted">{{ $branch }} · as of {{ \Illuminate\Support\Carbon::parse($data['as_of'])->format('d M Y') }}</p>
@foreach(['assets' => 'Assets', 'liabilities' => 'Liabilities', 'equity' => 'Equity'] as $key => $label)
    <h2>{{ $label }}</h2>
    <table class="grid">
        @foreach($data[$key]['rows'] as $r)
            <tr><td style="width:12%">{{ $r['account']['code'] }}</td><td>{{ $r['account']['name'] }}</td><td style="width:22%; text-align:right">{{ number_format($r['amount'], 2) }}</td></tr>
        @endforeach
        @if($key === 'equity')
            <tr><td></td><td>Profit / (loss) to date</td><td style="text-align:right">{{ number_format($data['equity']['profit_to_date'], 2) }}</td></tr>
        @endif
        <tr><td></td><td><b>Total {{ strtolower($label) }}</b></td><td style="text-align:right"><b>{{ number_format($data[$key]['total'], 2) }}</b></td></tr>
    </table>
@endforeach
<table class="info" style="margin-top:8pt;">
    <tr><td class="k" style="width:60%">Total assets</td><td style="text-align:right"><b>Tk {{ number_format($data['assets']['total'], 2) }}</b></td></tr>
    <tr><td class="k">Total liabilities + equity</td><td style="text-align:right"><b>Tk {{ number_format($data['liabilities_and_equity'], 2) }}</b></td></tr>
</table>
<p class="muted">{{ $data['balanced'] ? 'Assets equal liabilities plus equity.' : 'WARNING: the balance sheet does not balance.' }}</p>
