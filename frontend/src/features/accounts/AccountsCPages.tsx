import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft, Calculator, Check, FileUp, Link2, Plus, Trash2, Unlink, Wand2 } from 'lucide-react'
import { useState } from 'react'
import { Link, useParams } from 'react-router'
import { api, errorMessage, validationErrors } from '../../api/client'
import { Button } from '../../components/ui/Button'
import { Alert, Badge, Card, PageHeader } from '../../components/ui/Card'
import { Field, Input, Select } from '../../components/ui/Field'
import { Modal } from '../../components/ui/Modal'
import { Spinner } from '../../components/ui/Spinner'
import { useAuth } from '../../contexts/useAuth'
import { cn } from '../../utils/cn'
import { todayISO } from '../../utils/format'
import { taka } from '../billing/api'
import { useChart, useExpenseOptions } from './api'

const err = (e: unknown) => Object.values(validationErrors(e))[0] ?? errorMessage(e)

function useInvalidate() {
  const qc = useQueryClient()
  return () => ['vendors', 'vendor', 'recs', 'rec', 'assets', 'budgets', 'budget', 'ledger', 'report', 'accounts-dashboard'].forEach((k) => qc.invalidateQueries({ queryKey: [k] }))
}

/** Expense / asset accounts a bill line can go to. */
function useBillAccounts() {
  const { data } = useChart()
  return data?.data.filter((a) => !a.is_group && a.is_active && (a.type === 'expense' || (a.type === 'asset' && !['cash', 'bank', 'mfs', 'receivable'].includes(a.subtype ?? '')))) ?? []
}

// ================================================================================================
// Vendors & payables (Accounts §৬)
// ================================================================================================

interface VendorRow {
  id: number
  name: string
  type: string
  phone: string | null
  balance: number
  aging: Record<'current' | 'd30' | 'd60' | 'd90' | 'd90p', number>
}

export function VendorsPage() {
  const { can } = useAuth()
  const { data } = useQuery({ queryKey: ['vendors'], queryFn: async () => (await api.get<{ data: VendorRow[]; total: number }>('/accounts/vendors')).data })
  const [adding, setAdding] = useState(false)

  return (
    <>
      <PageHeader
        title="Vendors & payables"
        description="Buy on credit (bill), pay later — and see who is owed how much, for how long."
        actions={
          can('accounts.voucher.create') && (
            <Button onClick={() => setAdding(true)}>
              <Plus className="size-4" /> Vendor
            </Button>
          )
        }
      />
      {!data ? (
        <Spinner className="text-brand-600" />
      ) : (
        <Card className="overflow-x-auto">
          <table className="w-full min-w-[720px] text-sm">
            <thead className="bg-slate-50 text-left text-xs text-slate-500">
              <tr>
                <th className="px-4 py-2 font-medium">Vendor</th>
                {['Not due', '1–30 days', '31–60', '61–90', '90+'].map((h) => (
                  <th key={h} className="px-3 py-2 text-right font-medium">
                    {h}
                  </th>
                ))}
                <th className="px-4 py-2 text-right font-medium">Owed</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {data.data.map((v) => (
                <tr key={v.id} className="hover:bg-slate-50">
                  <td className="px-4 py-2">
                    <Link to={`/app/accounts/vendors/${v.id}`} className="font-medium text-slate-900 hover:text-brand-700">
                      {v.name}
                    </Link>
                    <p className="text-xs capitalize text-slate-500">{v.type}</p>
                  </td>
                  {(['current', 'd30', 'd60', 'd90', 'd90p'] as const).map((k) => (
                    <td key={k} className={cn('px-3 py-2 text-right tabular-nums', k !== 'current' && v.aging[k] > 0 && 'text-red-600')}>
                      {v.aging[k] ? taka(v.aging[k]) : '—'}
                    </td>
                  ))}
                  <td className="px-4 py-2 text-right font-semibold tabular-nums">{taka(v.balance)}</td>
                </tr>
              ))}
            </tbody>
            <tfoot className="border-t border-slate-200 font-semibold">
              <tr>
                <td className="px-4 py-2" colSpan={6}>
                  Total owed to vendors
                </td>
                <td className="px-4 py-2 text-right">{taka(data.total)}</td>
              </tr>
            </tfoot>
          </table>
        </Card>
      )}
      {adding && <VendorModal onClose={() => setAdding(false)} />}
    </>
  )
}

function VendorModal({ onClose }: { onClose: () => void }) {
  const invalidate = useInvalidate()
  const [v, setV] = useState({ name: '', type: 'supplier', phone: '', address: '', opening_balance: '' })
  const save = useMutation({ mutationFn: () => api.post('/accounts/vendors', { ...v, opening_balance: Number(v.opening_balance) || 0 }), onSuccess: () => (invalidate(), onClose()) })

  return (
    <Modal open title="New vendor" onClose={onClose}>
      <div className="space-y-4">
        {save.isError && <Alert>{err(save.error)}</Alert>}
        <Field label="Name" htmlFor="vd_name">
          <Input id="vd_name" value={v.name} onChange={(e) => setV({ ...v, name: e.target.value })} />
        </Field>
        <div className="grid grid-cols-2 gap-3">
          <Field label="Type" htmlFor="vd_type">
            <Select id="vd_type" value={v.type} onChange={(e) => setV({ ...v, type: e.target.value })}>
              {['supplier', 'landlord', 'utility', 'service', 'other'].map((t) => (
                <option key={t} value={t}>
                  {t}
                </option>
              ))}
            </Select>
          </Field>
          <Field label="Phone" htmlFor="vd_phone">
            <Input id="vd_phone" value={v.phone} onChange={(e) => setV({ ...v, phone: e.target.value })} />
          </Field>
        </div>
        <Field label="Address" htmlFor="vd_addr">
          <Input id="vd_addr" value={v.address} onChange={(e) => setV({ ...v, address: e.target.value })} />
        </Field>
        <Field label="Already owed at go-live (৳)" htmlFor="vd_open" hint="Set once — it is posted to the books">
          <Input id="vd_open" type="number" min={0} value={v.opening_balance} onChange={(e) => setV({ ...v, opening_balance: e.target.value })} />
        </Field>
        <div className="flex justify-end gap-2">
          <Button variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button loading={save.isPending} disabled={!v.name} onClick={() => save.mutate()}>
            Save
          </Button>
        </div>
      </div>
    </Modal>
  )
}

interface VendorDetail {
  id: number
  name: string
  type: string
  phone: string | null
  address: string | null
  opening_balance: number
  balance: number
  bills: { id: number; bill_no: string; vendor_ref: string | null; status: string; date: string; due_date: string | null; total: number; paid: number; due: number; items: { account: { code: string; name: string }; description: string; amount: number }[] }[]
  payments: { id: number; payment_no: string; date: string; amount: number; paid_from: string; reference: string | null; bills: string[] }[]
}

export function VendorDetailPage() {
  const { id } = useParams()
  const { can } = useAuth()
  const invalidate = useInvalidate()
  const { data: v } = useQuery({ queryKey: ['vendor', id], queryFn: async () => (await api.get<{ data: VendorDetail }>(`/accounts/vendors/${id}`)).data.data })
  const [modal, setModal] = useState<'bill' | 'pay' | null>(null)
  const voidBill = useMutation({ mutationFn: ({ billId, reason }: { billId: number; reason: string }) => api.post(`/accounts/vendor-bills/${billId}/void`, { reason }), onSuccess: invalidate })
  if (!v) return <Spinner className="text-brand-600" />

  return (
    <>
      <Link to="/app/accounts/vendors" className="mb-3 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-800">
        <ArrowLeft className="size-4" /> Vendors
      </Link>
      <Card className="flex flex-wrap items-start justify-between gap-3 p-5">
        <div>
          <h1 className="text-xl font-semibold text-slate-900">{v.name}</h1>
          <p className="text-sm capitalize text-slate-500">
            {v.type}
            {v.phone && ` · ${v.phone}`}
            {v.address && ` · ${v.address}`}
          </p>
          <p className="mt-1 text-sm">
            Owed <b className={v.balance > 0 ? 'text-red-600' : 'text-slate-900'}>{taka(v.balance)}</b>
            {v.opening_balance > 0 && <span className="text-xs text-slate-500"> (incl. {taka(v.opening_balance)} from go-live)</span>}
          </p>
        </div>
        {can('accounts.voucher.create') && (
          <div className="flex gap-2">
            <Button variant="secondary" onClick={() => setModal('bill')}>
              <Plus className="size-4" /> Bill
            </Button>
            <Button disabled={v.balance <= 0} onClick={() => setModal('pay')}>
              Pay
            </Button>
          </div>
        )}
      </Card>
      {voidBill.isError && <div className="mt-3"><Alert>{err(voidBill.error)}</Alert></div>}

      <div className="mt-4 grid gap-4 lg:grid-cols-2">
        <Card className="p-5">
          <h3 className="font-semibold text-slate-900">Bills</h3>
          <ul className="mt-2 divide-y divide-slate-100 text-sm">
            {v.bills.map((b) => (
              <li key={b.id} className="py-2.5">
                <div className="flex items-center justify-between gap-2">
                  <span className="font-medium">
                    {b.bill_no} <span className="text-xs font-normal text-slate-500">· {b.date}{b.vendor_ref && ` · their ref ${b.vendor_ref}`}</span>
                  </span>
                  <span className="flex items-center gap-2">
                    {taka(b.total)}
                    <Badge tone={b.status === 'paid' ? 'green' : b.status === 'void' ? 'gray' : b.status === 'partially_paid' ? 'amber' : 'red'}>{b.status.replace('_', ' ')}</Badge>
                  </span>
                </div>
                <p className="text-xs text-slate-500">{b.items.map((i) => `${i.description} (${i.account.name})`).join(', ')}</p>
                {b.due > 0 && b.status !== 'void' && <p className="text-xs text-red-600">due {taka(b.due)}{b.due_date && ` by ${b.due_date}`}</p>}
                {b.status === 'unpaid' && can('accounts.voucher.approve') && (
                  <button
                    className="text-xs text-slate-400 hover:text-red-600"
                    onClick={() => {
                      const reason = prompt('Why void this bill?')
                      if (reason && reason.trim().length >= 5) voidBill.mutate({ billId: b.id, reason })
                    }}
                  >
                    Void
                  </button>
                )}
              </li>
            ))}
            {v.bills.length === 0 && <li className="py-2 text-slate-500">No bills.</li>}
          </ul>
        </Card>
        <Card className="p-5">
          <h3 className="font-semibold text-slate-900">Payments</h3>
          <ul className="mt-2 divide-y divide-slate-100 text-sm">
            {v.payments.map((p) => (
              <li key={p.id} className="flex items-center justify-between py-2.5">
                <span>
                  {p.payment_no} <span className="text-xs text-slate-500">· {p.date} · {p.paid_from}{p.bills.length > 0 && ` · ${p.bills.join(', ')}`}</span>
                </span>
                <span className="font-medium">{taka(p.amount)}</span>
              </li>
            ))}
            {v.payments.length === 0 && <li className="py-2 text-slate-500">No payments.</li>}
          </ul>
        </Card>
      </div>
      {modal === 'bill' && <BillModal vendorId={v.id} onClose={() => setModal(null)} />}
      {modal === 'pay' && <VendorPayModal vendorId={v.id} owed={v.balance} onClose={() => setModal(null)} />}
    </>
  )
}

function BillModal({ vendorId, onClose }: { vendorId: number; onClose: () => void }) {
  const { user } = useAuth()
  const invalidate = useInvalidate()
  const accounts = useBillAccounts()
  const [v, setV] = useState({ date: todayISO(), due_date: '', vendor_ref: '', branch_id: user?.branches?.[0]?.id ?? 0 })
  const [items, setItems] = useState([{ key: 1, account_id: '', description: '', amount: '' }])
  const total = items.reduce((s, i) => s + (Number(i.amount) || 0), 0)
  const save = useMutation({
    mutationFn: () => api.post(`/accounts/vendors/${vendorId}/bills`, { ...v, due_date: v.due_date || null, items: items.map(({ account_id, description, amount }) => ({ account_id: Number(account_id), description, amount: Number(amount) })) }),
    onSuccess: () => (invalidate(), onClose()),
  })

  return (
    <Modal open wide title="Record a bill (bought on credit)" onClose={onClose}>
      <div className="space-y-4">
        {save.isError && <Alert>{err(save.error)}</Alert>}
        <div className="grid gap-3 sm:grid-cols-3">
          <Field label="Bill date" htmlFor="vb_date">
            <Input id="vb_date" type="date" max={todayISO()} value={v.date} onChange={(e) => setV({ ...v, date: e.target.value })} />
          </Field>
          <Field label="Pay by" htmlFor="vb_due">
            <Input id="vb_due" type="date" value={v.due_date} onChange={(e) => setV({ ...v, due_date: e.target.value })} />
          </Field>
          <Field label="Their bill no." htmlFor="vb_ref">
            <Input id="vb_ref" value={v.vendor_ref} onChange={(e) => setV({ ...v, vendor_ref: e.target.value })} />
          </Field>
        </div>
        {items.map((it) => (
          <div key={it.key} className="grid grid-cols-2 gap-2 sm:grid-cols-[1fr_1fr_120px_32px]">
            <Select aria-label="Account" value={it.account_id} onChange={(e) => setItems((l) => l.map((x) => (x.key === it.key ? { ...x, account_id: e.target.value } : x)))}>
              <option value="">For…</option>
              {accounts.map((a) => (
                <option key={a.id} value={a.id}>
                  {a.code} {a.name}
                </option>
              ))}
            </Select>
            <Input aria-label="Description" placeholder="What" value={it.description} onChange={(e) => setItems((l) => l.map((x) => (x.key === it.key ? { ...x, description: e.target.value } : x)))} />
            <Input aria-label="Amount" type="number" min={1} placeholder="৳" value={it.amount} onChange={(e) => setItems((l) => l.map((x) => (x.key === it.key ? { ...x, amount: e.target.value } : x)))} />
            <button type="button" aria-label="Remove" disabled={items.length === 1} onClick={() => setItems((l) => l.filter((x) => x.key !== it.key))} className="justify-self-end p-2 text-slate-400 hover:text-red-600 disabled:opacity-30">
              <Trash2 className="size-4" />
            </button>
          </div>
        ))}
        <Button variant="ghost" type="button" onClick={() => setItems((l) => [...l, { key: Date.now(), account_id: '', description: '', amount: '' }])}>
          <Plus className="size-4" /> Line
        </Button>
        <div className="flex items-center justify-between">
          <span className="text-sm">
            Total <b>{taka(total)}</b>
          </span>
          <div className="flex gap-2">
            <Button variant="secondary" onClick={onClose}>
              Cancel
            </Button>
            <Button loading={save.isPending} disabled={!total || items.some((i) => !i.account_id || !i.description)} onClick={() => save.mutate()}>
              Save bill
            </Button>
          </div>
        </div>
      </div>
    </Modal>
  )
}

function VendorPayModal({ vendorId, owed, onClose }: { vendorId: number; owed: number; onClose: () => void }) {
  const { user } = useAuth()
  const branchId = user?.branches?.[0]?.id ?? 0
  const invalidate = useInvalidate()
  const { data: options } = useExpenseOptions(branchId)
  const [v, setV] = useState({ date: todayISO(), amount: String(owed), paid_from_account_id: '', reference: '' })
  const from = v.paid_from_account_id || String(options?.paid_from.find((a) => a.subtype === 'bank')?.id ?? '')
  const save = useMutation({
    mutationFn: () => api.post(`/accounts/vendors/${vendorId}/payments`, { ...v, branch_id: branchId, amount: Number(v.amount), paid_from_account_id: Number(from) }),
    onSuccess: () => (invalidate(), onClose()),
  })

  return (
    <Modal open title="Pay vendor" onClose={onClose}>
      <div className="space-y-4">
        {save.isError && <Alert>{err(save.error)}</Alert>}
        <p className="text-sm text-slate-600">Owed {taka(owed)} — paid to the oldest bills first.</p>
        <div className="grid grid-cols-2 gap-3">
          <Field label="Amount (৳)" htmlFor="vp_amount">
            <Input id="vp_amount" type="number" min={1} max={owed} value={v.amount} onChange={(e) => setV({ ...v, amount: e.target.value })} />
          </Field>
          <Field label="Date" htmlFor="vp_date">
            <Input id="vp_date" type="date" max={todayISO()} value={v.date} onChange={(e) => setV({ ...v, date: e.target.value })} />
          </Field>
          <Field label="Paid from" htmlFor="vp_from">
            <Select id="vp_from" value={from} onChange={(e) => setV({ ...v, paid_from_account_id: e.target.value })}>
              {options?.paid_from.map((a) => (
                <option key={a.id} value={a.id}>
                  {a.name}
                </option>
              ))}
            </Select>
          </Field>
          <Field label="Cheque / TRX no." htmlFor="vp_ref">
            <Input id="vp_ref" value={v.reference} onChange={(e) => setV({ ...v, reference: e.target.value })} />
          </Field>
        </div>
        <div className="flex justify-end gap-2">
          <Button variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button loading={save.isPending} disabled={!Number(v.amount) || !from} onClick={() => save.mutate()}>
            Pay {taka(Number(v.amount) || 0)}
          </Button>
        </div>
      </div>
    </Modal>
  )
}

// ================================================================================================
// Bank reconciliation (Accounts §৯)
// ================================================================================================

interface RecSummary {
  book_balance: number
  statement_balance: number
  outstanding_total: number
  adjusted_book: number
  difference: number
  outstanding: { id: number; date: string; voucher_no: string; narration: string; amount: number }[]
}
interface RecDetail {
  id: number
  status: 'draft' | 'completed'
  statement_date: string
  statement_balance: number
  account: { id: number; code: string; name: string }
  lines: { id: number; date: string; description: string; amount: number; status: 'unmatched' | 'matched' | 'adjusted'; matched: { voucher_no: string; date: string; narration: string } | null }[]
  summary: RecSummary
}

export function ReconciliationsPage() {
  const { can } = useAuth()
  const invalidate = useInvalidate()
  const { data } = useQuery({
    queryKey: ['recs'],
    queryFn: async () =>
      (await api.get<{ data: { id: number; status: string; statement_date: string; statement_balance: number; lines_count: number; unmatched_count: number; account: { name: string } }[]; accounts: { id: number; code: string; name: string }[] }>('/accounts/reconciliations')).data,
  })
  const [v, setV] = useState({ account_id: '', statement_date: todayISO(), statement_balance: '' })
  const create = useMutation({ mutationFn: () => api.post('/accounts/reconciliations', { ...v, account_id: Number(v.account_id || data?.accounts[0]?.id), statement_balance: Number(v.statement_balance) }), onSuccess: invalidate })

  return (
    <>
      <PageHeader title="Bank reconciliation" description="Match the bank / bKash / Nagad statement with the books, line by line." />
      {can('accounts.voucher.create') && (
        <Card className="mb-4 space-y-3 p-4">
          {create.isError && <Alert>{err(create.error)}</Alert>}
          <div className="grid gap-2 sm:grid-cols-[1fr_170px_170px_auto] sm:items-end">
            <Field label="Account" htmlFor="rc_acc">
              <Select id="rc_acc" value={v.account_id} onChange={(e) => setV({ ...v, account_id: e.target.value })}>
                {data?.accounts.map((a) => (
                  <option key={a.id} value={a.id}>
                    {a.name}
                  </option>
                ))}
              </Select>
            </Field>
            <Field label="Statement date" htmlFor="rc_date">
              <Input id="rc_date" type="date" max={todayISO()} value={v.statement_date} onChange={(e) => setV({ ...v, statement_date: e.target.value })} />
            </Field>
            <Field label="Closing balance (৳)" htmlFor="rc_bal">
              <Input id="rc_bal" type="number" value={v.statement_balance} onChange={(e) => setV({ ...v, statement_balance: e.target.value })} />
            </Field>
            <Button loading={create.isPending} disabled={v.statement_balance === ''} onClick={() => create.mutate()}>
              <Plus className="size-4" /> Start
            </Button>
          </div>
        </Card>
      )}
      <Card className="divide-y divide-slate-100">
        {data?.data.map((r) => (
          <Link key={r.id} to={`/app/accounts/reconciliation/${r.id}`} className="flex items-center justify-between gap-2 px-4 py-3 hover:bg-slate-50">
            <span>
              <span className="font-medium text-slate-900">{r.account.name}</span>
              <span className="text-xs text-slate-500"> · statement {r.statement_date} · {r.lines_count} lines</span>
            </span>
            <span className="flex items-center gap-2">
              {taka(r.statement_balance)}
              <Badge tone={r.status === 'completed' ? 'green' : 'amber'}>{r.status === 'completed' ? 'reconciled' : `${r.unmatched_count} to match`}</Badge>
            </span>
          </Link>
        ))}
        {data?.data.length === 0 && <p className="p-4 text-sm text-slate-500">None yet.</p>}
      </Card>
    </>
  )
}

export function ReconciliationDetailPage() {
  const { id } = useParams()
  const qc = useQueryClient()
  const { data: rec } = useQuery({ queryKey: ['rec', id], queryFn: async () => (await api.get<{ data: RecDetail }>(`/accounts/reconciliations/${id}`)).data.data })
  const set = (d: RecDetail) => (qc.setQueryData(['rec', id], d), qc.invalidateQueries({ queryKey: ['recs'] }))
  const action = useMutation({
    mutationFn: async ({ path, body }: { path: string; body?: unknown }) => (await api.post<{ data: RecDetail; message?: string }>(path, body)).data,
    onSuccess: (r) => set(r.data),
  })
  const [line, setLine] = useState({ date: todayISO(), description: '', amount: '' })
  const [matching, setMatching] = useState<number | null>(null)
  if (!rec) return <Spinner className="text-brand-600" />
  const draft = rec.status === 'draft'
  const s = rec.summary

  return (
    <>
      <Link to="/app/accounts/reconciliation" className="mb-3 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-800">
        <ArrowLeft className="size-4" /> Reconciliations
      </Link>
      <Card className="p-5">
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h1 className="text-xl font-semibold text-slate-900">{rec.account.name}</h1>
            <p className="text-sm text-slate-500">Statement to {rec.statement_date}</p>
          </div>
          {draft && (
            <div className="flex flex-wrap gap-2">
              <label className="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                <FileUp className="size-4" /> Import CSV
                <input
                  type="file"
                  accept=".csv,text/csv"
                  hidden
                  onChange={(e) => {
                    const f = e.target.files?.[0]
                    if (!f) return
                    const form = new FormData()
                    form.append('file', f)
                    action.mutate({ path: `/accounts/reconciliations/${rec.id}/import`, body: form })
                  }}
                />
              </label>
              <Button variant="secondary" onClick={() => action.mutate({ path: `/accounts/reconciliations/${rec.id}/auto-match` })}>
                <Wand2 className="size-4" /> Auto-match
              </Button>
              <Button disabled={Math.abs(s.difference) > 0.005} onClick={() => action.mutate({ path: `/accounts/reconciliations/${rec.id}/complete` })}>
                <Check className="size-4" /> Complete
              </Button>
            </div>
          )}
        </div>
        {action.isError && <div className="mt-3"><Alert>{err(action.error)}</Alert></div>}
        {action.data?.message && <div className="mt-3"><Alert tone="green">{action.data.message}</Alert></div>}
        <div className="mt-4 grid gap-2 text-sm sm:grid-cols-4">
          {[
            ['Balance in the books', s.book_balance],
            ['Not yet on statement', s.outstanding_total],
            ['Books, adjusted', s.adjusted_book],
            ['Bank statement', s.statement_balance],
          ].map(([l, val]) => (
            <div key={l as string} className="rounded-lg bg-slate-50 p-3">
              <p className="text-xs text-slate-500">{l}</p>
              <p className="font-semibold">{taka(val as number)}</p>
            </div>
          ))}
        </div>
        <p className={cn('mt-2 text-sm font-medium', Math.abs(s.difference) < 0.005 ? 'text-brand-700' : 'text-red-600')}>
          {Math.abs(s.difference) < 0.005 ? (draft ? 'Balanced — match the remaining lines and complete.' : 'Reconciled.') : `Difference ${taka(s.difference)}`}
        </p>
        <p className="mt-1 text-xs text-slate-500">CSV columns: Date, Description, Amount (+ in / − out) — or Date, Description, Debit, Credit.</p>
      </Card>

      <div className="mt-4 grid gap-4 lg:grid-cols-[1fr_380px]">
        <Card className="p-5">
          <h3 className="font-semibold text-slate-900">Statement lines</h3>
          <ul className="mt-2 divide-y divide-slate-100 text-sm">
            {rec.lines.map((l) => (
              <li key={l.id} className="py-2.5">
                <div className="flex items-center justify-between gap-2">
                  <span>
                    {l.date} · {l.description}
                  </span>
                  <span className={cn('font-medium tabular-nums', l.amount < 0 ? 'text-red-600' : 'text-brand-700')}>{taka(l.amount)}</span>
                </div>
                <div className="mt-1 flex flex-wrap items-center gap-2 text-xs">
                  <Badge tone={l.status === 'unmatched' ? 'amber' : 'green'}>{l.status}</Badge>
                  {l.matched && <span className="text-slate-500">{l.matched.voucher_no} · {l.matched.narration}</span>}
                  {draft && l.status === 'unmatched' && (
                    <>
                      <button className="inline-flex items-center gap-1 text-sky-brand-600" onClick={() => setMatching(matching === l.id ? null : l.id)}>
                        <Link2 className="size-3.5" /> Match
                      </button>
                      <button className="inline-flex items-center gap-1 text-sky-brand-600" onClick={() => action.mutate({ path: `/accounts/statement-lines/${l.id}/adjust` })}>
                        <Calculator className="size-3.5" /> Book as {l.amount < 0 ? 'bank charge' : 'other income'}
                      </button>
                      <button className="text-slate-400 hover:text-red-600" onClick={() => action.mutate({ path: `/accounts/statement-lines/${l.id}/delete` })}>
                        Remove
                      </button>
                    </>
                  )}
                  {draft && l.status === 'matched' && (
                    <button className="inline-flex items-center gap-1 text-slate-500" onClick={() => action.mutate({ path: `/accounts/statement-lines/${l.id}/match`, body: { journal_line_id: null } })}>
                      <Unlink className="size-3.5" /> Unmatch
                    </button>
                  )}
                </div>
                {matching === l.id && (
                  <div className="mt-2 rounded-lg bg-slate-50 p-2">
                    {s.outstanding.filter((o) => Math.abs(o.amount - l.amount) < 0.005).map((o) => (
                      <button key={o.id} className="block w-full rounded px-2 py-1 text-left text-xs hover:bg-white" onClick={() => (setMatching(null), action.mutate({ path: `/accounts/statement-lines/${l.id}/match`, body: { journal_line_id: o.id } }))}>
                        {o.date} · {o.voucher_no} · {o.narration}
                      </button>
                    ))}
                    {!s.outstanding.some((o) => Math.abs(o.amount - l.amount) < 0.005) && <p className="text-xs text-slate-500">No open book entry with this amount.</p>}
                  </div>
                )}
              </li>
            ))}
          </ul>
          {draft && (
            <div className="mt-3 grid gap-2 sm:grid-cols-[150px_1fr_130px_auto]">
              <Input type="date" aria-label="Date" value={line.date} onChange={(e) => setLine({ ...line, date: e.target.value })} />
              <Input aria-label="Description" placeholder="Description" value={line.description} onChange={(e) => setLine({ ...line, description: e.target.value })} />
              <Input aria-label="Amount" type="number" placeholder="+ in / − out" value={line.amount} onChange={(e) => setLine({ ...line, amount: e.target.value })} />
              <Button
                variant="secondary"
                disabled={!line.description || !Number(line.amount)}
                onClick={() => action.mutate({ path: `/accounts/reconciliations/${rec.id}/lines`, body: { ...line, amount: Number(line.amount) } }, { onSuccess: () => setLine({ ...line, description: '', amount: '' }) })}
              >
                Add line
              </Button>
            </div>
          )}
        </Card>
        <Card className="h-fit p-5">
          <h3 className="font-semibold text-slate-900">In the books, not on the statement</h3>
          <p className="text-xs text-slate-500">Cheques not yet cleared, deposits in transit…</p>
          <ul className="mt-2 divide-y divide-slate-100 text-xs">
            {s.outstanding.map((o) => (
              <li key={o.id} className="flex justify-between gap-2 py-1.5">
                <span className="truncate">
                  {o.date} · {o.narration}
                </span>
                <span className="tabular-nums">{taka(o.amount)}</span>
              </li>
            ))}
            {s.outstanding.length === 0 && <li className="py-1.5 text-slate-500">Nothing outstanding.</li>}
          </ul>
        </Card>
      </div>
    </>
  )
}

// ================================================================================================
// Fixed assets (Accounts §১০)
// ================================================================================================

interface AssetRow {
  id: number
  asset_code: string
  name: string
  location: string | null
  status: 'active' | 'disposed'
  purchase_date: string
  cost: number
  accumulated_depreciation: number
  book_value: number
  monthly_depreciation: number
  useful_life_months: number
  depreciated_until: string | null
  category: { id: number; name: string }
  branch: { id: number; name: string }
}

export function FixedAssetsPage() {
  const { can } = useAuth()
  const invalidate = useInvalidate()
  const { data } = useQuery({
    queryKey: ['assets'],
    queryFn: async () => (await api.get<{ data: AssetRow[]; categories: { id: number; code: string; name: string }[]; totals: { cost: number; book_value: number } }>('/accounts/fixed-assets')).data,
  })
  const [adding, setAdding] = useState(false)
  const [month, setMonth] = useState(todayISO().slice(0, 7))
  const depreciate = useMutation({
    mutationFn: async () => (await api.post<{ data: { assets: number; amount: number; voucher_no: string | null } }>('/accounts/fixed-assets/depreciate', { month })).data.data,
    onSuccess: invalidate,
  })
  const [disposing, setDisposing] = useState<AssetRow | null>(null)

  return (
    <>
      <PageHeader
        title="Fixed assets"
        description="Equipment, furniture and computers — depreciated straight-line every month."
        actions={
          can('accounts.voucher.create') && (
            <Button onClick={() => setAdding(true)}>
              <Plus className="size-4" /> Asset
            </Button>
          )
        }
      />
      {can('accounts.voucher.create') && (
        <Card className="mb-4 flex flex-wrap items-center gap-2 p-3">
          <span className="text-sm text-slate-600">Charge depreciation up to</span>
          <Input type="month" max={todayISO().slice(0, 7)} value={month} onChange={(e) => setMonth(e.target.value)} className="w-44" aria-label="Month" />
          <Button variant="secondary" loading={depreciate.isPending} onClick={() => depreciate.mutate()}>
            Run
          </Button>
          {depreciate.data && (
            <span className="text-sm text-brand-700">
              {depreciate.data.amount ? `${taka(depreciate.data.amount)} for ${depreciate.data.assets} assets (${depreciate.data.voucher_no})` : 'Already up to date.'}
            </span>
          )}
        </Card>
      )}
      {!data ? (
        <Spinner className="text-brand-600" />
      ) : (
        <Card className="overflow-x-auto">
          <table className="w-full min-w-[760px] text-sm">
            <thead className="bg-slate-50 text-left text-xs text-slate-500">
              <tr>
                <th className="px-4 py-2 font-medium">Asset</th>
                <th className="px-3 py-2 font-medium">Bought</th>
                <th className="px-3 py-2 text-right font-medium">Cost</th>
                <th className="px-3 py-2 text-right font-medium">Depreciated</th>
                <th className="px-3 py-2 text-right font-medium">Book value</th>
                <th className="px-3 py-2 text-right font-medium">Per month</th>
                <th className="px-3 py-2" />
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {data.data.map((a) => (
                <tr key={a.id} className={a.status === 'disposed' ? 'opacity-50' : ''}>
                  <td className="px-4 py-2">
                    <p className="font-medium text-slate-900">{a.name}</p>
                    <p className="text-xs text-slate-500">
                      {a.asset_code} · {a.category.name}
                      {a.location && ` · ${a.location}`}
                    </p>
                  </td>
                  <td className="px-3 py-2 text-slate-600">{a.purchase_date}</td>
                  <td className="px-3 py-2 text-right tabular-nums">{taka(a.cost)}</td>
                  <td className="px-3 py-2 text-right tabular-nums">{taka(a.accumulated_depreciation)}</td>
                  <td className="px-3 py-2 text-right font-medium tabular-nums">{taka(a.book_value)}</td>
                  <td className="px-3 py-2 text-right text-xs tabular-nums text-slate-500">
                    {taka(a.monthly_depreciation)}
                    <span className="block">{a.useful_life_months} mo life</span>
                  </td>
                  <td className="px-3 py-2 text-right">
                    {a.status === 'disposed' ? (
                      <Badge>disposed</Badge>
                    ) : (
                      can('accounts.voucher.approve') && (
                        <button className="text-xs text-slate-400 hover:text-red-600" onClick={() => setDisposing(a)}>
                          Sell / write off
                        </button>
                      )
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
            <tfoot className="border-t border-slate-200 font-semibold">
              <tr>
                <td className="px-4 py-2" colSpan={2}>
                  In use
                </td>
                <td className="px-3 py-2 text-right">{taka(data.totals.cost)}</td>
                <td />
                <td className="px-3 py-2 text-right">{taka(data.totals.book_value)}</td>
                <td colSpan={2} />
              </tr>
            </tfoot>
          </table>
        </Card>
      )}
      {adding && data && <AssetModal categories={data.categories} onClose={() => setAdding(false)} />}
      {disposing && <DisposeModal asset={disposing} onClose={() => setDisposing(null)} />}
    </>
  )
}

/** Sale or write-off: the difference from book value is booked as a gain or a loss. */
function DisposeModal({ asset, onClose }: { asset: AssetRow; onClose: () => void }) {
  const invalidate = useInvalidate()
  const { data: options } = useExpenseOptions(asset.branch.id)
  const [v, setV] = useState({ date: todayISO(), amount: '', received_in_account_id: '', reason: '' })
  const into = v.received_in_account_id || String(options?.paid_from[0]?.id ?? '')
  const received = Number(v.amount) || 0
  const save = useMutation({
    mutationFn: () => api.post(`/accounts/fixed-assets/${asset.id}/dispose`, { date: v.date, amount: received, received_in_account_id: received > 0 ? Number(into) : null, reason: v.reason || null }),
    onSuccess: () => (invalidate(), onClose()),
  })
  const diff = received - asset.book_value

  return (
    <Modal open title={`Sell / write off — ${asset.name}`} onClose={onClose}>
      <div className="space-y-4">
        {save.isError && <Alert>{err(save.error)}</Alert>}
        <p className="text-sm text-slate-600">Book value today {taka(asset.book_value)}.</p>
        <div className="grid grid-cols-2 gap-3">
          <Field label="Sold for (৳, 0 if thrown away)" htmlFor="dp_amount">
            <Input id="dp_amount" type="number" min={0} value={v.amount} onChange={(e) => setV({ ...v, amount: e.target.value })} />
          </Field>
          <Field label="Date" htmlFor="dp_date">
            <Input id="dp_date" type="date" max={todayISO()} value={v.date} onChange={(e) => setV({ ...v, date: e.target.value })} />
          </Field>
        </div>
        {received > 0 && (
          <Field label="Money received in" htmlFor="dp_into">
            <Select id="dp_into" value={into} onChange={(e) => setV({ ...v, received_in_account_id: e.target.value })}>
              {options?.paid_from.map((a) => (
                <option key={a.id} value={a.id}>
                  {a.name}
                </option>
              ))}
            </Select>
          </Field>
        )}
        <Field label="Reason" htmlFor="dp_reason">
          <Input id="dp_reason" value={v.reason} onChange={(e) => setV({ ...v, reason: e.target.value })} />
        </Field>
        <p className={cn('text-sm', diff >= 0 ? 'text-brand-700' : 'text-red-600')}>{diff >= 0 ? `Gain ${taka(diff)}` : `Loss ${taka(-diff)}`}</p>
        <div className="flex justify-end gap-2">
          <Button variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button variant="danger" loading={save.isPending} onClick={() => save.mutate()}>
            Confirm
          </Button>
        </div>
      </div>
    </Modal>
  )
}

function AssetModal({ categories, onClose }: { categories: { id: number; code: string; name: string }[]; onClose: () => void }) {
  const { user } = useAuth()
  const branchId = user?.branches?.[0]?.id ?? 0
  const invalidate = useInvalidate()
  const { data: options } = useExpenseOptions(branchId)
  const [v, setV] = useState({ name: '', account_id: String(categories[0]?.id ?? ''), location: '', purchase_date: todayISO(), cost: '', salvage_value: '', useful_life_months: '60', how: 'paid', paid_from_account_id: '' })
  const from = v.paid_from_account_id || String(options?.paid_from.find((a) => a.subtype === 'bank')?.id ?? '')
  const save = useMutation({
    mutationFn: () =>
      api.post('/accounts/fixed-assets', {
        name: v.name, account_id: Number(v.account_id), branch_id: branchId, location: v.location || null, purchase_date: v.purchase_date,
        cost: Number(v.cost), salvage_value: Number(v.salvage_value) || 0, useful_life_months: Number(v.useful_life_months),
        paid_from_account_id: v.how === 'paid' ? Number(from) : null,
      }),
    onSuccess: () => (invalidate(), onClose()),
  })

  return (
    <Modal open title="Register an asset" onClose={onClose}>
      <div className="space-y-4">
        {save.isError && <Alert>{err(save.error)}</Alert>}
        <Field label="Name" htmlFor="fa_name">
          <Input id="fa_name" value={v.name} onChange={(e) => setV({ ...v, name: e.target.value })} placeholder="Therapy swing, laptop…" />
        </Field>
        <div className="grid grid-cols-2 gap-3">
          <Field label="Category" htmlFor="fa_cat">
            <Select id="fa_cat" value={v.account_id} onChange={(e) => setV({ ...v, account_id: e.target.value })}>
              {categories.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.name}
                </option>
              ))}
            </Select>
          </Field>
          <Field label="Location" htmlFor="fa_loc">
            <Input id="fa_loc" value={v.location} onChange={(e) => setV({ ...v, location: e.target.value })} />
          </Field>
          <Field label="Bought on" htmlFor="fa_date">
            <Input id="fa_date" type="date" max={todayISO()} value={v.purchase_date} onChange={(e) => setV({ ...v, purchase_date: e.target.value })} />
          </Field>
          <Field label="Cost (৳)" htmlFor="fa_cost">
            <Input id="fa_cost" type="number" min={1} value={v.cost} onChange={(e) => setV({ ...v, cost: e.target.value })} />
          </Field>
          <Field label="Useful life (months)" htmlFor="fa_life">
            <Input id="fa_life" type="number" min={1} value={v.useful_life_months} onChange={(e) => setV({ ...v, useful_life_months: e.target.value })} />
          </Field>
          <Field label="Value at end (৳)" htmlFor="fa_salv">
            <Input id="fa_salv" type="number" min={0} value={v.salvage_value} onChange={(e) => setV({ ...v, salvage_value: e.target.value })} />
          </Field>
        </div>
        <Field label="How was it paid?" htmlFor="fa_how">
          <Select id="fa_how" value={v.how} onChange={(e) => setV({ ...v, how: e.target.value })}>
            <option value="paid">Paid now from…</option>
            <option value="opening">Already owned (in the go-live opening balance)</option>
          </Select>
        </Field>
        {v.how === 'paid' && (
          <Select aria-label="Paid from" value={from} onChange={(e) => setV({ ...v, paid_from_account_id: e.target.value })}>
            {options?.paid_from.map((a) => (
              <option key={a.id} value={a.id}>
                {a.name}
              </option>
            ))}
          </Select>
        )}
        <div className="flex justify-end gap-2">
          <Button variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button loading={save.isPending} disabled={!v.name || !Number(v.cost)} onClick={() => save.mutate()}>
            Register
          </Button>
        </div>
      </div>
    </Modal>
  )
}

// ================================================================================================
// Budget (Accounts §১২)
// ================================================================================================

interface BudgetList {
  data: { id: number; name: string; fiscal_year: string; fiscal_year_id: number; branch: { id: number; name: string } | null; total: number }[]
  years: { id: number; name: string; status: string }[]
  accounts: { id: number; code: string; name: string; type: 'income' | 'expense' }[]
}
interface VsActual {
  months: number
  income: { account: { id: number; code: string; name: string }; annual: number; budget: number; actual: number; variance: number; used_percent: number | null }[]
  expense: VsActual['income']
  totals: { income_budget: number; income_actual: number; expense_budget: number; expense_actual: number }
}

export function BudgetsPage() {
  const { can } = useAuth()
  const { data } = useQuery({ queryKey: ['budgets'], queryFn: async () => (await api.get<BudgetList>('/accounts/budgets')).data })
  const [selected, setSelected] = useState<number | null>(null)
  const [editing, setEditing] = useState<number | 'new' | null>(null)
  const current = selected ?? data?.data[0]?.id ?? null

  return (
    <>
      <PageHeader
        title="Budget"
        description="Plan the year by account, then compare with what actually happened."
        actions={
          can('accounts.coa.manage') && (
            <Button onClick={() => setEditing('new')}>
              <Plus className="size-4" /> Budget
            </Button>
          )
        }
      />
      {!data ? (
        <Spinner className="text-brand-600" />
      ) : data.data.length === 0 ? (
        <Card className="p-5 text-sm text-slate-500">No budget yet.</Card>
      ) : (
        <>
          <div className="mb-4 flex flex-wrap gap-2">
            {data.data.map((b) => (
              <button key={b.id} onClick={() => setSelected(b.id)} className={cn('rounded-lg px-3 py-2 text-sm', b.id === current ? 'bg-brand-600 text-white' : 'bg-white text-slate-700 ring-1 ring-slate-200')}>
                {b.name}
              </button>
            ))}
            {current && can('accounts.coa.manage') && (
              <Button variant="ghost" onClick={() => setEditing(current)}>
                Edit
              </Button>
            )}
          </div>
          {current && <VsActualView budgetId={current} />}
        </>
      )}
      {editing && data && <BudgetEditor list={data} budgetId={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />}
    </>
  )
}

function VsActualView({ budgetId }: { budgetId: number }) {
  const { data } = useQuery({ queryKey: ['budget', budgetId, 'vs'], queryFn: async () => (await api.get<{ data: VsActual }>(`/accounts/budgets/${budgetId}/vs-actual`)).data.data })
  if (!data) return <Spinner className="text-brand-600" />
  const section = (title: string, rows: VsActual['income'], income: boolean) => (
    <Card className="mb-4 overflow-x-auto">
      <p className="bg-slate-50 px-4 py-2 text-sm font-semibold text-slate-700">{title}</p>
      <table className="w-full min-w-[600px] text-sm">
        <thead className="text-left text-xs text-slate-500">
          <tr>
            <th className="px-4 py-2 font-medium">Account</th>
            <th className="px-3 py-2 text-right font-medium">Budget so far</th>
            <th className="px-3 py-2 text-right font-medium">Actual</th>
            <th className="px-3 py-2 text-right font-medium">Difference</th>
            <th className="w-40 px-3 py-2 font-medium">Used</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-100">
          {rows.map((r) => {
            const bad = income ? r.variance < 0 : r.variance > 0
            return (
              <tr key={r.account.id}>
                <td className="px-4 py-2">
                  <span className="text-slate-400">{r.account.code}</span> {r.account.name}
                </td>
                <td className="px-3 py-2 text-right tabular-nums">{taka(r.budget)}</td>
                <td className="px-3 py-2 text-right tabular-nums">{taka(r.actual)}</td>
                <td className={cn('px-3 py-2 text-right tabular-nums', bad ? 'text-red-600' : 'text-brand-700')}>{taka(r.variance)}</td>
                <td className="px-3 py-2">
                  <div className="h-2 overflow-hidden rounded-full bg-slate-100">
                    <div className={cn('h-full rounded-full', bad ? 'bg-red-400' : 'bg-brand-500')} style={{ width: `${Math.min(100, r.used_percent ?? 0)}%` }} />
                  </div>
                  <span className="text-xs text-slate-500">{r.used_percent ?? 0}%</span>
                </td>
              </tr>
            )
          })}
        </tbody>
      </table>
    </Card>
  )

  return (
    <>
      <p className="mb-3 text-sm text-slate-500">
        {data.months} month(s) of the year so far · income {taka(data.totals.income_actual)} of {taka(data.totals.income_budget)} · expenses {taka(data.totals.expense_actual)} of {taka(data.totals.expense_budget)}
      </p>
      {data.income.length > 0 && section('Income', data.income, true)}
      {data.expense.length > 0 && section('Expenses', data.expense, false)}
    </>
  )
}

function BudgetEditor({ list, budgetId, onClose }: { list: BudgetList; budgetId: number | null; onClose: () => void }) {
  const invalidate = useInvalidate()
  const { data: existing } = useQuery({
    queryKey: ['budget', budgetId],
    queryFn: async () => (await api.get<{ data: { name: string; fiscal_year_id: number; branch_id: number | null; lines: { account_id: number; annual_amount: number }[] } }>(`/accounts/budgets/${budgetId}`)).data.data,
    enabled: !!budgetId,
  })
  if (budgetId && !existing) return null
  return <BudgetForm key={budgetId ?? 'new'} list={list} budgetId={budgetId} existing={existing} onClose={onClose} invalidate={invalidate} />
}

function BudgetForm({ list, budgetId, existing, onClose, invalidate }: { list: BudgetList; budgetId: number | null; existing?: { name: string; fiscal_year_id: number; lines: { account_id: number; annual_amount: number }[] }; onClose: () => void; invalidate: () => void }) {
  const [name, setName] = useState(existing?.name ?? `Center budget ${list.years[0]?.name ?? ''}`)
  const [yearId, setYearId] = useState(existing?.fiscal_year_id ?? list.years[0]?.id ?? 0)
  const [amounts, setAmounts] = useState<Record<number, string>>(() => Object.fromEntries((existing?.lines ?? []).map((l) => [l.account_id, String(l.annual_amount)])))
  const save = useMutation({
    mutationFn: () =>
      budgetId
        ? api.put(`/accounts/budgets/${budgetId}`, body())
        : api.post('/accounts/budgets', body()),
    onSuccess: () => (invalidate(), onClose()),
  })
  const body = () => ({ name, fiscal_year_id: yearId, branch_id: null, lines: Object.entries(amounts).filter(([, a]) => Number(a) > 0).map(([id, a]) => ({ account_id: Number(id), annual_amount: Number(a) })) })

  return (
    <Modal open wide title={budgetId ? 'Edit budget' : 'New budget'} onClose={onClose}>
      <div className="space-y-4">
        {save.isError && <Alert>{err(save.error)}</Alert>}
        <div className="grid gap-3 sm:grid-cols-2">
          <Field label="Name" htmlFor="bg_name">
            <Input id="bg_name" value={name} onChange={(e) => setName(e.target.value)} />
          </Field>
          <Field label="Fiscal year (July–June)" htmlFor="bg_year">
            <Select id="bg_year" value={yearId} onChange={(e) => setYearId(Number(e.target.value))}>
              {list.years.map((y) => (
                <option key={y.id} value={y.id}>
                  {y.name}
                </option>
              ))}
            </Select>
          </Field>
        </div>
        <p className="text-xs text-slate-500">Annual amount per account — spread evenly across the twelve months. Leave blank to skip.</p>
        <div className="max-h-96 overflow-y-auto rounded-lg border border-slate-100">
          {(['income', 'expense'] as const).map((type) => (
            <div key={type}>
              <p className="sticky top-0 bg-slate-50 px-3 py-1.5 text-xs font-semibold uppercase text-slate-500">{type}</p>
              {list.accounts
                .filter((a) => a.type === type)
                .map((a) => (
                  <label key={a.id} className="flex items-center justify-between gap-3 px-3 py-1.5 text-sm">
                    <span>
                      <span className="text-slate-400">{a.code}</span> {a.name}
                    </span>
                    <Input type="number" min={0} className="w-36" value={amounts[a.id] ?? ''} onChange={(e) => setAmounts({ ...amounts, [a.id]: e.target.value })} aria-label={a.name} />
                  </label>
                ))}
            </div>
          ))}
        </div>
        <div className="flex justify-end gap-2">
          <Button variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button loading={save.isPending} disabled={!name} onClick={() => save.mutate()}>
            Save budget
          </Button>
        </div>
      </div>
    </Modal>
  )
}
