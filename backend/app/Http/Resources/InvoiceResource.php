<?php

namespace App\Http\Resources;

use App\Enums\Permission;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Invoice */
class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'invoice_no' => $this->invoice_no,
            'status' => $this->status,
            'issue_date' => $this->issue_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'subtotal' => (float) $this->subtotal,
            'discount_total' => (float) $this->discount_total,
            'total' => (float) $this->total,
            'paid_total' => (float) $this->paid_total,
            'due_total' => (float) $this->due_total,
            'discount_reason' => $this->discount_reason,
            'notes' => $this->notes,
            'void_reason' => $this->void_reason,
            'is_overdue' => in_array($this->status, Invoice::OPEN, true) && $this->due_date?->isPast(),
            'created_at' => $this->created_at,
            'patient' => $this->whenLoaded('patient', fn () => $this->patient->only(['id', 'name', 'patient_code', 'phone'])),
            'branch' => $this->whenLoaded('branch', fn () => $this->branch->only(['id', 'name'])),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'id' => $i->id,
                'item_type' => $i->item_type,
                'description' => $i->description,
                'service_id' => $i->service_id,
                'package_id' => $i->package_id,
                'enrollment_id' => $i->enrollment_id,
                'billing_period' => $i->billing_period,
                'quantity' => $i->quantity,
                'unit_price' => (float) $i->unit_price,
                'discount' => (float) $i->discount,
                'line_total' => (float) $i->line_total,
            ])),
            'payments' => $this->whenLoaded('allocations', fn () => $this->allocations->map(fn ($a) => [
                'amount' => (float) $a->amount,
                'from_advance' => $a->from_advance,
                'receipt_no' => $a->payment->receipt_no,
                'payment_id' => $a->payment_id,
                'method' => $a->payment->method,
                'paid_at' => $a->payment->paid_at,
            ])),
            'can' => [
                'edit' => $this->status === 'draft' && $user?->can(Permission::INVOICES_MANAGE),
                'void' => ! in_array($this->status, ['draft', 'void'], true) && $user?->can(Permission::INVOICES_VOID),
                'pay' => in_array($this->status, Invoice::OPEN, true) && $user?->can(Permission::PAYMENTS_CREATE),
            ],
        ];
    }
}
