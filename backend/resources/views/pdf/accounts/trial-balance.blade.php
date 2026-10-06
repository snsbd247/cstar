@include('pdf.partials.styles')
@php($tk = fn ($v) => $v ? number_format((float) $v, 2) : '')
<p class="muted">{{ $branch }} · as of {{ \Illuminate\Support\Carbon::parse($data['to'])->format('d M Y') }}</p>
<table class="grid">
    <tr><th style="width:12%">Code</th><th>Account</th><th style="width:20%; text-align:right">Debit (Tk)</th><th style="width:20%; text-align:right">Credit (Tk)</th></tr>
    @foreach($data['rows'] as $r)
        <tr><td>{{ $r['account']['code'] }}</td><td>{{ $r['account']['name'] }}</td><td style="text-align:right">{{ $tk($r['debit']) }}</td><td style="text-align:right">{{ $tk($r['credit']) }}</td></tr>
    @endforeach
    <tr><td></td><td><b>Total</b></td><td style="text-align:right"><b>{{ number_format($data['total_debit'], 2) }}</b></td><td style="text-align:right"><b>{{ number_format($data['total_credit'], 2) }}</b></td></tr>
</table>
<p class="muted">{{ abs($data['total_debit'] - $data['total_credit']) < 0.01 ? 'Debit equals credit — the books balance.' : 'WARNING: debit and credit do not match.' }}</p>
