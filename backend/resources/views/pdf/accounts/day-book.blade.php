@include('pdf.partials.styles')
<p class="muted">{{ $branch }} · {{ \Illuminate\Support\Carbon::parse($data['date'])->format('l, d M Y') }}</p>
@forelse($data['entries'] as $e)
    <table class="grid">
        <tr><th colspan="3">{{ $e['voucher_no'] }} — {{ $e['narration'] }}@if($e['status'] === 'reversed') (reversed)@endif</th></tr>
        @foreach($e['lines'] as $l)
            <tr>
                <td style="{{ $l['credit'] ? 'padding-left:18pt' : '' }}">{{ $l['account']['code'] }} {{ $l['account']['name'] }}</td>
                <td style="width:18%; text-align:right">{{ $l['debit'] ? number_format($l['debit'], 2) : '' }}</td>
                <td style="width:18%; text-align:right">{{ $l['credit'] ? number_format($l['credit'], 2) : '' }}</td>
            </tr>
        @endforeach
    </table>
@empty
    <p class="muted">No entries on this day.</p>
@endforelse
