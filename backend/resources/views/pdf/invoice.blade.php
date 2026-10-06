@include('pdf.partials.styles')
@php
    $p = $invoice->patient;
    $guardian = $p->guardians->firstWhere('pivot.is_primary', true) ?? $p->guardians->first();
    $tk = fn ($v) => 'Tk '.number_format((float) $v, 2);
@endphp
<table class="info">
    <tr><td class="k">Invoice no.</td><td><b>{{ $invoice->invoice_no ?? 'DRAFT' }}</b></td><td class="k">Status</td><td>{{ ucfirst(str_replace('_', ' ', $invoice->status)) }}</td></tr>
    <tr><td class="k">Child</td><td><b>{{ $p->name }}</b> ({{ $p->patient_code }})</td><td class="k">Issue date</td><td>{{ $invoice->issue_date?->format('d M Y') }}</td></tr>
    <tr><td class="k">Guardian</td><td>{{ $guardian?->name }} {{ $guardian?->phone ? '· '.$guardian->phone : '' }}</td><td class="k">Due date</td><td>{{ $invoice->due_date?->format('d M Y') }}</td></tr>
    <tr><td class="k">Branch</td><td colspan="3">{{ $invoice->branch->name }}</td></tr>
</table>
@if($invoice->status === 'void')<p class="draft">VOID — {{ $invoice->void_reason }}</p>@endif

<table class="grid">
    <tr><th style="width:5%">#</th><th>Description</th><th style="width:8%; text-align:right">Qty</th><th style="width:16%; text-align:right">Rate</th><th style="width:14%; text-align:right">Discount</th><th style="width:16%; text-align:right">Amount</th></tr>
    @foreach($invoice->items as $i => $item)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ $item->description }}</td>
            <td style="text-align:right">{{ $item->quantity }}</td>
            <td style="text-align:right">{{ $tk($item->unit_price) }}</td>
            <td style="text-align:right">{{ (float) $item->discount ? $tk($item->discount) : '—' }}</td>
            <td style="text-align:right">{{ $tk($item->line_total) }}</td>
        </tr>
    @endforeach
</table>

<table width="100%"><tr>
    <td style="width:55%; vertical-align:top;">
        @if($invoice->discount_reason)<p class="muted">Discount: {{ $invoice->discount_reason }}</p>@endif
        @if($invoice->notes)<p class="muted">{{ $invoice->notes }}</p>@endif
        @if($invoice->allocations->isNotEmpty())
            <p style="margin-top:6pt;"><b>Payments</b></p>
            @foreach($invoice->allocations as $a)
                <p class="muted">{{ $a->payment->receipt_no }} · {{ $a->payment->paid_at->format('d M Y') }} · {{ strtoupper($a->payment->method) }} · {{ $tk($a->amount) }}</p>
            @endforeach
        @endif
    </td>
    <td style="width:45%; vertical-align:top;">
        <table class="info">
            <tr><td class="k" style="width:45%">Subtotal</td><td style="text-align:right">{{ $tk($invoice->subtotal) }}</td></tr>
            @if((float) $invoice->discount_total)<tr><td class="k">Discount</td><td style="text-align:right">− {{ $tk($invoice->discount_total) }}</td></tr>@endif
            <tr><td class="k"><b>Total</b></td><td style="text-align:right"><b>{{ $tk($invoice->total) }}</b></td></tr>
            <tr><td class="k">Paid</td><td style="text-align:right">{{ $tk($invoice->paid_total) }}</td></tr>
            <tr><td class="k"><b>Due</b></td><td style="text-align:right; color:{{ (float) $invoice->due_total ? '#b91c1c' : '#047857' }}"><b>{{ $tk($invoice->due_total) }}</b></td></tr>
        </table>
    </td>
</tr></table>

<p class="muted" style="margin-top:14pt;">Pay at the front desk (cash, bKash, Nagad, card or bank). Please quote the invoice number.</p>
