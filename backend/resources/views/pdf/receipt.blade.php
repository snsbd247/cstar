@include('pdf.partials.styles')
@php
    $tk = fn ($v) => 'Tk '.number_format((float) $v, 2);
    $methods = ['cash' => 'Cash', 'bkash' => 'bKash', 'nagad' => 'Nagad', 'bank' => 'Bank transfer', 'card' => 'Card'];
    $unallocated = $payment->type === 'payment' ? round((float) $payment->amount - (float) $payment->allocations->sum('amount'), 2) : 0;
@endphp
<table class="info">
    <tr><td class="k">{{ $payment->type === 'refund' ? 'Refund no.' : 'Receipt no.' }}</td><td><b>{{ $payment->receipt_no }}</b></td><td class="k">Date</td><td>{{ $payment->paid_at->format('d M Y, h:i A') }}</td></tr>
    <tr><td class="k">Child</td><td><b>{{ $payment->patient->name }}</b> ({{ $payment->patient->patient_code }})</td><td class="k">Branch</td><td>{{ $payment->branch->name }}</td></tr>
    <tr><td class="k">{{ $payment->type === 'refund' ? 'Paid to' : 'Received from' }}</td><td>{{ $payment->payer_name ?: 'Guardian' }}</td><td class="k">Method</td><td>{{ $methods[$payment->method] ?? $payment->method }}@if($payment->transaction_ref) · {{ $payment->transaction_ref }}@endif</td></tr>
</table>
@if($payment->status === 'void')<p class="draft">VOID — {{ $payment->void_reason }}</p>@endif

<div class="box" style="text-align:center; font-size:14pt; margin:10pt 0;">
    {{ $payment->type === 'refund' ? 'Amount refunded' : 'Amount received' }}: <b>{{ $tk($payment->amount) }}</b>
</div>

@if($payment->allocations->isNotEmpty())
    <table class="grid">
        <tr><th>Paid against invoice</th><th style="text-align:right; width:30%">Amount</th></tr>
        @foreach($payment->allocations as $a)
            <tr><td>{{ $a->invoice->invoice_no }}@if($a->from_advance) <span class="muted">(from advance)</span>@endif</td><td style="text-align:right">{{ $tk($a->amount) }}</td></tr>
        @endforeach
        @if($unallocated > 0)<tr><td>Kept as advance</td><td style="text-align:right">{{ $tk($unallocated) }}</td></tr>@endif
    </table>
@elseif($payment->type === 'payment')
    <p>Kept as advance for future invoices.</p>
@else
    <p>Reason: {{ $payment->notes }}</p>
@endif

<table class="info" style="width:60%; margin-top:8pt;">
    <tr><td class="k" style="width:55%">Total due now</td><td style="text-align:right">{{ $tk($due) }}</td></tr>
    <tr><td class="k" style="width:55%">Advance balance</td><td style="text-align:right">{{ $tk($advance) }}</td></tr>
</table>

<div class="sign">Received by: <b>{{ $payment->receiver?->name }}</b></div>
<p class="muted" style="margin-top:10pt;">This is a computer-generated receipt. ধন্যবাদ।</p>
