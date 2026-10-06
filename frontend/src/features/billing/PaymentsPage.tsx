import { Ban, FileDown, Wallet } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router'
import { Button } from '../../components/ui/Button'
import { Badge, Card, PageHeader } from '../../components/ui/Card'
import { Input } from '../../components/ui/Field'
import { Modal } from '../../components/ui/Modal'
import { Spinner } from '../../components/ui/Spinner'
import { useAuth } from '../../contexts/useAuth'
import { todayISO } from '../../utils/format'
import { methodLabel, receiptPdfUrl, taka, useBillingMutations, useCollection, usePayments, type PaymentMethod } from './api'
import { PatientPicker, type PickedPatient } from './components/PatientPicker'
import { ReceivePaymentModal } from './components/ReceivePaymentModal'

/** Payments and the day's collection by method (Plan §১৯ "দিন শেষে … cash collection report"). */
export default function PaymentsPage() {
  const { can } = useAuth()
  const [date, setDate] = useState(todayISO())
  const { data: collection } = useCollection(date)
  const { data, isLoading } = usePayments({ date })
  const { voidPayment } = useBillingMutations()
  const [payFor, setPayFor] = useState<PickedPatient | 'pick' | null>(null)

  return (
    <>
      <PageHeader
        title="Payments"
        description={collection?.own_only ? 'Your collection for the day.' : 'Collection for the day, by method and by staff member.'}
        actions={
          can('payments.create') && (
            <Button onClick={() => setPayFor('pick')}>
              <Wallet className="size-4" /> Receive payment
            </Button>
          )
        }
      />
      <Card className="mb-4 flex flex-wrap items-center gap-3 p-3">
        <Input type="date" value={date} max={todayISO()} onChange={(e) => setDate(e.target.value)} className="sm:w-48" aria-label="Date" />
      </Card>

      {collection && (
        <div className="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-6">
          <Card className="col-span-2 p-4">
            <p className="text-sm text-slate-500">Collected</p>
            <p className="text-2xl font-semibold text-brand-700">{taka(collection.total)}</p>
            <p className="text-xs text-slate-500">{collection.count} payments</p>
          </Card>
          {(Object.keys(methodLabel) as PaymentMethod[]).map((m) => (
            <Card key={m} className="p-4">
              <p className="text-xs text-slate-500">{methodLabel[m]}</p>
              <p className="font-semibold text-slate-900">{taka(collection.by_method[m])}</p>
            </Card>
          ))}
        </div>
      )}
      {collection && !collection.own_only && collection.by_user.length > 1 && (
        <Card className="mb-4 p-4">
          <p className="mb-2 text-sm font-medium text-slate-700">By staff member</p>
          <ul className="space-y-1 text-sm">
            {collection.by_user.map((u) => (
              <li key={u.user?.id ?? 0} className="flex justify-between">
                <span>{u.user?.name ?? '—'}</span>
                <span className="text-slate-600">
                  {taka(u.total)} <span className="text-xs text-slate-400">(cash {taka(u.by_method.cash)})</span>
                </span>
              </li>
            ))}
          </ul>
        </Card>
      )}

      {isLoading ? (
        <Spinner className="text-brand-600" />
      ) : !data?.data.length ? (
        <Card className="p-5 text-sm text-slate-500">No payments on this day.</Card>
      ) : (
        <Card className="divide-y divide-slate-100">
          {data.data.map((p) => (
            <div key={p.id} className="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm">
              <div>
                <p className="font-medium text-slate-900">
                  {p.type === 'refund' && <Badge tone="red">refund</Badge>} {p.receipt_no} ·{' '}
                  <Link to={`/app/patients/${p.patient?.id}?tab=billing`} className="text-sky-brand-600">
                    {p.patient?.name}
                  </Link>
                  {p.status === 'void' && <Badge tone="gray">void</Badge>}
                </p>
                <p className="text-xs text-slate-500">
                  {new Date(p.paid_at).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })} · {methodLabel[p.method]}
                  {p.transaction_ref && ` · ${p.transaction_ref}`} · {p.received_by?.name}
                  {p.allocations?.length ? ` · ${p.allocations.map((a) => a.invoice_no).join(', ')}` : ''}
                  {p.unallocated ? ` · ${taka(p.unallocated)} advance` : ''}
                </p>
              </div>
              <div className="flex items-center gap-3">
                <span className={p.status === 'void' ? 'text-slate-400 line-through' : p.type === 'refund' ? 'text-red-600' : 'font-semibold text-brand-700'}>{taka(p.amount)}</span>
                <a href={receiptPdfUrl(p.id)} target="_blank" rel="noreferrer" aria-label="Receipt" className="text-slate-400 hover:text-slate-700">
                  <FileDown className="size-4" />
                </a>
                {can('invoices.void') && p.status === 'completed' && (
                  <button
                    aria-label="Void payment"
                    className="text-slate-400 hover:text-red-600"
                    onClick={() => {
                      const reason = prompt(`Void ${p.receipt_no}? Write the reason:`)
                      if (reason && reason.trim().length >= 5) voidPayment.mutate({ id: p.id, reason })
                    }}
                  >
                    <Ban className="size-4" />
                  </button>
                )}
              </div>
            </div>
          ))}
        </Card>
      )}

      {payFor === 'pick' && (
        <Modal open title="Receive payment — choose the child" onClose={() => setPayFor(null)}>
          <PatientPicker onPick={setPayFor} />
        </Modal>
      )}
      {payFor && payFor !== 'pick' && <ReceivePaymentModal patient={payFor} onClose={() => setPayFor(null)} />}
    </>
  )
}
