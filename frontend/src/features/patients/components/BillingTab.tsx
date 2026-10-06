import { FileDown, Package, Plus, Undo2, Wallet } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router'
import { errorMessage } from '../../../api/client'
import { Button } from '../../../components/ui/Button'
import { Alert, Badge, Card } from '../../../components/ui/Card'
import { Field, Input, Select } from '../../../components/ui/Field'
import { Modal } from '../../../components/ui/Modal'
import { Spinner } from '../../../components/ui/Spinner'
import { useAuth } from '../../../contexts/useAuth'
import { invoiceStatusStyle, methodLabel, receiptPdfUrl, taka, useBillingAccount, useBillingMutations, type PaymentMethod } from '../../billing/api'
import { InvoiceFormModal } from '../../billing/components/InvoiceFormModal'
import { SellPackageModal } from '../../billing/components/SellPackageModal'
import type { PatientDetail } from '../types'

/** The child's account: due, advance, packages, invoices and payments (Plan §১৯). */
export function BillingTab({ patient, onPay }: { patient: PatientDetail; onPay: () => void }) {
  const { can, user } = useAuth()
  const { data, isLoading } = useBillingAccount(patient.id)
  const [modal, setModal] = useState<'invoice' | 'package' | 'refund' | null>(null)
  const who = { id: patient.id, name: patient.name, home_branch_id: patient.home_branch?.id }
  const therapy = patient.enrollments
    .filter((e) => e.type === 'therapy' && ['pending', 'active', 'on_hold'].includes(e.status) && e.therapy)
    .map((e) => ({ id: e.id, service_id: e.therapy!.service.id, label: `${e.therapy!.service.name} (${e.enrollment_code})` }))

  if (isLoading || !data) return <Spinner className="text-brand-600" />

  return (
    <div className="space-y-5">
      <div className="grid gap-3 sm:grid-cols-3">
        <Card className="p-4">
          <p className="text-sm text-slate-500">Due</p>
          <p className={`text-2xl font-semibold ${data.due > 0 ? 'text-red-600' : 'text-slate-900'}`}>{taka(data.due)}</p>
        </Card>
        <Card className="p-4">
          <p className="text-sm text-slate-500">Advance</p>
          <p className="text-2xl font-semibold text-sky-brand-700">{taka(data.advance)}</p>
        </Card>
        <Card className="flex flex-wrap items-center gap-2 p-4">
          {can('payments.create') && (
            <Button onClick={onPay}>
              <Wallet className="size-4" /> Receive payment
            </Button>
          )}
          {can('invoices.manage') && (
            <>
              <Button variant="secondary" onClick={() => setModal('invoice')}>
                <Plus className="size-4" /> Invoice
              </Button>
              <Button variant="secondary" onClick={() => setModal('package')}>
                <Package className="size-4" /> Sell package
              </Button>
            </>
          )}
          {can('invoices.void') && data.advance > 0 && (
            <Button variant="ghost" onClick={() => setModal('refund')}>
              <Undo2 className="size-4" /> Refund
            </Button>
          )}
        </Card>
      </div>

      {data.packages.length > 0 && (
        <Card className="p-5">
          <h3 className="font-semibold text-slate-900">Packages</h3>
          <ul className="mt-3 space-y-3">
            {data.packages.map((p) => (
              <li key={p.id} className="rounded-lg border border-slate-100 p-3">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <p className="font-medium text-slate-900">{p.name}</p>
                  <Badge tone={p.status === 'active' ? (p.remaining <= 2 ? 'amber' : 'green') : 'gray'}>{p.status}</Badge>
                </div>
                <div className="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
                  <div className="h-full rounded-full bg-brand-500" style={{ width: `${(p.used_sessions / p.total_sessions) * 100}%` }} />
                </div>
                <p className="mt-1 text-xs text-slate-500">
                  {p.used_sessions} of {p.total_sessions} used · <b className="text-slate-700">{p.remaining} left</b> · expires {p.expiry_date}
                  {p.status === 'active' && p.remaining <= 2 && ' · renewal due'}
                </p>
              </li>
            ))}
          </ul>
        </Card>
      )}

      <Card className="p-5">
        <h3 className="font-semibold text-slate-900">Invoices</h3>
        {data.invoices.length === 0 ? (
          <p className="mt-3 text-sm text-slate-500">No invoices yet.</p>
        ) : (
          <ul className="mt-3 divide-y divide-slate-100">
            {data.invoices.map((i) => (
              <li key={i.id}>
                <Link to={`/app/invoices/${i.id}`} className="flex flex-wrap items-center justify-between gap-2 py-3 hover:bg-slate-50">
                  <div className="min-w-0">
                    <p className="font-medium text-slate-900">
                      {i.invoice_no ?? 'Draft'} <span className="text-xs font-normal text-slate-500">· {i.issue_date ?? 'not issued'}</span>
                    </p>
                    <p className="truncate text-xs text-slate-500">{i.items?.map((x) => x.description).join(', ')}</p>
                  </div>
                  <div className="flex items-center gap-3 text-sm">
                    <span className="text-slate-800">{taka(i.total)}</span>
                    {i.due_total > 0 && i.status !== 'void' && <span className="text-red-600">due {taka(i.due_total)}</span>}
                    <Badge tone={invoiceStatusStyle[i.status].tone}>{invoiceStatusStyle[i.status].label}</Badge>
                  </div>
                </Link>
              </li>
            ))}
          </ul>
        )}
      </Card>

      <Card className="p-5">
        <h3 className="font-semibold text-slate-900">Payments</h3>
        {data.payments.length === 0 ? (
          <p className="mt-3 text-sm text-slate-500">No payments yet.</p>
        ) : (
          <ul className="mt-3 divide-y divide-slate-100">
            {data.payments.map((p) => (
              <li key={p.id} className="flex flex-wrap items-center justify-between gap-2 py-3 text-sm">
                <div>
                  <p className="font-medium text-slate-900">
                    {p.type === 'refund' ? 'Refund ' : ''}
                    {p.receipt_no} {p.status === 'void' && <Badge tone="gray">void</Badge>}
                  </p>
                  <p className="text-xs text-slate-500">
                    {new Date(p.paid_at).toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short' })} · {methodLabel[p.method]}
                    {p.transaction_ref && ` · ${p.transaction_ref}`} · {p.received_by?.name}
                  </p>
                </div>
                <div className="flex items-center gap-3">
                  <span className={p.type === 'refund' ? 'text-red-600' : 'font-medium text-brand-700'}>
                    {p.type === 'refund' ? '−' : '+'}
                    {taka(p.amount)}
                  </span>
                  <a href={receiptPdfUrl(p.id)} target="_blank" rel="noreferrer" className="text-slate-400 hover:text-slate-700" aria-label="Receipt PDF">
                    <FileDown className="size-4" />
                  </a>
                </div>
              </li>
            ))}
          </ul>
        )}
      </Card>

      {modal === 'invoice' && <InvoiceFormModal patient={who} onClose={() => setModal(null)} />}
      {modal === 'package' && <SellPackageModal patient={who} therapyEnrollments={therapy} onClose={() => setModal(null)} />}
      {modal === 'refund' && <RefundModal patientId={patient.id} advance={data.advance} branches={user?.branches ?? []} onClose={() => setModal(null)} />}
    </div>
  )
}

function RefundModal({ patientId, advance, branches, onClose }: { patientId: number; advance: number; branches: { id: number; name: string }[]; onClose: () => void }) {
  const { refund } = useBillingMutations()
  const [amount, setAmount] = useState(String(advance))
  const [method, setMethod] = useState<PaymentMethod>('cash')
  const [reason, setReason] = useState('')
  const [branchId, setBranchId] = useState(branches[0]?.id ?? 0)

  return (
    <Modal open title="Refund advance" onClose={onClose}>
      <div className="space-y-4">
        {refund.isError && <Alert>{errorMessage(refund.error)}</Alert>}
        <p className="text-sm text-slate-600">Advance available: {taka(advance)}</p>
        <div className="grid gap-3 sm:grid-cols-2">
          <Field label="Amount (৳)" htmlFor="rf_amount">
            <Input id="rf_amount" type="number" max={advance} value={amount} onChange={(e) => setAmount(e.target.value)} />
          </Field>
          <Field label="Paid back by" htmlFor="rf_method">
            <Select id="rf_method" value={method} onChange={(e) => setMethod(e.target.value as PaymentMethod)}>
              {(Object.keys(methodLabel) as PaymentMethod[]).map((m) => (
                <option key={m} value={m}>
                  {methodLabel[m]}
                </option>
              ))}
            </Select>
          </Field>
          <Field label="Branch" htmlFor="rf_branch">
            <Select id="rf_branch" value={branchId} onChange={(e) => setBranchId(Number(e.target.value))}>
              {branches.map((b) => (
                <option key={b.id} value={b.id}>
                  {b.name}
                </option>
              ))}
            </Select>
          </Field>
        </div>
        <Field label="Reason" htmlFor="rf_reason">
          <Input id="rf_reason" value={reason} onChange={(e) => setReason(e.target.value)} />
        </Field>
        <div className="flex justify-end gap-2">
          <Button variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button
            variant="danger"
            loading={refund.isPending}
            disabled={!reason || !Number(amount)}
            onClick={() => refund.mutate({ patientId, amount: Number(amount), method, reason, branch_id: branchId }, { onSuccess: onClose })}
          >
            Refund {taka(Number(amount) || 0)}
          </Button>
        </div>
      </div>
    </Modal>
  )
}
