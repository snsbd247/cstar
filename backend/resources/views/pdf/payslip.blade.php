@include('pdf.partials.styles')
@php
    $e = $item->employee;
    $run = $item->run;
    $b = $item->breakdown ?? [];
    $tk = fn ($v) => number_format((float) $v, 2);
@endphp
<table class="info">
    <tr><td class="k">Employee</td><td><b>{{ $e->name }}</b> ({{ $e->employee_code }})</td><td class="k">Pay period</td><td>{{ $run->label() }}</td></tr>
    <tr><td class="k">Designation</td><td>{{ $e->designation }}</td><td class="k">Branch</td><td>{{ $run->branch->name }}</td></tr>
    <tr><td class="k">Paid by</td><td>{{ ucfirst($e->payment_method) }} {{ $e->bank_account ?? $e->mfs_number }}</td><td class="k">Status</td><td>{{ $item->paid_at ? 'Paid '.$item->paid_at->format('d M Y') : 'Approved, not yet paid' }}</td></tr>
</table>

<table width="100%"><tr>
    <td style="width:50%; vertical-align:top; padding-right:6pt;">
        <table class="grid">
            <tr><th>Earnings</th><th style="text-align:right">Tk</th></tr>
            @foreach($b['allowances'] ?? [] as $a)
                <tr><td>{{ $a['name'] }}</td><td style="text-align:right">{{ $tk($a['amount']) }}</td></tr>
            @endforeach
            @foreach($b['sessions'] ?? [] as $s)
                <tr><td>{{ $s['label'] }} — {{ $s['count'] }} sessions @isset($s['rate'])× {{ $tk($s['rate']) }}@endisset</td><td style="text-align:right">{{ $tk($s['amount']) }}</td></tr>
            @endforeach
            @if((float) $item->bonus)<tr><td>Bonus</td><td style="text-align:right">{{ $tk($item->bonus) }}</td></tr>@endif
            @if((float) $item->other_addition)<tr><td>Other addition</td><td style="text-align:right">{{ $tk($item->other_addition) }}</td></tr>@endif
            @if((float) $item->absence_deduction)<tr><td>Less: absence</td><td style="text-align:right">− {{ $tk($item->absence_deduction) }}</td></tr>@endif
            <tr><td><b>Gross pay</b></td><td style="text-align:right"><b>{{ $tk($item->gross) }}</b></td></tr>
        </table>
        @if(isset($b['prorated_days']))<p class="muted">Paid for {{ $b['prorated_days'] }} days of the month.</p>@endif
        @if($item->session_count)<p class="muted">Finalized therapy sessions this month: {{ $item->session_count }}</p>@endif
    </td>
    <td style="width:50%; vertical-align:top; padding-left:6pt;">
        <table class="grid">
            <tr><th>Deductions</th><th style="text-align:right">Tk</th></tr>
            <tr><td>Advance instalment</td><td style="text-align:right">{{ $tk($item->advance_deduction) }}</td></tr>
            <tr><td>Tax (TDS)</td><td style="text-align:right">{{ $tk($item->tax) }}</td></tr>
            <tr><td>Other</td><td style="text-align:right">{{ $tk($item->other_deduction) }}</td></tr>
            <tr><td><b>Total deductions</b></td><td style="text-align:right"><b>{{ $tk((float) $item->advance_deduction + (float) $item->tax + (float) $item->other_deduction) }}</b></td></tr>
        </table>
    </td>
</tr></table>

<div class="box" style="font-size:13pt; text-align:center;">Net pay: <b>Tk {{ $tk($item->net_pay) }}</b></div>
@if($item->note)<p class="muted">Note: {{ $item->note }}</p>@endif

<table width="100%" style="margin-top:36pt;"><tr>
    <td style="width:45%; border-top:0.5pt solid #94a3b8; font-size:9pt; padding-top:3pt;">Employee signature</td>
    <td style="width:10%"></td>
    <td style="width:45%; border-top:0.5pt solid #94a3b8; font-size:9pt; padding-top:3pt;">Authorised by</td>
</tr></table>
