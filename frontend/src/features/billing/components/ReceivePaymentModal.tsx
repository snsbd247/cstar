import { CheckCircle2, FileDown } from 'lucide-react'
import { useMemo, useState } from 'react'
import { errorMessage, validationErrors } from '../../../api/client'
import { Button } from '../../../components/ui/Button'
import { Alert } from '../../../components/ui/Card'
import { Field, Input, Select } from '../../../components/ui/Field'
import { Modal } from '../../../components/ui/Modal'
import { Spinner } from '../../../components/ui/Spinner'
import { useAuth } from '../../../contexts/useAuth'
import { cn } from '../../../utils/cn'
import { methodLabel, receiptPdfUrl, taka, useBillingAccount, useBillingMutations, type Payment, type PaymentMethod } from '../api'

/**
 * Plan §১৯ payment: amount + method (+ transaction ID for non-cash), paid against the open invoices
 * oldest first; anything extra is kept as advance. Ends with a printable receipt.
 */
export function ReceivePaymentModal({ patient, onClose }: { patient: { id: number; name: string; home_branch_id?: number | null }; onClose: () => void }) {
  const { user } = useAuth()
  const { data: account, isLoading } = useBillingAccount(patient.id)
  const { pay } = useBillingMutations()
  const open = useMemo(() => account?.invoices.filter((i) => ['issued', 'partially_paid'].includes(i.status)).reverse() ?? [], [account])
  const [selected, setSelected] = useState<number[] | null>(null)
  const chosen = selected ?? open.map((i) => i.id)
  const dueOfChosen = open.filter((i) => chosen.includes(i.id)).reduce((s, i) => s + i.due_total, 0)

  const [amount, setAmount] = useState<string>('')
  const [method, setMethod] = useState<PaymentMethod>('cash')
  const [ref, setRef] = useState('')
  const [payer, setPayer] = useState('')
  const branches = user?.branches ?? []
  const [branchId, setBranchId] = useState<number>(patient.home_branch_id && branches.some((b) => b.id === patient.home_branch_id) ? patient.home_branch_id : (branches[0]?.id ?? 0))
  const [error, setError] = useState<string | null>(null)
  const [done, setDone] = useState<Payment | null>(null)

  const value = amount === '' ? dueOfChosen : Number(amount)
  // Preview: oldest chosen invoice first, the rest is advance.
  const preview = useMemo(
    () =>
      open
        .filter((i) => chosen.includes(i.id))
        .reduce<{ invoice: (typeof open)[number]; part: number }[]>((rows, i) => {
          const used = rows.reduce((s, r) => s + r.part, 0)
          return [...rows, { invoice: i, part: Math.max(0, Math.min(value - used, i.due_total)) }]
        }, []),
    [open, chosen, value],
  )
  const advance = Math.max(0, value - preview.reduce((s, p) => s + p.part, 0))

  const submit = async () => {
    setError(null)
    try {
      const payment = await pay.mutateAsync({
        patientId: patient.id,
        amount: value,
        method,
        transaction_ref: ref || null,
        payer_name: payer || null,
        branch_id: branchId,
        allocations: preview.filter((p) => p.part > 0).map((p) => ({ invoice_id: p.invoice.id, amount: Math.round(p.part * 100) / 100 })),
      })
      setDone(payment)
    } catch (e) {
      setError(Object.values(validationErrors(e))[0] ?? errorMessage(e))
    }
  }

  if (done) {
    return (
      <Modal open title="Payment received" onClose={onClose}>
        <div className="space-y-4 text-center">
          <CheckCircle2 className="mx-auto size-12 text-brand-600" />
          <p className="text-2xl font-semibold text-slate-900">{taka(done.amount)}</p>
          <p className="text-sm text-slate-500">
            {done.receipt_no} · {methodLabel[done.method]} · {patient.name}
          </p>
          {!!done.unallocated && <p className="text-sm text-sky-brand-700">{taka(done.unallocated)} kept as advance.</p>}
          <div className="flex justify-center gap-2">
            <a href={receiptPdfUrl(done.id)} target="_blank" rel="noreferrer">
              <Button>
                <FileDown className="size-4" /> Print receipt
              </Button>
            </a>
            <Button variant="secondary" onClick={onClose}>
              Done
            </Button>
          </div>
        </div>
      </Modal>
    )
  }

  return (
    <Modal open title={`Receive payment — ${patient.name}`} onClose={onClose}>
      {isLoading || !account ? (
        <Spinner className="text-brand-600" />
      ) : (
        <div className="space-y-4">
          {error && <Alert>{error}</Alert>}
          {open.length > 0 ? (
            <div>
              <p className="mb-2 text-sm font-medium text-slate-700">Pay invoices</p>
              <ul className="divide-y divide-slate-100 rounded-lg border border-slate-200">
                {open.map((i) => {
                  const part = preview.find((p) => p.invoice.id === i.id)?.part ?? 0
                  return (
                    <li key={i.id} className="flex items-center gap-3 px-3 py-2 text-sm">
                      <input
                        type="checkbox"
                        className="size-4 accent-brand-600"
                        aria-label={i.invoice_no ?? ''}
                        checked={chosen.includes(i.id)}
                        onChange={(e) => setSelected(e.target.checked ? [...chosen, i.id] : chosen.filter((x) => x !== i.id))}
                      />
                      <span className="flex-1">
                        <span className="font-medium text-slate-800">{i.invoice_no}</span>
                        <span className="block text-xs text-slate-500">
                          {i.items?.map((x) => x.description).join(', ')}
                        </span>
                      </span>
                      <span className="text-right">
                        <span className="block text-slate-800">{taka(i.due_total)}</span>
                        {chosen.includes(i.id) && part < i.due_total && <span className="text-xs text-amber-700">paying {taka(part)}</span>}
                      </span>
                    </li>
                  )
                })}
              </ul>
            </div>
          ) : (
            <p className="rounded-lg bg-slate-50 p-3 text-sm text-slate-600">No unpaid invoices — the whole amount will be kept as advance.</p>
          )}

          <div className="grid gap-3 sm:grid-cols-2">
            <Field label="Amount (৳)" htmlFor="pay_amount">
              <Input id="pay_amount" type="number" min={1} inputMode="decimal" value={amount === '' ? String(dueOfChosen || '') : amount} onChange={(e) => setAmount(e.target.value)} />
            </Field>
            <Field label="Branch" htmlFor="pay_branch">
              <Select id="pay_branch" value={branchId} onChange={(e) => setBranchId(Number(e.target.value))}>
                {branches.map((b) => (
                  <option key={b.id} value={b.id}>
                    {b.name}
                  </option>
                ))}
              </Select>
            </Field>
          </div>

          <div role="radiogroup" aria-label="Method" className="grid grid-cols-5 gap-1.5">
            {(Object.keys(methodLabel) as PaymentMethod[]).filter((m) => m !== 'online').map((m) => (
              <button
                key={m}
                type="button"
                role="radio"
                aria-checked={method === m}
                onClick={() => setMethod(m)}
                className={cn('min-h-10 rounded-lg border text-sm font-medium', method === m ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-slate-200 text-slate-600')}
              >
                {methodLabel[m]}
              </button>
            ))}
          </div>
          {method !== 'cash' && (
            <Field label="Transaction ID" htmlFor="pay_ref">
              <Input id="pay_ref" value={ref} onChange={(e) => setRef(e.target.value)} placeholder="e.g. 8F3K2L9Q" />
            </Field>
          )}
          <Field label="Paid by (optional)" htmlFor="pay_payer">
            <Input id="pay_payer" value={payer} onChange={(e) => setPayer(e.target.value)} placeholder="Guardian name" />
          </Field>

          <div className="rounded-lg bg-slate-50 p-3 text-sm">
            <div className="flex justify-between">
              <span className="text-slate-500">Against invoices</span>
              <span>{taka(value - advance)}</span>
            </div>
            {advance > 0 && (
              <div className="flex justify-between text-sky-brand-700">
                <span>Kept as advance</span>
                <span>{taka(advance)}</span>
              </div>
            )}
            {account.advance > 0 && <p className="mt-1 text-xs text-slate-500">Existing advance: {taka(account.advance)} (used automatically on new invoices)</p>}
          </div>

          <div className="flex justify-end gap-2">
            <Button variant="secondary" onClick={onClose}>
              Cancel
            </Button>
            <Button loading={pay.isPending} disabled={!value || value <= 0 || !branchId} onClick={submit}>
              Receive {value > 0 ? taka(value) : ''}
            </Button>
          </div>
        </div>
      )}
    </Modal>
  )
}
