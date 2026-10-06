import { Paperclip, Plus, Send } from 'lucide-react'
import { useState } from 'react'
import { errorMessage, validationErrors } from '../../api/client'
import { Button } from '../../components/ui/Button'
import { Alert, Badge, Card, PageHeader } from '../../components/ui/Card'
import { Field, Input, Select } from '../../components/ui/Field'
import { Modal } from '../../components/ui/Modal'
import { Spinner } from '../../components/ui/Spinner'
import { useAuth } from '../../contexts/useAuth'
import { todayISO } from '../../utils/format'
import { taka } from '../billing/api'
import { expenseAttachmentUrl, useAccountsMutations, useExpenseOptions, useExpenses, voucherStatusTone } from './api'

const statusLabel = { draft: 'Draft', submitted: 'Waiting approval', posted: 'Posted', rejected: 'Rejected', reversed: 'Reversed' } as const

/** Accounts §৫: "Electricity ৳5,000 from Cash" — the voucher is written automatically. */
export default function ExpensesPage() {
  const { can } = useAuth()
  const [status, setStatus] = useState('')
  const [from, setFrom] = useState(todayISO().slice(0, 8) + '01')
  const [to, setTo] = useState(todayISO())
  const [adding, setAdding] = useState(false)
  const { data, isLoading } = useExpenses({ status: status || undefined, from, to })
  const { submitExpense } = useAccountsMutations()
  const total = data?.data.filter((e) => e.status === 'posted').reduce((s, e) => s + e.amount, 0) ?? 0

  return (
    <>
      <PageHeader
        title="Expenses"
        description={can('accounts.voucher.create') ? 'Expenses up to the approval limit post at once; larger ones wait for a second person.' : 'Small expenses paid from the cash box.'}
        actions={
          <Button onClick={() => setAdding(true)}>
            <Plus className="size-4" /> Add expense
          </Button>
        }
      />
      <Card className="mb-4 flex flex-col gap-2 p-3 sm:flex-row sm:items-center">
        <Input type="date" value={from} onChange={(e) => setFrom(e.target.value)} className="sm:w-44" aria-label="From" />
        <Input type="date" value={to} onChange={(e) => setTo(e.target.value)} className="sm:w-44" aria-label="To" />
        <Select value={status} onChange={(e) => setStatus(e.target.value)} className="sm:w-48" aria-label="Status">
          <option value="">All</option>
          <option value="posted">Posted</option>
          <option value="submitted">Waiting approval</option>
          <option value="draft">Draft</option>
          <option value="rejected">Rejected</option>
        </Select>
        <span className="text-sm text-slate-600 sm:ml-auto">
          Posted total <b>{taka(total)}</b>
        </span>
      </Card>

      {isLoading ? (
        <Spinner className="text-brand-600" />
      ) : !data?.data.length ? (
        <Card className="p-5 text-sm text-slate-500">No expenses in this period.</Card>
      ) : (
        <Card className="divide-y divide-slate-100">
          {data.data.map((e) => (
            <div key={e.id} className="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
              <div className="min-w-0">
                <p className="font-medium text-slate-900">
                  {e.category.name}
                  {e.payee && <span className="font-normal text-slate-500"> · {e.payee}</span>}
                </p>
                <p className="text-xs text-slate-500">
                  {e.date} · {e.expense_no} · {e.voucher_no} · from {e.paid_from.name} · {e.created_by?.name}
                </p>
                {e.description && <p className="text-xs text-slate-600">{e.description}</p>}
                {e.reject_reason && e.status === 'rejected' && <p className="text-xs text-red-600">Rejected: {e.reject_reason}</p>}
              </div>
              <div className="flex items-center gap-3">
                {e.has_attachment && (
                  <a href={expenseAttachmentUrl(e.id)} target="_blank" rel="noreferrer" aria-label="Bill" className="text-slate-400 hover:text-slate-700">
                    <Paperclip className="size-4" />
                  </a>
                )}
                <Badge tone={voucherStatusTone[e.status]}>{statusLabel[e.status]}</Badge>
                <span className="w-24 text-right font-semibold text-slate-900">{taka(e.amount)}</span>
                {e.status === 'draft' && can('accounts.voucher.create') && (
                  <Button variant="secondary" loading={submitExpense.isPending} onClick={() => submitExpense.mutate(e.id)}>
                    <Send className="size-4" /> Post
                  </Button>
                )}
              </div>
            </div>
          ))}
        </Card>
      )}
      {adding && <ExpenseModal onClose={() => setAdding(false)} />}
    </>
  )
}

function ExpenseModal({ onClose }: { onClose: () => void }) {
  const { user } = useAuth()
  const branches = user?.branches ?? []
  const [branchId, setBranchId] = useState(branches[0]?.id ?? 0)
  const { data: options } = useExpenseOptions(branchId)
  const { saveExpense } = useAccountsMutations()
  const [v, setV] = useState({ date: todayISO(), expense_category_id: '', amount: '', paid_from_account_id: '', payee: '', reference: '', description: '' })
  const [file, setFile] = useState<File | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [done, setDone] = useState<string | null>(null)
  const paidFrom = v.paid_from_account_id || String(options?.paid_from[0]?.id ?? '')

  const save = async () => {
    setError(null)
    const form = new FormData()
    Object.entries({ ...v, paid_from_account_id: paidFrom, branch_id: String(branchId) }).forEach(([k, val]) => val && form.append(k, val))
    if (file) form.append('attachment', file)
    try {
      const saved = await saveExpense.mutateAsync(form)
      setDone(saved.status === 'posted' ? `${saved.expense_no} posted.` : `${saved.expense_no} saved — waiting for approval.`)
    } catch (e) {
      setError(Object.values(validationErrors(e))[0] ?? errorMessage(e))
    }
  }

  return (
    <Modal open title="Add expense" onClose={onClose}>
      {done ? (
        <div className="space-y-4">
          <Alert tone="green">{done}</Alert>
          <div className="flex justify-end">
            <Button onClick={onClose}>Done</Button>
          </div>
        </div>
      ) : (
        <div className="space-y-4">
          {error && <Alert>{error}</Alert>}
          <div className="grid gap-3 sm:grid-cols-2">
            <Field label="Date" htmlFor="ex_date">
              <Input id="ex_date" type="date" max={todayISO()} value={v.date} onChange={(e) => setV({ ...v, date: e.target.value })} />
            </Field>
            <Field label="Branch" htmlFor="ex_branch">
              <Select id="ex_branch" value={branchId} onChange={(e) => setBranchId(Number(e.target.value))}>
                {branches.map((b) => (
                  <option key={b.id} value={b.id}>
                    {b.name}
                  </option>
                ))}
              </Select>
            </Field>
          </div>
          <Field label="What for?" htmlFor="ex_cat">
            <Select id="ex_cat" value={v.expense_category_id} onChange={(e) => setV({ ...v, expense_category_id: e.target.value })}>
              <option value="">Choose…</option>
              {options?.categories.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.name}
                  {c.name_bn ? ` — ${c.name_bn}` : ''}
                </option>
              ))}
            </Select>
          </Field>
          <div className="grid gap-3 sm:grid-cols-2">
            <Field label="Amount (৳)" htmlFor="ex_amount">
              <Input id="ex_amount" type="number" min={1} inputMode="decimal" value={v.amount} onChange={(e) => setV({ ...v, amount: e.target.value })} />
            </Field>
            <Field label="Paid from" htmlFor="ex_from">
              <Select id="ex_from" value={paidFrom} onChange={(e) => setV({ ...v, paid_from_account_id: e.target.value })}>
                {options?.paid_from.map((a) => (
                  <option key={a.id} value={a.id}>
                    {a.name}
                  </option>
                ))}
              </Select>
            </Field>
          </div>
          <div className="grid gap-3 sm:grid-cols-2">
            <Field label="Paid to (optional)" htmlFor="ex_payee">
              <Input id="ex_payee" value={v.payee} onChange={(e) => setV({ ...v, payee: e.target.value })} placeholder="Shop, company, person" />
            </Field>
            <Field label="Bill / transaction no." htmlFor="ex_ref">
              <Input id="ex_ref" value={v.reference} onChange={(e) => setV({ ...v, reference: e.target.value })} />
            </Field>
          </div>
          <Field label="Note" htmlFor="ex_desc">
            <Input id="ex_desc" value={v.description} onChange={(e) => setV({ ...v, description: e.target.value })} />
          </Field>
          <Field label="Photo of the bill (optional)" htmlFor="ex_file">
            <input id="ex_file" type="file" accept="image/*,application/pdf" capture="environment" onChange={(e) => setFile(e.target.files?.[0] ?? null)} className="text-sm" />
          </Field>
          <div className="flex justify-end gap-2">
            <Button variant="secondary" onClick={onClose}>
              Cancel
            </Button>
            <Button loading={saveExpense.isPending} disabled={!v.expense_category_id || !Number(v.amount) || !paidFrom} onClick={save}>
              Save {Number(v.amount) > 0 ? taka(Number(v.amount)) : ''}
            </Button>
          </div>
        </div>
      )}
    </Modal>
  )
}
