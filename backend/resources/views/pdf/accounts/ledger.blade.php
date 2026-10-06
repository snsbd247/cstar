@include('pdf.partials.styles')
@php($tk = fn ($v) => $v ? number_format((float) $v, 2) : '')
<p class="muted">{{ $branch }} · {{ \Illuminate\Support\Carbon::parse($data['from'])->format('d M Y') }} – {{ \Illuminate\Support\Carbon::parse($data['to'])->format('d M Y') }}</p>
<table class="grid">
    <tr><th style="width:11%">Date</th><th style="width:15%">Voucher</th><th>Particulars</th><th style="width:13%; text-align:right">Debit</th><th style="width:13%; text-align:right">Credit</th><th style="width:14%; text-align:right">Balance</th></tr>
    <tr><td></td><td></td><td><b>Opening balance</b></td><td></td><td></td><td style="text-align:right"><b>{{ number_format($data['opening'], 2) }}</b></td></tr>
    @foreach($data['rows'] as $r)
        <tr>
            <td>{{ \Illuminate\Support\Carbon::parse($r['date'])->format('d/m/y') }}</td>
            <td>{{ $r['voucher_no'] }}</td>
            <td>{{ $r['narration'] }}@if($r['memo'])<br><span class="muted">{{ $r['memo'] }}</span>@endif</td>
            <td style="text-align:right">{{ $tk($r['debit']) }}</td>
            <td style="text-align:right">{{ $tk($r['credit']) }}</td>
            <td style="text-align:right">{{ number_format($r['balance'], 2) }}</td>
        </tr>
    @endforeach
    <tr><td></td><td></td><td><b>Closing balance</b></td><td style="text-align:right"><b>{{ number_format($data['total_debit'], 2) }}</b></td><td style="text-align:right"><b>{{ number_format($data['total_credit'], 2) }}</b></td><td style="text-align:right"><b>{{ number_format($data['closing'], 2) }}</b></td></tr>
</table>
