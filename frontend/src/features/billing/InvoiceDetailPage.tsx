import { ArrowLeft, Ban, FileDown, Pencil, Send, Trash2, Wallet } from 'lucide-react'
import { useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router'
import { errorMessage } from '../../api/client'
import { Button } from '../../components/ui/Button'
import { Alert, Badge, Card } from '../../components/ui/Card'
import { FullPageSpinner } from '../../components/ui/Spinner'
import { invoicePdfUrl, invoiceStatusStyle, itemTypeLabel, methodLabel, receiptPdfUrl, taka, useBillingMutations, useInvoice } from './api'
import { InvoiceFormModal } from './components/InvoiceFormModal'
import { ReceivePaymentModal } from './components/ReceivePaymentModal'

export default function InvoiceDetailPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const { data: inv, isLoading, error } = useInvoice(Number(id))
  const { issue, voidInvoice, deleteDraft } = useBillingMutations()
  const [modal, setModal] = useState<'edit' | 'pay' | null>(null)
  const busy = issue.isPending || voidInvoice.isPending || deleteDraft.isPending
  const actionError = issue.error ?? voidInvoice.error ?? deleteDraft.error

  if (isLoading) return <FullPageSpinner />
  if (error || !inv) return <Alert>{errorMessage(error)}</Alert>
  const patient = { id: inv.patient!.id, name: inv.patient!.name, home_branch_id: inv.branch?.id }

  return (
    <>
      <Link to="/app/invoices" className="mb-3 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-800">
        <ArrowLeft className="size-4" /> Invoices
      </Link>
      <Card className="p-5">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <div>
            <div className="flex items-center gap-2">
              <h1 className="text-xl font-semibold text-slate-900">{inv.invoice_no ?? 'Draft invoice'}</h1>
              <Badge tone={invoiceStatusStyle[inv.status].tone}>{invoiceStatusStyle[inv.status].label}</Badge>
              {inv.is_overdue && <Badge tone="red">overdue</Badge>}
            </div>
            <Link to={`/app/patients/${inv.patient?.id}?tab=billing`} className="mt-1 block text-sm text-sky-brand-600">
              {inv.patient?.name} · {inv.patient?.patient_code}
            </Link>
            <p className="text-sm text-slate-500">
              {inv.branch?.name}
              {inv.issue_date && ` · issued ${inv.issue_date}`}
              {inv.due_date && ` · due ${inv.due_date}`}
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            {inv.can.pay && (
              <Button onClick={() => setModal('pay')}>
                <Wallet className="size-4" /> Receive payment
              </Button>
            )}
            {inv.can.edit && (
              <>
                <Button onClick={() => issue.mutate(inv.id)} loading={issue.isPending} disabled={busy}>
                  <Send className="size-4" /> Issue
                </Button>
                <Button variant="secondary" onClick={() => setModal('edit')}>
                  <Pencil className="size-4" /> Edit
                </Button>
                <Button variant="ghost" disabled={busy} onClick={() => confirm('Delete this draft?') && deleteDraft.mutate(inv.id, { onSuccess: () => navigate('/app/invoices') })}>
                  <Trash2 className="size-4" /> Delete
                </Button>
              </>
            )}
            <a href={invoicePdfUrl(inv.id)} target="_blank" rel="noreferrer">
              <Button variant="secondary">
                <FileDown className="size-4" /> PDF
              </Button>
            </a>
            {inv.can.void && (
              <Button
                variant="ghost"
                disabled={busy}
                onClick={() => {
                  const reason = prompt('Why is this invoice being voided? (required)')
                  if (reason && reason.trim().length >= 5) voidInvoice.mutate({ id: inv.id, reason })
                }}
              >
                <Ban className="size-4" /> Void
              </Button>
            )}
          </div>
        </div>
        {actionError && <div className="mt-3"><Alert>{errorMessage(actionError)}</Alert></div>}
        {inv.status === 'void' && <div className="mt-3"><Alert>Void: {inv.void_reason}</Alert></div>}
      </Card>

      <Card className="mt-4 overflow-x-auto">
        <table className="w-full min-w-[560px] text-sm">
          <thead className="bg-slate-50 text-left text-xs text-slate-500">
            <tr>
              <th className="px-4 py-2.5 font-medium">Item</th>
              <th className="px-4 py-2.5 text-right font-medium">Qty</th>
              <th className="px-4 py-2.5 text-right font-medium">Rate</th>
              <th className="px-4 py-2.5 text-right font-medium">Discount</th>
              <th className="px-4 py-2.5 text-right font-medium">Amount</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {inv.items?.map((it) => (
              <tr key={it.id}>
                <td className="px-4 py-2.5">
                  {it.description}
                  <p className="text-xs text-slate-500">{itemTypeLabel[it.item_type]}</p>
                </td>
                <td className="px-4 py-2.5 text-right">{it.quantity}</td>
                <td className="px-4 py-2.5 text-right">{taka(it.unit_price)}</td>
                <td className="px-4 py-2.5 text-right">{it.discount ? taka(it.discount) : '—'}</td>
                <td className="px-4 py-2.5 text-right">{taka(it.line_total)}</td>
              </tr>
            ))}
          </tbody>
          <tfoot className="text-sm">
            {inv.discount_total > 0 && (
              <tr>
                <td colSpan={4} className="px-4 py-1.5 text-right text-slate-500">
                  Discount ({inv.discount_reason})
                </td>
                <td className="px-4 py-1.5 text-right">−{taka(inv.discount_total)}</td>
              </tr>
            )}
            <tr className="font-semibold">
              <td colSpan={4} className="px-4 py-1.5 text-right">
                Total
              </td>
              <td className="px-4 py-1.5 text-right">{taka(inv.total)}</td>
            </tr>
            <tr>
              <td colSpan={4} className="px-4 py-1.5 text-right text-slate-500">
                Paid
              </td>
              <td className="px-4 py-1.5 text-right text-brand-700">{taka(inv.paid_total)}</td>
            </tr>
            <tr className="font-semibold">
              <td colSpan={4} className="px-4 py-1.5 pb-3 text-right">
                Due
              </td>
              <td className={`px-4 py-1.5 pb-3 text-right ${inv.due_total > 0 ? 'text-red-600' : ''}`}>{taka(inv.due_total)}</td>
            </tr>
          </tfoot>
        </table>
      </Card>

      {!!inv.payments?.length && (
        <Card className="mt-4 p-5">
          <h3 className="font-semibold text-slate-900">Payments</h3>
          <ul className="mt-2 divide-y divide-slate-100 text-sm">
            {inv.payments.map((p, i) => (
              <li key={i} className="flex items-center justify-between py-2">
                <span>
                  {p.receipt_no} · {methodLabel[p.method]} · {new Date(p.paid_at).toLocaleDateString('en-GB')}
                  {p.from_advance && <span className="text-xs text-slate-500"> (from advance)</span>}
                </span>
                <span className="flex items-center gap-3">
                  {taka(p.amount)}
                  <a href={receiptPdfUrl(p.payment_id)} target="_blank" rel="noreferrer" aria-label="Receipt" className="text-slate-400 hover:text-slate-700">
                    <FileDown className="size-4" />
                  </a>
                </span>
              </li>
            ))}
          </ul>
        </Card>
      )}

      {modal === 'edit' && <InvoiceFormModal patient={patient} invoice={inv} onClose={() => setModal(null)} />}
      {modal === 'pay' && <ReceivePaymentModal patient={patient} onClose={() => setModal(null)} />}
    </>
  )
}
