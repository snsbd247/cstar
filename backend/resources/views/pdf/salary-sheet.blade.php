@include('pdf.partials.styles')
@php($tk = fn ($v) => (float) $v ? number_format((float) $v, 2) : '—')
<p class="muted">{{ $run->run_no }} · {{ $run->label() }} · {{ $run->branch->name }} · {{ ucfirst($run->status) }}@if($run->approver) · approved by {{ $run->approver->name }}@endif</p>
<table class="grid" style="font-size:8pt;">
    <tr>
        <th>#</th><th>Employee</th><th style="text-align:right">Fixed</th><th style="text-align:right">Sessions</th><th style="text-align:right">Bonus / add.</th>
        <th style="text-align:right">Absence</th><th style="text-align:right">Gross</th><th style="text-align:right">Advance</th><th style="text-align:right">Tax / other</th>
        <th style="text-align:right">Net pay</th><th>Signature</th>
    </tr>
    @foreach($run->items->sortBy(fn ($i) => [$i->department, $i->employee->name])->values() as $n => $i)
        <tr>
            <td>{{ $n + 1 }}</td>
            <td>{{ $i->employee->name }}<br><span class="muted">{{ $i->employee->employee_code }} · {{ $i->employee->designation }}</span></td>
            <td style="text-align:right">{{ $tk($i->fixed_amount) }}</td>
            <td style="text-align:right">{{ $tk($i->session_pay) }}@if($i->session_count)<br><span class="muted">{{ $i->session_count }} sess.</span>@endif</td>
            <td style="text-align:right">{{ $tk((float) $i->bonus + (float) $i->other_addition) }}</td>
            <td style="text-align:right">{{ $tk($i->absence_deduction) }}</td>
            <td style="text-align:right">{{ $tk($i->gross) }}</td>
            <td style="text-align:right">{{ $tk($i->advance_deduction) }}</td>
            <td style="text-align:right">{{ $tk((float) $i->tax + (float) $i->other_deduction) }}</td>
            <td style="text-align:right"><b>{{ number_format((float) $i->net_pay, 2) }}</b></td>
            <td style="width:12%"></td>
        </tr>
    @endforeach
    <tr>
        <td></td><td><b>Total ({{ $run->items->count() }})</b></td>
        <td style="text-align:right">{{ $tk($run->items->sum('fixed_amount')) }}</td>
        <td style="text-align:right">{{ $tk($run->items->sum('session_pay')) }}</td>
        <td style="text-align:right">{{ $tk($run->items->sum('bonus') + $run->items->sum('other_addition')) }}</td>
        <td style="text-align:right">{{ $tk($run->items->sum('absence_deduction')) }}</td>
        <td style="text-align:right"><b>{{ number_format((float) $run->total_gross, 2) }}</b></td>
        <td style="text-align:right">{{ $tk($run->items->sum('advance_deduction')) }}</td>
        <td style="text-align:right">{{ $tk($run->items->sum('tax') + $run->items->sum('other_deduction')) }}</td>
        <td style="text-align:right"><b>{{ number_format((float) $run->total_net, 2) }}</b></td>
        <td></td>
    </tr>
</table>
<table width="100%" style="margin-top:30pt;"><tr>
    <td style="width:30%; border-top:0.5pt solid #94a3b8; font-size:9pt; padding-top:3pt;">Prepared by{{ $run->preparer ? ': '.$run->preparer->name : '' }}</td>
    <td style="width:5%"></td>
    <td style="width:30%; border-top:0.5pt solid #94a3b8; font-size:9pt; padding-top:3pt;">Approved by{{ $run->approver ? ': '.$run->approver->name : '' }}</td>
    <td style="width:5%"></td>
    <td style="width:30%; border-top:0.5pt solid #94a3b8; font-size:9pt; padding-top:3pt;">Received for payment</td>
</tr></table>
