import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router'
import { api, errorMessage, validationErrors } from '../../api/client'
import { Button } from '../../components/ui/Button'
import { Alert, Badge, Card, PageHeader } from '../../components/ui/Card'
import { Field, Input, Select } from '../../components/ui/Field'
import { Modal } from '../../components/ui/Modal'
import { Pager } from '../../components/ui/Pager'
import { Spinner } from '../../components/ui/Spinner'
import { Stat } from '../../components/ui/Stat'
import { useAuth } from '../../contexts/useAuth'
import { cn } from '../../utils/cn'
import { taka } from '../billing/api'
import { useBranches } from '../branches/api'

interface BillRow {
  id: number
  no: string
  vendor_ref: string | null
  vendor: { id: number; name: string }
  date: string
  due_date: string | null
  total: number
  paid: number
  due: number
  status: string
  overdue: boolean
  description: string | null
}

const billTone = (b: BillRow) => (b.status === 'paid' ? 'green' : b.status === 'void' ? 'gray' : b.overdue ? 'red' : 'amber')
const billLabel = (b: BillRow) => (b.status === 'paid' ? 'Paid' : b.status === 'void' ? 'Void' : b.overdue ? 'Overdue' : b.status === 'partially_paid' ? 'Part paid' : 'Unpaid')

/** Accounts → Bills / Payables: every vendor bill; pay or void from the vendor’s page. */
export function BillsPage() {
  const [status, setStatus] = useState('open')
  const [page, setPage] = useState(1)
  const { data, isLoading } = useQuery({
    queryKey: ['vendor-bills', status, page],
    queryFn: async () => (await api.get<{ data: BillRow[]; meta: { current_page: number; last_page: number; total: number }; summary: { owed: number; overdue: number } }>('/accounts/vendor-bills', { params: { status, page } })).data,
  })

  return (
    <>
      <PageHeader title="Bills / Payables" description="Bills from suppliers, landlord and service providers. Record or pay a bill from the vendor’s page." />
      {data && (
        <div className="mb-4 grid gap-3 sm:grid-cols-2">
          <Stat label="We owe vendors" value={taka(data.summary.owed)} to="/app/accounts/vendors" />
          <Stat label="Of which overdue" value={taka(data.summary.overdue)} tone={data.summary.overdue ? 'red' : undefined} />
        </div>
      )}
      <Card className="mb-4 p-3">
        <Select value={status} onChange={(e) => (setStatus(e.target.value), setPage(1))} className="sm:w-48" aria-label="Show">
          <option value="open">Not fully paid</option>
          <option value="overdue">Overdue</option>
          <option value="paid">Paid</option>
          <option value="void">Void</option>
          <option value="all">All bills</option>
        </Select>
      </Card>
      <Card className="overflow-hidden">
        {isLoading ? (
          <Spinner className="m-5 text-brand-600" />
        ) : !data?.data.length ? (
          <p className="p-5 text-sm text-slate-500">No bills here.</p>
        ) : (
          <ul className="divide-y divide-slate-100">
            {data.data.map((b) => (
              <li key={b.id} className={cn('flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm', b.status === 'void' && 'opacity-60')}>
                <div>
                  <p>
                    <span className="font-medium text-slate-900">{b.no}</span>{' '}
                    <Link to={`/app/accounts/vendors/${b.vendor.id}`} className="text-slate-700 hover:text-brand-700">
                      · {b.vendor.name}
                    </Link>
                    {b.vendor_ref && <span className="text-xs text-slate-500"> · their no. {b.vendor_ref}</span>}
                  </p>
                  <p className="text-xs text-slate-500">
                    {b.date}
                    {b.due_date && ` · due ${b.due_date}`}
                    {b.description && ` · ${b.description}`}
                  </p>
                </div>
                <span className="flex items-center gap-3">
                  <span className="text-right">
                    <b>{taka(b.due > 0 ? b.due : b.total)}</b>
                    {b.paid > 0 && b.due > 0 && <span className="block text-xs text-slate-500">of {taka(b.total)}</span>}
                  </span>
                  <Badge tone={billTone(b)}>{billLabel(b)}</Badge>
                </span>
              </li>
            ))}
          </ul>
        )}
        <Pager meta={data?.meta} onPage={setPage} />
      </Card>
    </>
  )
}

interface MoneyAccount {
  id: number
  code: string
  name: string
  name_bn: string | null
  kind: 'cash' | 'bank' | 'mfs'
  is_active: boolean
  branch: string | null
  balance: number
  last_activity: string | null
  reconciled_to: string | null
}

const kindLabel = { cash: 'Cash', bank: 'Bank', mfs: 'bKash / Nagad' }

/** Accounts → Bank Accounts: cash boxes, bank and mobile money accounts with their book balance. */
export function BankAccountsPage() {
  const { can } = useAuth()
  const [adding, setAdding] = useState(false)
  const { data, isLoading } = useQuery({ queryKey: ['money-accounts'], queryFn: async () => (await api.get<{ data: MoneyAccount[] }>('/accounts/money-accounts')).data.data })
  const totals = (['cash', 'bank', 'mfs'] as const).map((k) => [k, (data ?? []).filter((a) => a.kind === k).reduce((s, a) => s + a.balance, 0)] as const)

  return (
    <>
      <PageHeader
        title="Bank Accounts"
        description="Balances are from the books. Match them with the bank statement in Bank Reconciliation."
        actions={
          can('accounts.coa.manage') && (
            <Button onClick={() => setAdding(true)}>
              <Plus className="size-4" /> Add bank / bKash account
            </Button>
          )
        }
      />
      <div className="mb-4 grid gap-3 sm:grid-cols-3">
        {totals.map(([k, v]) => (
          <Stat key={k} label={`${kindLabel[k]} — total`} value={taka(v)} />
        ))}
      </div>
      <Card className="overflow-x-auto">
        {isLoading ? (
          <Spinner className="m-5 text-brand-600" />
        ) : (
          <table className="w-full text-sm">
            <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
              <tr>
                <th className="px-4 py-2.5 font-medium">Account</th>
                <th className="px-4 py-2.5 font-medium">Kind</th>
                <th className="px-4 py-2.5 font-medium">Last entry</th>
                <th className="px-4 py-2.5 font-medium">Reconciled to</th>
                <th className="px-4 py-2.5 text-right font-medium">Balance</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {data?.map((a) => (
                <tr key={a.id} className={cn(!a.is_active && 'opacity-50')}>
                  <td className="px-4 py-2.5">
                    <Link to={`/app/accounts/reports?r=ledger&account=${a.id}`} className="font-medium text-slate-900 hover:text-brand-700">
                      {a.name}
                    </Link>
                    <p className="text-xs text-slate-500">
                      {a.code}
                      {a.branch && ` · ${a.branch}`}
                    </p>
                  </td>
                  <td className="px-4 py-2.5">
                    <Badge tone={a.kind === 'bank' ? 'blue' : a.kind === 'mfs' ? 'amber' : 'green'}>{kindLabel[a.kind]}</Badge>
                  </td>
                  <td className="px-4 py-2.5 text-slate-600">{a.last_activity ? new Date(a.last_activity).toLocaleDateString('en-GB', { dateStyle: 'medium' }) : '—'}</td>
                  <td className="px-4 py-2.5 text-slate-600">
                    {a.kind === 'cash' ? <span className="text-xs text-slate-400">daily cash closing</span> : (a.reconciled_to ?? <Link to="/app/accounts/reconciliation" className="text-xs text-amber-700 hover:underline">never — reconcile</Link>)}
                  </td>
                  <td className={cn('px-4 py-2.5 text-right font-medium', a.balance < 0 && 'text-red-700')}>{taka(a.balance)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </Card>
      {adding && <AddMoneyAccount onClose={() => setAdding(false)} />}
    </>
  )
}

function AddMoneyAccount({ onClose }: { onClose: () => void }) {
  const qc = useQueryClient()
  const { data: branches } = useBranches()
  const [v, setV] = useState({ kind: 'bank', name: '', name_bn: '', branch_id: '' })
  const [errors, setErrors] = useState<Record<string, string>>({})
  const save = useMutation({
    mutationFn: () => api.post('/accounts/money-accounts', { ...v, branch_id: v.branch_id ? Number(v.branch_id) : null, name_bn: v.name_bn || null }),
    onSuccess: () => (qc.invalidateQueries({ queryKey: ['money-accounts'] }), qc.invalidateQueries({ queryKey: ['ledger'] }), onClose()),
    onError: (e) => setErrors(validationErrors(e)),
  })

  return (
    <Modal open title="Add a bank or mobile money account" onClose={onClose}>
      <div className="space-y-3">
        {save.isError && !Object.keys(errors).length && <Alert>{errorMessage(save.error)}</Alert>}
        <Field label="Kind" htmlFor="ma_kind">
          <Select id="ma_kind" value={v.kind} onChange={(e) => setV({ ...v, kind: e.target.value })}>
            <option value="bank">Bank account</option>
            <option value="mfs">bKash / Nagad / Rocket</option>
          </Select>
        </Field>
        <Field label="Name" htmlFor="ma_name" error={errors.name} hint="Include the bank and last digits, e.g. “DBBL — Current 4521”">
          <Input id="ma_name" value={v.name} onChange={(e) => setV({ ...v, name: e.target.value })} />
        </Field>
        <Field label="Name in Bangla" htmlFor="ma_bn">
          <Input id="ma_bn" value={v.name_bn} onChange={(e) => setV({ ...v, name_bn: e.target.value })} />
        </Field>
        <Field label="Branch (optional)" htmlFor="ma_branch">
          <Select id="ma_branch" value={v.branch_id} onChange={(e) => setV({ ...v, branch_id: e.target.value })}>
            <option value="">Whole center</option>
            {branches?.map((b) => (
              <option key={b.id} value={b.id}>
                {b.name}
              </option>
            ))}
          </Select>
        </Field>
        <p className="text-xs text-slate-500">An opening balance is entered with a Journal Voucher (decision A9).</p>
        <div className="flex justify-end gap-2">
          <Button variant="ghost" onClick={onClose}>
            Cancel
          </Button>
          <Button loading={save.isPending} disabled={!v.name} onClick={() => save.mutate()}>
            Add account
          </Button>
        </div>
      </div>
    </Modal>
  )
}
