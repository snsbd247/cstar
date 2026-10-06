import { useQuery } from '@tanstack/react-query'
import { FileText, Search } from 'lucide-react'
import { useEffect, useState, type ReactNode } from 'react'
import { Link } from 'react-router'
import { api } from '../../api/client'
import { Badge, Card, PageHeader } from '../../components/ui/Card'
import { Input, Select } from '../../components/ui/Field'
import { Pager } from '../../components/ui/Pager'
import { Spinner } from '../../components/ui/Spinner'
import { Stat } from '../../components/ui/Stat'
import { todayISO } from '../../utils/format'
import { invoicePdfUrl, methodLabel, receiptPdfUrl, taka, type PaymentMethod } from './api'

type Meta = { current_page: number; last_page: number; total: number }
type Child = { id: number; name: string; patient_code: string }
const monthStart = () => todayISO().slice(0, 8) + '01'
const when = (iso: string) => new Date(iso).toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short' })

function useDebounced(value: string) {
  const [v, setV] = useState(value)
  useEffect(() => {
    const t = setTimeout(() => setV(value.trim()), 300)
    return () => clearTimeout(t)
  }, [value])
  return v
}

/** Search + date range bar shared by the billing lists. */
function Filters({ search, setSearch, from, setFrom, to, setTo, children, placeholder }: {
  search?: string
  setSearch?: (v: string) => void
  from: string
  setFrom: (v: string) => void
  to: string
  setTo: (v: string) => void
  children?: ReactNode
  placeholder?: string
}) {
  return (
    <Card className="mb-4 flex flex-col gap-2 p-3 sm:flex-row sm:flex-wrap">
      {setSearch && (
        <div className="relative sm:w-72">
          <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
          <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder={placeholder} className="pl-9" aria-label={placeholder} />
        </div>
      )}
      <Input type="date" value={from} onChange={(e) => setFrom(e.target.value)} className="sm:w-44" aria-label="From date" />
      <Input type="date" value={to} onChange={(e) => setTo(e.target.value)} className="sm:w-44" aria-label="To date" />
      {children}
    </Card>
  )
}

function ListShell({ loading, empty, meta, onPage, children, footer }: { loading: boolean; empty: boolean; meta?: Meta; onPage: (p: number) => void; children: ReactNode; footer?: ReactNode }) {
  return (
    <Card className="overflow-hidden">
      {loading ? <Spinner className="m-5 text-brand-600" /> : empty ? <p className="p-5 text-sm text-slate-500">Nothing in this period.</p> : children}
      {footer}
      <Pager meta={meta} onPage={onPage} />
    </Card>
  )
}

interface Dashboard {
  kpis: { collected_today: number; collected_month: number; invoiced_month: number; outstanding: number; overdue_invoices: number; advances_held: number; discounts_month: number }
  by_method: Record<PaymentMethod, number>
  top_dues: { patient: Child; due: number; invoices: number }[]
  recent: { id: number; no: string; type: string; patient: string | null; amount: number; method: PaymentMethod; at: string; status: string }[]
}

/** Billing & Payments → Billing Dashboard. */
export function BillingDashboardPage() {
  const { data, isLoading } = useQuery({ queryKey: ['billing-dashboard'], queryFn: async () => (await api.get<{ data: Dashboard }>('/billing/dashboard')).data.data })
  if (isLoading || !data) return <Spinner className="text-brand-600" />
  const k = data.kpis
  const maxMethod = Math.max(1, ...Object.values(data.by_method))

  return (
    <>
      <PageHeader title="Billing Dashboard" description="Money in, money owed and advances this month. Refunds are subtracted from collection." />
      <div className="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <Stat label="Collected today" value={taka(k.collected_today)} to="/app/payments" tone="green" />
        <Stat label="Collected this month" value={taka(k.collected_month)} to="/app/billing/receipts" />
        <Stat label="Invoiced this month" value={taka(k.invoiced_month)} to="/app/invoices" />
        <Stat label="Outstanding dues" value={taka(k.outstanding)} hint={`${k.overdue_invoices} invoice(s) past due date`} to="/app/invoices?tab=dues" tone={k.outstanding ? 'amber' : undefined} />
        <Stat label="Advances held for families" value={taka(k.advances_held)} hint="Used automatically on the next invoice" />
        <Stat label="Discounts this month" value={taka(k.discounts_month)} to="/app/billing/discounts" />
      </div>
      <div className="grid gap-4 lg:grid-cols-3">
        <Card className="p-4">
          <h2 className="font-semibold text-slate-900">This month by method</h2>
          <ul className="mt-3 space-y-2 text-sm">
            {(Object.keys(methodLabel) as PaymentMethod[]).map((m) => (
              <li key={m}>
                <div className="flex justify-between">
                  <span className="text-slate-600">{methodLabel[m]}</span>
                  <span className="font-medium">{taka(data.by_method[m])}</span>
                </div>
                <div className="mt-1 h-1.5 rounded-full bg-slate-100">
                  <div className="h-1.5 rounded-full bg-brand-500" style={{ width: `${(Math.max(0, data.by_method[m]) / maxMethod) * 100}%` }} />
                </div>
              </li>
            ))}
          </ul>
        </Card>
        <Card className="overflow-hidden">
          <h2 className="border-b border-slate-100 px-4 py-3 font-semibold text-slate-900">Highest dues</h2>
          <ul className="divide-y divide-slate-100 text-sm">
            {data.top_dues.map((d) => (
              <li key={d.patient.id} className="flex justify-between gap-2 px-4 py-2.5">
                <Link to={`/app/patients/${d.patient.id}?tab=billing`} className="text-slate-800 hover:text-brand-700">
                  {d.patient.name}
                </Link>
                <span className="font-medium text-red-700">{taka(d.due)}</span>
              </li>
            ))}
            {!data.top_dues.length && <li className="px-4 py-3 text-slate-500">Nobody owes anything.</li>}
          </ul>
        </Card>
        <Card className="overflow-hidden">
          <h2 className="border-b border-slate-100 px-4 py-3 font-semibold text-slate-900">Latest payments</h2>
          <ul className="divide-y divide-slate-100 text-sm">
            {data.recent.map((p) => (
              <li key={p.id} className={p.status === 'void' ? 'px-4 py-2.5 line-through opacity-60' : 'px-4 py-2.5'}>
                <div className="flex justify-between gap-2">
                  <span className="text-slate-800">{p.patient}</span>
                  <span className={p.type === 'refund' ? 'font-medium text-red-700' : 'font-medium'}>
                    {p.type === 'refund' && '−'}
                    {taka(p.amount)}
                  </span>
                </div>
                <p className="text-xs text-slate-500">
                  {p.no} · {methodLabel[p.method]} · {when(p.at)}
                </p>
              </li>
            ))}
          </ul>
        </Card>
      </div>
    </>
  )
}

interface PaymentRow {
  id: number
  no: string
  patient: Child | null
  amount: number
  method: PaymentMethod
  transaction_ref: string | null
  at: string
  status: string
  by: string | null
  note: string | null
  void_reason: string | null
}

/** Billing & Payments → Receipts (type payment) and Refunds (type refund). */
export function ReceiptsPage({ refunds }: { refunds?: boolean }) {
  const [search, setSearch] = useState('')
  const q = useDebounced(search)
  const [from, setFrom] = useState(monthStart())
  const [to, setTo] = useState('')
  const [method, setMethod] = useState('')
  const [page, setPage] = useState(1)
  const { data, isLoading } = useQuery({
    queryKey: ['billing-payment-list', refunds, q, from, to, method, page],
    queryFn: async () =>
      (
        await api.get<{ data: PaymentRow[]; meta: Meta; total: number }>('/billing/payment-list', {
          params: { type: refunds ? 'refund' : 'payment', q: q || undefined, from: from || undefined, to: to || undefined, method: method || undefined, page },
        })
      ).data,
  })
  const reset = (set: (v: string) => void) => (v: string) => (set(v), setPage(1))

  return (
    <>
      <PageHeader
        title={refunds ? 'Refunds' : 'Receipts'}
        description={refunds ? 'Money paid back to families — only ever from their advance. Make a refund from the child’s Billing tab.' : 'Every payment received, with its money receipt. Take a payment from the child’s Billing tab or Payments.'}
      />
      <Filters search={search} setSearch={reset(setSearch)} from={from} setFrom={reset(setFrom)} to={to} setTo={reset(setTo)} placeholder="Child, receipt no. or transaction ID">
        <Select value={method} onChange={(e) => reset(setMethod)(e.target.value)} className="sm:w-40" aria-label="Method">
          <option value="">Any method</option>
          {Object.entries(methodLabel).map(([k, l]) => (
            <option key={k} value={k}>
              {l}
            </option>
          ))}
        </Select>
      </Filters>
      <ListShell
        loading={isLoading}
        empty={!data?.data.length}
        meta={data?.meta}
        onPage={setPage}
        footer={data && data.data.length > 0 && <p className="border-t border-slate-100 px-4 py-2.5 text-right text-sm text-slate-600">Total (not voided): <b>{taka(data.total)}</b></p>}
      >
        <ul className="divide-y divide-slate-100">
          {data?.data.map((p) => (
            <li key={p.id} className="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm">
              <div className={p.status === 'void' ? 'opacity-60' : ''}>
                <p>
                  <span className="font-medium text-slate-900">{p.no}</span>{' '}
                  {p.patient && (
                    <Link to={`/app/patients/${p.patient.id}?tab=billing`} className="text-slate-700 hover:text-brand-700">
                      · {p.patient.name}
                    </Link>
                  )}{' '}
                  {p.status === 'void' && <Badge tone="gray">void</Badge>}
                </p>
                <p className="text-xs text-slate-500">
                  {when(p.at)} · {methodLabel[p.method]}
                  {p.transaction_ref && ` · ${p.transaction_ref}`}
                  {p.by && ` · ${p.by}`}
                  {refunds && p.note && ` · ${p.note}`}
                  {p.void_reason && ` · void: ${p.void_reason}`}
                </p>
              </div>
              <span className="flex items-center gap-3">
                <b className={refunds ? 'text-red-700' : ''}>{taka(p.amount)}</b>
                {!refunds && (
                  <a href={receiptPdfUrl(p.id)} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 text-slate-500 hover:text-slate-800" aria-label={`Receipt ${p.no}`}>
                    <FileText className="size-4" />
                  </a>
                )}
              </span>
            </li>
          ))}
        </ul>
      </ListShell>
    </>
  )
}

/** Billing & Payments → Payment Allocations: how each payment was spread over invoices. */
export function AllocationsPage() {
  const [search, setSearch] = useState('')
  const q = useDebounced(search)
  const [from, setFrom] = useState(monthStart())
  const [to, setTo] = useState('')
  const [page, setPage] = useState(1)
  const { data, isLoading } = useQuery({
    queryKey: ['billing-allocations', q, from, to, page],
    queryFn: async () =>
      (
        await api.get<{
          data: { id: number; amount: number; from_advance: boolean; payment: { id: number; no: string; at: string; method: PaymentMethod; status: string }; patient: Child | null; invoice: { id: number; no: string; total: number; status: string } }[]
          meta: Meta
        }>('/billing/allocations', { params: { q: q || undefined, from: from || undefined, to: to || undefined, page } })
      ).data,
  })

  return (
    <>
      <PageHeader title="Payment Allocations" description="A payment pays the oldest invoices first; what is left becomes advance and pays the next invoice automatically." />
      <Filters search={search} setSearch={(v) => (setSearch(v), setPage(1))} from={from} setFrom={(v) => (setFrom(v), setPage(1))} to={to} setTo={(v) => (setTo(v), setPage(1))} placeholder="Child, receipt or invoice no." />
      <ListShell loading={isLoading} empty={!data?.data.length} meta={data?.meta} onPage={setPage}>
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
              <tr>
                <th className="px-4 py-2.5 font-medium">Payment</th>
                <th className="px-4 py-2.5 font-medium">Child</th>
                <th className="px-4 py-2.5 font-medium">Invoice</th>
                <th className="px-4 py-2.5 text-right font-medium">Applied</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {data?.data.map((a) => (
                <tr key={a.id} className={a.payment.status === 'void' ? 'opacity-50' : ''}>
                  <td className="px-4 py-2.5">
                    <p className="font-medium text-slate-900">{a.payment.no}</p>
                    <p className="text-xs text-slate-500">
                      {when(a.payment.at)} · {methodLabel[a.payment.method]}
                    </p>
                  </td>
                  <td className="px-4 py-2.5">
                    {a.patient && (
                      <Link to={`/app/patients/${a.patient.id}?tab=billing`} className="text-slate-800 hover:text-brand-700">
                        {a.patient.name}
                      </Link>
                    )}
                  </td>
                  <td className="px-4 py-2.5">
                    <Link to={`/app/invoices/${a.invoice.id}`} className="text-slate-800 hover:text-brand-700">
                      {a.invoice.no}
                    </Link>
                    <span className="block text-xs text-slate-500">of {taka(a.invoice.total)}</span>
                  </td>
                  <td className="px-4 py-2.5 text-right font-medium">
                    {taka(a.amount)}
                    {a.from_advance && <span className="block text-xs font-normal text-slate-500">from advance</span>}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </ListShell>
    </>
  )
}

/** Billing & Payments → Discounts / Concessions: every discount given, why, and by whom. */
export function DiscountsPage() {
  const [from, setFrom] = useState(monthStart())
  const [to, setTo] = useState('')
  const [page, setPage] = useState(1)
  const { data, isLoading } = useQuery({
    queryKey: ['billing-discounts', from, to, page],
    queryFn: async () =>
      (
        await api.get<{
          data: { id: number; no: string; date: string | null; patient: Child; subtotal: number; discount: number; percent: number | null; reason: string | null; by: string | null; status: string }[]
          meta: Meta
          total: number
        }>('/billing/discounts', { params: { from: from || undefined, to: to || undefined, page } })
      ).data,
  })

  return (
    <>
      <PageHeader title="Discounts / Concessions" description="A reason is required for every discount; receptionists can give up to the limit in Billing Settings." />
      <Filters from={from} setFrom={(v) => (setFrom(v), setPage(1))} to={to} setTo={(v) => (setTo(v), setPage(1))} />
      <ListShell
        loading={isLoading}
        empty={!data?.data.length}
        meta={data?.meta}
        onPage={setPage}
        footer={data && data.data.length > 0 && <p className="border-t border-slate-100 px-4 py-2.5 text-right text-sm text-slate-600">Total discount (not voided): <b>{taka(data.total)}</b></p>}
      >
        <ul className="divide-y divide-slate-100">
          {data?.data.map((d) => (
            <li key={d.id} className="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm">
              <div className={d.status === 'void' ? 'opacity-60' : ''}>
                <p>
                  <Link to={`/app/invoices/${d.id}`} className="font-medium text-slate-900 hover:text-brand-700">
                    {d.no}
                  </Link>{' '}
                  <Link to={`/app/patients/${d.patient.id}?tab=billing`} className="text-slate-700 hover:text-brand-700">
                    · {d.patient.name}
                  </Link>{' '}
                  {d.status === 'void' && <Badge>void</Badge>}
                </p>
                <p className="text-xs text-slate-500">
                  {d.date} · {d.reason ?? 'no reason recorded'}
                  {d.by && ` · ${d.by}`}
                </p>
              </div>
              <span className="flex items-center gap-3">
                <span className="text-right">
                  <b>{taka(d.discount)}</b>
                  {d.percent !== null && <span className="block text-xs text-slate-500">{d.percent}% of {taka(d.subtotal)}</span>}
                </span>
                <a href={invoicePdfUrl(d.id)} target="_blank" rel="noreferrer" className="text-slate-500 hover:text-slate-800" aria-label={`Invoice ${d.no}`}>
                  <FileText className="size-4" />
                </a>
              </span>
            </li>
          ))}
        </ul>
      </ListShell>
    </>
  )
}

const usageTone = (reason: string) => (reason === 'session' ? 'green' : reason === 'no_show' ? 'red' : 'amber')

/** Packages → Package Usage: every session taken from a package and why. */
export function PackageUsagePage() {
  const [search, setSearch] = useState('')
  const q = useDebounced(search)
  const [from, setFrom] = useState(monthStart())
  const [to, setTo] = useState('')
  const [reason, setReason] = useState('')
  const [page, setPage] = useState(1)
  const { data, isLoading } = useQuery({
    queryKey: ['package-usage', q, from, to, reason, page],
    queryFn: async () =>
      (
        await api.get<{
          data: { id: number; reason: string; reason_label: string; quantity: number; value: number; at: string; appointment_date: string | null; patient: Child; package: string | null; left: number; note: string | null }[]
          meta: Meta
        }>('/package-usage', { params: { q: q || undefined, from: from || undefined, to: to || undefined, reason: reason || undefined, page } })
      ).data,
  })
  const reset = (set: (v: string) => void) => (v: string) => (set(v), setPage(1))

  return (
    <>
      <PageHeader title="Package Usage" description="A session comes off the package when the therapist finalizes the note — and for a no-show or late cancellation (Billing Settings)." />
      <Filters search={search} setSearch={reset(setSearch)} from={from} setFrom={reset(setFrom)} to={to} setTo={reset(setTo)} placeholder="Child or patient ID">
        <Select value={reason} onChange={(e) => reset(setReason)(e.target.value)} className="sm:w-48" aria-label="Reason">
          <option value="">Any reason</option>
          <option value="session">Session held</option>
          <option value="no_show">No-show</option>
          <option value="late_cancel">Late cancellation</option>
        </Select>
      </Filters>
      <ListShell loading={isLoading} empty={!data?.data.length} meta={data?.meta} onPage={setPage}>
        <ul className="divide-y divide-slate-100">
          {data?.data.map((u) => (
            <li key={u.id} className="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm">
              <div>
                <Link to={`/app/patients/${u.patient.id}?tab=billing`} className="font-medium text-slate-900 hover:text-brand-700">
                  {u.patient.name}
                </Link>{' '}
                <span className="text-xs text-slate-500">· {u.package}</span>
                <p className="text-xs text-slate-500">
                  {u.appointment_date ?? when(u.at)} · {u.left} session(s) left
                  {u.note && ` · ${u.note}`}
                </p>
              </div>
              <span className="flex items-center gap-2">
                <Badge tone={usageTone(u.reason)}>{u.reason_label}</Badge>
                <span className="text-slate-600">{taka(u.value)}</span>
              </span>
            </li>
          ))}
        </ul>
      </ListShell>
    </>
  )
}
