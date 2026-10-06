<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Payment */
class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'receipt_no' => $this->receipt_no,
            'type' => $this->type,
            'amount' => (float) $this->amount,
            'method' => $this->method,
            'transaction_ref' => $this->transaction_ref,
            'paid_at' => $this->paid_at,
            'payer_name' => $this->payer_name,
            'notes' => $this->notes,
            'status' => $this->status,
            'void_reason' => $this->void_reason,
            'patient' => $this->whenLoaded('patient', fn () => $this->patient->only(['id', 'name', 'patient_code'])),
            'branch' => $this->whenLoaded('branch', fn () => $this->branch->only(['id', 'name'])),
            'received_by' => $this->whenLoaded('receiver', fn () => $this->receiver?->only(['id', 'name'])),
            'allocations' => $this->whenLoaded('allocations', fn () => $this->allocations->map(fn ($a) => [
                'invoice_id' => $a->invoice_id,
                'invoice_no' => $a->invoice->invoice_no,
                'amount' => (float) $a->amount,
                'from_advance' => $a->from_advance,
            ])),
            'unallocated' => $this->whenLoaded('allocations', fn () => $this->type === 'payment'
                ? round((float) $this->amount - (float) $this->allocations->sum('amount'), 2) : 0),
        ];
    }
}
