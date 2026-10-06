import { useQuery } from '@tanstack/react-query'
import { Plus, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { api, errorMessage, validationErrors } from '../../../api/client'
import { Button } from '../../../components/ui/Button'
import { Alert } from '../../../components/ui/Card'
import { Field, Input, Select } from '../../../components/ui/Field'
import { Modal } from '../../../components/ui/Modal'
import { useAuth } from '../../../contexts/useAuth'
import { itemTypeLabel, taka, useBillingMutations, useBillingSettings, type Invoice, type InvoiceItem, type ItemType } from '../api'

type Row = InvoiceItem & { key: number }

const serviceItemType = (category: string): ItemType =>
  category === 'assessment' ? 'assessment' : category === 'consultation' ? 'consultation' : 'therapy_session'

/** Manual invoice (admission, assessment, extra sessions…). Packages are sold from "Sell package". */
export function InvoiceFormModal({
  patient,
  invoice,
  onClose,
  onSaved,
}: {
  patient: { id: number; name: string; home_branch_id?: number | null }
  invoice?: Invoice
  onClose: () => void
  onSaved?: (invoice: Invoice) => void
}) {
  const { user, can } = useAuth()
  const { saveInvoice } = useBillingMutations()
  const { data: settings } = useBillingSettings()
  const { data: services } = useQuery({
    queryKey: ['billable-services'],
    queryFn: async () => (await api.get<{ data: { id: number; name: string; category: string; default_price: string | null }[] }>('/lookups/bookable-services')).data.data,
    enabled: can('appointments.view'),
    staleTime: Infinity,
  })
  const branches = user?.branches ?? []
  const [branchId, setBranchId] = useState<number>(invoice?.branch?.id ?? (patient.home_branch_id && branches.some((b) => b.id === patient.home_branch_id) ? patient.home_branch_id : (branches[0]?.id ?? 0)))
  const [rows, setRows] = useState<Row[]>(() => invoice?.items?.map((i, k) => ({ ...i, key: k })) ?? [])
  const [reason, setReason] = useState(invoice?.discount_reason ?? '')
  const [notes, setNotes] = useState(invoice?.notes ?? '')
  const [error, setError] = useState<string | null>(null)

  const add = (row: Omit<Row, 'key'>) => setRows((r) => [...r, { ...row, key: Date.now() + r.length }])
  const set = (key: number, patch: Partial<Row>) => setRows((r) => r.map((x) => (x.key === key ? { ...x, ...patch } : x)))
  const subtotal = rows.reduce((s, r) => s + r.quantity * r.unit_price, 0)
  const discount = rows.reduce((s, r) => s + (r.discount || 0), 0)

  const save = async (issue: boolean) => {
    setError(null)
    try {
      const saved = await saveInvoice.mutateAsync({
        id: invoice?.id,
        ...(invoice ? {} : { patient_id: patient.id }),
        branch_id: branchId,
        discount_reason: reason || null,
        notes: notes || null,
        items: rows.map((r) => ({ item_type: r.item_type, description: r.description, service_id: r.service_id ?? null, quantity: r.quantity, unit_price: r.unit_price, discount: r.discount || 0 })),
        issue,
      })
      onSaved?.(saved)
      onClose()
    } catch (e) {
      setError(Object.values(validationErrors(e))[0] ?? errorMessage(e))
    }
  }

  return (
    <Modal open wide title={`${invoice ? 'Edit draft' : 'New invoice'} — ${patient.name}`} onClose={onClose}>
      <div className="space-y-4">
        {error && <Alert>{error}</Alert>}

        <div className="flex flex-wrap gap-2">
          {Number(settings?.admission_fee) > 0 && (
            <Button variant="secondary" type="button" onClick={() => add({ item_type: 'admission', description: 'Registration & admission fee', quantity: 1, unit_price: Number(settings?.admission_fee), discount: 0 })}>
              <Plus className="size-4" /> Admission fee
            </Button>
          )}
          <Select
            aria-label="Add a service"
            className="sm:w-64"
            value=""
            onChange={(e) => {
              const s = services?.find((x) => x.id === Number(e.target.value))
              if (s) add({ item_type: serviceItemType(s.category), description: s.name, service_id: s.id, quantity: 1, unit_price: Number(s.default_price ?? 0), discount: 0 })
            }}
          >
            <option value="">+ Add a service…</option>
            {services?.map((s) => (
              <option key={s.id} value={s.id}>
                {s.name}
                {s.default_price ? ` — ${taka(s.default_price)}` : ''}
              </option>
            ))}
          </Select>
          <Button variant="ghost" type="button" onClick={() => add({ item_type: 'other', description: '', quantity: 1, unit_price: 0, discount: 0 })}>
            <Plus className="size-4" /> Other item
          </Button>
        </div>

        {rows.length === 0 ? (
          <p className="rounded-lg bg-slate-50 p-4 text-center text-sm text-slate-500">Add at least one item.</p>
        ) : (
          <div className="space-y-2">
            <div className="hidden grid-cols-[130px_1fr_60px_100px_90px_32px] gap-2 px-1 text-xs font-medium text-slate-500 sm:grid">
              <span>Type</span>
              <span>Description</span>
              <span>Qty</span>
              <span>Rate (৳)</span>
              <span>Discount</span>
              <span />
            </div>
            {rows.map((r) => (
              <div key={r.key} className="grid grid-cols-2 gap-2 rounded-lg border border-slate-100 p-2 sm:grid-cols-[130px_1fr_60px_100px_90px_32px] sm:border-0 sm:p-0">
                <Select aria-label="Type" value={r.item_type} onChange={(e) => set(r.key, { item_type: e.target.value as ItemType })}>
                  {(Object.keys(itemTypeLabel) as ItemType[]).filter((t) => t !== 'opening_balance')
                    .filter((t) => t !== 'package')
                    .map((t) => (
                      <option key={t} value={t}>
                        {itemTypeLabel[t]}
                      </option>
                    ))}
                </Select>
                <Input aria-label="Description" value={r.description} onChange={(e) => set(r.key, { description: e.target.value })} placeholder="Description" />
                <Input aria-label="Quantity" type="number" min={1} value={r.quantity} onChange={(e) => set(r.key, { quantity: Math.max(1, Number(e.target.value)) })} />
                <Input aria-label="Rate" type="number" min={0} value={r.unit_price} onChange={(e) => set(r.key, { unit_price: Number(e.target.value) })} />
                <Input aria-label="Discount" type="number" min={0} disabled={!can('discounts.apply')} value={r.discount} onChange={(e) => set(r.key, { discount: Number(e.target.value) })} />
                <button type="button" aria-label="Remove" onClick={() => setRows((x) => x.filter((y) => y.key !== r.key))} className="justify-self-end p-2 text-slate-400 hover:text-red-600">
                  <Trash2 className="size-4" />
                </button>
              </div>
            ))}
          </div>
        )}

        {discount > 0 && (
          <Field label="Discount reason (required)" htmlFor="discount_reason">
            <Input id="discount_reason" value={reason} onChange={(e) => setReason(e.target.value)} placeholder="e.g. Sibling concession, financial hardship" />
          </Field>
        )}

        <div className="grid gap-3 sm:grid-cols-2">
          <Field label="Branch" htmlFor="inv_branch">
            <Select id="inv_branch" value={branchId} onChange={(e) => setBranchId(Number(e.target.value))}>
              {branches.map((b) => (
                <option key={b.id} value={b.id}>
                  {b.name}
                </option>
              ))}
            </Select>
          </Field>
          <Field label="Note (printed on invoice)" htmlFor="inv_notes">
            <Input id="inv_notes" value={notes} onChange={(e) => setNotes(e.target.value)} />
          </Field>
        </div>

        <div className="flex flex-col gap-3 border-t border-slate-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
          <div className="text-sm">
            <span className="text-slate-500">Total </span>
            <span className="text-lg font-semibold text-slate-900">{taka(subtotal - discount)}</span>
            {discount > 0 && <span className="ml-2 text-xs text-slate-500">({taka(subtotal)} − {taka(discount)} discount)</span>}
          </div>
          <div className="flex gap-2">
            <Button variant="secondary" loading={saveInvoice.isPending} disabled={!rows.length} onClick={() => save(false)}>
              Save draft
            </Button>
            <Button loading={saveInvoice.isPending} disabled={!rows.length} onClick={() => save(true)}>
              Issue invoice
            </Button>
          </div>
        </div>
      </div>
    </Modal>
  )
}
