import { CalendarPlus, Plus } from 'lucide-react'
import { useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router'
import { errorMessage } from '../../api/client'
import { Button } from '../../components/ui/Button'
import { Alert, Badge, Card, PageHeader } from '../../components/ui/Card'
import { Field, Input, Select } from '../../components/ui/Field'
import { Modal } from '../../components/ui/Modal'
import { Spinner } from '../../components/ui/Spinner'
import { useAuth } from '../../contexts/useAuth'
import { cn } from '../../utils/cn'
import { todayISO } from '../../utils/format'
import { invoiceStatusStyle, taka, useBillingMutations, useDues, useInvoices } from './api'
import { InvoiceFormModal } from './components/InvoiceFormModal'
import { PatientPicker, type PickedPatient } from './components/PatientPicker'

export default function InvoicesPage() {
  const [params, setParams] = useSearchParams()
  const tab = params.get('tab') ?? 'invoices'
  const { can } = useAuth()
  const [newFor, setNewFor] = useState<PickedPatient | null | 'pick'>(null)
  const [fees, setFees] = useState(false)
  const navigate = useNavigate()

  return (
    <>
      <PageHeader
        title="Invoices"
        description="Issued invoices are never deleted — a mistake is voided with a reason."
        actions={
          can('invoices.manage') && (
            <>
              <Button variant="secondary" onClick={() => setFees(true)}>
                <CalendarPlus className="size-4" /> Training fees
              </Button>
              <Button onClick={() => setNewFor('pick')}>
                <Plus className="size-4" /> New invoice
              </Button>
            </>
          )
        }
      />
      <div className="mb-4 flex gap-1 border-b border-slate-200">
        {[
          ['invoices', 'All invoices'],
          ['dues', 'Due list'],
        ].map(([key, label]) => (
          <button
            key={key}
            onClick={() => setParams({ tab: key }, { replace: true })}
            className={cn('border-b-2 px-3 py-2.5 text-sm font-medium', tab === key ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-800')}
          >
            {label}
          </button>
        ))}
      </div>
      {tab === 'dues' ? <DueList /> : <InvoiceList />}

      {newFor === 'pick' && (
        <Modal open title="New invoice — choose the child" onClose={() => setNewFor(null)}>
          <PatientPicker onPick={setNewFor} />
        </Modal>
      )}
      {newFor && newFor !== 'pick' && <InvoiceFormModal patient={newFor} onClose={() => setNewFor(null)} onSaved={(inv) => navigate(`/app/invoices/${inv.id}`)} />}
      {fees && <TrainingFeesModal onClose={() => setFees(false)} />}
    </>
  )
}

function InvoiceList() {
  const [status, setStatus] = useState('')
  const [q, setQ] = useState('')
  const [page, setPage] = useState(1)
  const { data, isLoading } = useInvoices({ status: status || undefined, q: q || undefined, page })

  return (
    <>
      <Card className="mb-4 flex flex-col gap-2 p-3 sm:flex-row">
        <Input placeholder="Invoice no., child name or ID" value={q} onChange={(e) => (setQ(e.target.value), setPage(1))} className="sm:w-72" aria-label="Search" />
        <Select value={status} onChange={(e) => (setStatus(e.target.value), setPage(1))} className="sm:w-48" aria-label="Status">
          <option value="">All statuses</option>
          <option value="open">Unpaid / part paid</option>
          <option value="paid">Paid</option>
          <option value="draft">Draft</option>
          <option value="void">Void</option>
        </Select>
      </Card>
      {isLoading ? (
        <Spinner className="text-brand-600" />
      ) : !data?.data.length ? (
        <Card className="p-5 text-sm text-slate-500">No invoices.</Card>
      ) : (
        <Card className="overflow-x-auto">
          <table className="w-full min-w-[640px] text-sm">
            <thead className="bg-slate-50 text-left text-xs text-slate-500">
              <tr>
                <th className="px-4 py-2.5 font-medium">Invoice</th>
                <th className="px-4 py-2.5 font-medium">Child</th>
                <th className="px-4 py-2.5 text-right font-medium">Total</th>
                <th className="px-4 py-2.5 text-right font-medium">Due</th>
                <th className="px-4 py-2.5 font-medium">Status</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {data.data.map((i) => (
                <tr key={i.id} className="hover:bg-slate-50">
                  <td className="px-4 py-2.5">
                    <Link to={`/app/invoices/${i.id}`} className="font-medium text-slate-900 hover:text-brand-700">
                      {i.invoice_no ?? 'Draft'}
                    </Link>
                    <p className="text-xs text-slate-500">{i.issue_date ?? '—'}</p>
                  </td>
                  <td className="px-4 py-2.5">
                    {i.patient?.name}
                    <p className="text-xs text-slate-500">{i.patient?.patient_code}</p>
                  </td>
                  <td className="px-4 py-2.5 text-right">{taka(i.total)}</td>
                  <td className={cn('px-4 py-2.5 text-right', i.due_total > 0 && i.status !== 'void' && 'text-red-600')}>{i.status === 'void' ? '—' : taka(i.due_total)}</td>
                  <td className="px-4 py-2.5">
                    <Badge tone={invoiceStatusStyle[i.status].tone}>{invoiceStatusStyle[i.status].label}</Badge>
                    {i.is_overdue && <span className="ml-1 text-xs text-red-600">overdue</span>}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          {data.meta.last_page > 1 && (
            <div className="flex items-center justify-between border-t border-slate-100 px-4 py-2 text-sm">
              <span className="text-slate-500">
                Page {data.meta.current_page} of {data.meta.last_page}
              </span>
              <div className="flex gap-2">
                <Button variant="secondary" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
                  Previous
                </Button>
                <Button variant="secondary" disabled={page >= data.meta.last_page} onClick={() => setPage((p) => p + 1)}>
                  Next
                </Button>
              </div>
            </div>
          )}
        </Card>
      )}
    </>
  )
}

function DueList() {
  const { data, isLoading } = useDues()
  if (isLoading || !data) return <Spinner className="text-brand-600" />

  return (
    <>
      <Card className="mb-4 p-4">
        <p className="text-sm text-slate-500">Total due</p>
        <p className="text-2xl font-semibold text-red-600">{taka(data.total)}</p>
      </Card>
      {data.data.length === 0 ? (
        <Card className="p-5 text-sm text-slate-500">Nobody owes anything. 🎉</Card>
      ) : (
        <Card className="divide-y divide-slate-100">
          {data.data.map((r) => (
            <Link key={r.patient.id} to={`/app/patients/${r.patient.id}?tab=billing`} className="flex flex-wrap items-center justify-between gap-2 px-4 py-3 hover:bg-slate-50">
              <div>
                <p className="font-medium text-slate-900">{r.patient.name}</p>
                <p className="text-xs text-slate-500">
                  {r.patient.patient_code} · {r.patient.phone} · {r.invoices} invoice{r.invoices > 1 ? 's' : ''}
                </p>
              </div>
              <div className="text-right">
                <p className="font-semibold text-red-600">{taka(r.due)}</p>
                <p className={cn('text-xs', r.overdue ? 'text-red-600' : 'text-slate-500')}>
                  {r.overdue ? 'overdue since' : 'due'} {r.oldest_due_date}
                </p>
              </div>
            </Link>
          ))}
        </Card>
      )}
    </>
  )
}

/** D4: fixed monthly training fee — normally automatic on the 1st; this re-runs it safely (never bills a month twice). */
function TrainingFeesModal({ onClose }: { onClose: () => void }) {
  const { trainingFees } = useBillingMutations()
  const [month, setMonth] = useState(todayISO().slice(0, 7))

  return (
    <Modal open title="Regular Training fees" onClose={onClose}>
      <div className="space-y-4">
        <p className="text-sm text-slate-600">
          Creates the month's fee invoice for every active Regular Training student. Runs automatically on the 1st of each month; students already billed for the month are skipped.
        </p>
        <Field label="Month" htmlFor="fee_month">
          <Input id="fee_month" type="month" value={month} onChange={(e) => setMonth(e.target.value)} />
        </Field>
        {trainingFees.isError && <Alert>{errorMessage(trainingFees.error)}</Alert>}
        {trainingFees.data && <Alert tone="green">{trainingFees.data.message}</Alert>}
        <div className="flex justify-end gap-2">
          <Button variant="secondary" onClick={onClose}>
            Close
          </Button>
          <Button loading={trainingFees.isPending} onClick={() => trainingFees.mutate({ month })}>
            Create invoices
          </Button>
        </div>
      </div>
    </Modal>
  )
}
