import { ArrowLeft, Check, FileDown, Plus, RefreshCw, Undo2, Wallet } from 'lucide-react'
import { useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router'
import { errorMessage, validationErrors } from '../../api/client'
import { Button } from '../../components/ui/Button'
import { Alert, Badge, Card, PageHeader } from '../../components/ui/Card'
import { Field, Input, Select } from '../../components/ui/Field'
import { Modal } from '../../components/ui/Modal'
import { Spinner } from '../../components/ui/Spinner'
import { useAuth } from '../../contexts/useAuth'
import { cn } from '../../utils/cn'
import { todayISO } from '../../utils/format'
import { useExpenseOptions } from '../accounts/api'
import { taka } from '../billing/api'
import { departmentLabel, payslipUrl, salarySheetUrl, useMyPayslips, usePayrollMutations, usePayrollRun, usePayrollRuns, type PayrollItem } from './api'

const statusTone = { draft: 'gray', posted: 'amber', paid: 'green' } as const

/** "2026-10-06" → "2026-09" (payroll is usually made for the month just ended). */
const previousMonth = (iso: string) => {
  const [y, m] = iso.split('-').map(Number)
  return m === 1 ? `${y - 1}-12` : `${y}-${String(m - 1).padStart(2, '0')}`
}
const statusLabel = { draft: 'Draft', posted: 'Approved — to pay', paid: 'Paid' } as const

/** Accounts §৭.৩: monthly salary and festival bonus runs. */
export default function PayrollPage() {
  const { can } = useAuth()
  const navigate = useNavigate()
  const { data, isLoading } = usePayrollRuns()
  const [creating, setCreating] = useState(false)

  return (
    <>
      <PageHeader
        title="Payroll"
        description="Prepared by accounts, approved by a second person, then paid."
        actions={
          can('accounts.payroll.manage') && (
            <Button onClick={() => setCreating(true)}>
              <Plus className="size-4" /> New payroll
            </Button>
          )
        }
      />
      {isLoading ? (
        <Spinner className="text-brand-600" />
      ) : !data?.length ? (
        <Card className="p-5 text-sm text-slate-500">No payroll yet.</Card>
      ) : (
        <Card className="divide-y divide-slate-100">
          {data.map((r) => (
            <Link key={r.id} to={`/app/payroll/${r.id}`} className="flex flex-wrap items-center justify-between gap-2 px-4 py-3 hover:bg-slate-50">
              <div>
                <p className="font-medium text-slate-900">{r.label}</p>
                <p className="text-xs text-slate-500">
                  {r.run_no} · {r.branch?.name} · {r.items_count} staff · prepared by {r.prepared_by?.name}
                  {r.approved_by && ` · approved by ${r.approved_by.name}`}
                </p>
              </div>
              <div className="flex items-center gap-3">
                <Badge tone={statusTone[r.status]}>{statusLabel[r.status]}</Badge>
                <span className="w-28 text-right font-semibold">{taka(r.total_net)}</span>
              </div>
            </Link>
          ))}
        </Card>
      )}
      {creating && <NewRunModal onClose={() => setCreating(false)} onCreated={(id) => navigate(`/app/payroll/${id}`)} />}
    </>
  )
}

function NewRunModal({ onClose, onCreated }: { onClose: () => void; onCreated: (id: number) => void }) {
  const { user } = useAuth()
  const { createRun } = usePayrollMutations()
  const [v, setV] = useState(() => ({
    month: previousMonth(todayISO()),
    branch_id: user?.branches?.[0]?.id ?? 0,
    type: 'salary',
    title: '',
  }))
  const [error, setError] = useState<string | null>(null)

  const save = async () => {
    setError(null)
    try {
      const run = await createRun.mutateAsync({ ...v, title: v.type === 'bonus' ? v.title : null })
      onCreated(run.id)
    } catch (e) {
      setError(Object.values(validationErrors(e))[0] ?? errorMessage(e))
    }
  }

  return (
    <Modal open title="New payroll" onClose={onClose}>
      <div className="space-y-4">
        {error && <Alert>{error}</Alert>}
        <div className="grid grid-cols-2 gap-3">
          <Field label="Type" htmlFor="pr_type">
            <Select id="pr_type" value={v.type} onChange={(e) => setV({ ...v, type: e.target.value })}>
              <option value="salary">Monthly salary</option>
              <option value="bonus">Festival bonus</option>
            </Select>
          </Field>
          <Field label="Month" htmlFor="pr_month">
            <Input id="pr_month" type="month" max={todayISO().slice(0, 7)} value={v.month} onChange={(e) => setV({ ...v, month: e.target.value })} />
          </Field>
        </div>
        {v.type === 'bonus' && (
          <Field label="Title" htmlFor="pr_title" hint="One basic salary each, editable per person">
            <Input id="pr_title" value={v.title} onChange={(e) => setV({ ...v, title: e.target.value })} placeholder="Eid-ul-Adha bonus" />
          </Field>
        )}
        <Field label="Branch" htmlFor="pr_branch">
          <Select id="pr_branch" value={v.branch_id} onChange={(e) => setV({ ...v, branch_id: Number(e.target.value) })}>
            {user?.branches?.map((b) => (
              <option key={b.id} value={b.id}>
                {b.name}
              </option>
            ))}
          </Select>
        </Field>
        <p className="text-xs text-slate-500">Salaries, finalized therapy sessions and advance instalments are calculated automatically; you can adjust each person afterwards.</p>
        <div className="flex justify-end gap-2">
          <Button variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button loading={createRun.isPending} disabled={v.type === 'bonus' && !v.title} onClick={save}>
            Calculate
          </Button>
        </div>
      </div>
    </Modal>
  )
}

export function PayrollRunPage() {
  const { id } = useParams()
  const { data: run, isLoading } = usePayrollRun(Number(id))
  const { runAction } = usePayrollMutations()
  const [editing, setEditing] = useState<PayrollItem | null>(null)
  const [paying, setPaying] = useState(false)

  if (isLoading || !run) return <Spinner className="text-brand-600" />
  const unpaid = run.items.filter((i) => i.net_pay > 0 && !i.paid_at)

  return (
    <>
      <Link to="/app/payroll" className="mb-3 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-800">
        <ArrowLeft className="size-4" /> Payroll
      </Link>
      <Card className="p-5">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
          <div>
            <div className="flex items-center gap-2">
              <h1 className="text-xl font-semibold text-slate-900">{run.label}</h1>
              <Badge tone={statusTone[run.status]}>{statusLabel[run.status]}</Badge>
            </div>
            <p className="text-sm text-slate-500">
              {run.run_no} · {run.branch?.name} · prepared by {run.prepared_by?.name}
              {run.approved_by && ` · approved by ${run.approved_by.name}`}
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            {run.can.edit && (
              <Button variant="ghost" disabled={runAction.isPending} onClick={() => confirm('Recalculate everyone? Manual changes will be lost.') && runAction.mutate({ id: run.id, action: 'recalculate' })}>
                <RefreshCw className="size-4" /> Recalculate
              </Button>
            )}
            {run.can.approve && (
              <Button loading={runAction.isPending} onClick={() => confirm(`Approve and post ${taka(run.total_net)}?`) && runAction.mutate({ id: run.id, action: 'approve' })}>
                <Check className="size-4" /> Approve
              </Button>
            )}
            {run.can.pay && unpaid.length > 0 && (
              <Button onClick={() => setPaying(true)}>
                <Wallet className="size-4" /> Pay
              </Button>
            )}
            {run.can.reopen && (
              <Button
                variant="ghost"
                disabled={runAction.isPending}
                onClick={() => {
                  const reason = prompt('Why reopen this payroll?')
                  if (reason && reason.trim().length >= 5) runAction.mutate({ id: run.id, action: 'reopen', reason })
                }}
              >
                <Undo2 className="size-4" /> Reopen
              </Button>
            )}
            <a href={salarySheetUrl(run.id)} target="_blank" rel="noreferrer">
              <Button variant="secondary">
                <FileDown className="size-4" /> Salary sheet
              </Button>
            </a>
          </div>
        </div>
        {runAction.isError && <div className="mt-3"><Alert>{errorMessage(runAction.error)}</Alert></div>}
        {run.status === 'draft' && !run.can.approve && run.can.edit && <p className="mt-3 text-sm text-amber-700">A second person (branch admin) must approve this payroll.</p>}
        <div className="mt-4 grid grid-cols-3 gap-3 text-center">
          <div className="rounded-lg bg-slate-50 p-3">
            <p className="text-xs text-slate-500">Gross</p>
            <p className="font-semibold">{taka(run.total_gross)}</p>
          </div>
          <div className="rounded-lg bg-slate-50 p-3">
            <p className="text-xs text-slate-500">Deductions</p>
            <p className="font-semibold">{taka(run.total_deductions)}</p>
          </div>
          <div className="rounded-lg bg-brand-50 p-3">
            <p className="text-xs text-brand-700">Net pay</p>
            <p className="font-semibold text-brand-800">{taka(run.total_net)}</p>
          </div>
        </div>
      </Card>

      <Card className="mt-4 overflow-x-auto">
        <table className="w-full min-w-[820px] text-sm">
          <thead className="bg-slate-50 text-left text-xs text-slate-500">
            <tr>
              <th className="px-3 py-2 font-medium">Employee</th>
              <th className="px-3 py-2 text-right font-medium">Fixed</th>
              <th className="px-3 py-2 text-right font-medium">Sessions</th>
              <th className="px-3 py-2 text-right font-medium">Bonus / add.</th>
              <th className="px-3 py-2 text-right font-medium">Absence</th>
              <th className="px-3 py-2 text-right font-medium">Advance</th>
              <th className="px-3 py-2 text-right font-medium">Tax / other</th>
              <th className="px-3 py-2 text-right font-medium">Net</th>
              <th className="px-3 py-2" />
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {run.items.map((i) => (
              <tr key={i.id} className={cn(run.can.edit && 'cursor-pointer hover:bg-slate-50')} onClick={() => run.can.edit && setEditing(i)}>
                <td className="px-3 py-2">
                  <p className="font-medium text-slate-900">{i.employee.name}</p>
                  <p className="text-xs text-slate-500">
                    {departmentLabel[i.department]}
                    {i.breakdown?.prorated_days && ` · ${i.breakdown.prorated_days} days`}
                    {i.note && ` · ${i.note}`}
                  </p>
                </td>
                <td className="px-3 py-2 text-right tabular-nums">{i.fixed_amount ? taka(i.fixed_amount) : '—'}</td>
                <td className="px-3 py-2 text-right tabular-nums">
                  {i.session_pay ? taka(i.session_pay) : '—'}
                  {i.session_count > 0 && <span className="block text-xs text-slate-500">{i.session_count} sessions</span>}
                </td>
                <td className="px-3 py-2 text-right tabular-nums">{i.bonus + i.other_addition ? taka(i.bonus + i.other_addition) : '—'}</td>
                <td className="px-3 py-2 text-right tabular-nums">{i.absence_deduction ? `−${taka(i.absence_deduction)}` : '—'}</td>
                <td className="px-3 py-2 text-right tabular-nums">{i.advance_deduction ? `−${taka(i.advance_deduction)}` : '—'}</td>
                <td className="px-3 py-2 text-right tabular-nums">{i.tax + i.other_deduction ? `−${taka(i.tax + i.other_deduction)}` : '—'}</td>
                <td className="px-3 py-2 text-right font-semibold tabular-nums">{taka(i.net_pay)}</td>
                <td className="px-3 py-2 text-right">
                  {run.status !== 'draft' && (
                    <span className="flex items-center justify-end gap-2">
                      {i.paid_at ? <Badge tone="green">paid</Badge> : i.net_pay > 0 && <Badge tone="amber">unpaid</Badge>}
                      <a href={payslipUrl(i.id)} target="_blank" rel="noreferrer" aria-label="Payslip" className="text-slate-400 hover:text-slate-700" onClick={(e) => e.stopPropagation()}>
                        <FileDown className="size-4" />
                      </a>
                    </span>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>
      {run.can.edit && <p className="mt-2 text-xs text-slate-500">Click a row to add a bonus, absence, tax or change the advance instalment.</p>}

      {editing && <AdjustModal item={editing} onClose={() => setEditing(null)} />}
      {paying && <PayModal runId={run.id} branchId={run.branch?.id ?? 0} unpaid={unpaid} onClose={() => setPaying(false)} />}
    </>
  )
}

function AdjustModal({ item, onClose }: { item: PayrollItem; onClose: () => void }) {
  const { updateItem } = usePayrollMutations()
  const fields = [
    ['bonus', 'Bonus'],
    ['other_addition', 'Other addition'],
    ['absence_deduction', 'Absence deduction'],
    ['advance_deduction', 'Advance instalment'],
    ['tax', 'Tax (TDS)'],
    ['other_deduction', 'Other deduction'],
  ] as const
  const [v, setV] = useState<Record<string, string>>(() => ({ ...Object.fromEntries(fields.map(([k]) => [k, String(item[k] || '')])), note: item.note ?? '' }))
  const [error, setError] = useState<string | null>(null)
  const preview =
    item.fixed_amount + item.session_pay + (Number(v.bonus) || 0) + (Number(v.other_addition) || 0) - (Number(v.absence_deduction) || 0) - (Number(v.advance_deduction) || 0) - (Number(v.tax) || 0) - (Number(v.other_deduction) || 0)

  const save = async () => {
    setError(null)
    try {
      await updateItem.mutateAsync({ id: item.id, ...Object.fromEntries(fields.map(([k]) => [k, Number(v[k]) || 0])), note: v.note || null })
      onClose()
    } catch (e) {
      setError(Object.values(validationErrors(e))[0] ?? errorMessage(e))
    }
  }

  return (
    <Modal open title={`Adjust — ${item.employee.name}`} onClose={onClose}>
      <div className="space-y-4">
        {error && <Alert>{error}</Alert>}
        <p className="text-sm text-slate-600">
          Fixed {taka(item.fixed_amount)}
          {item.session_pay > 0 && ` + sessions ${taka(item.session_pay)}`}
        </p>
        {item.breakdown?.sessions?.map((s) => (
          <p key={s.label} className="text-xs text-slate-500">
            {s.label}: {s.count} sessions{s.rate ? ` × ${taka(s.rate)}` : ''} = {taka(s.amount)}
          </p>
        ))}
        <div className="grid grid-cols-2 gap-3">
          {fields.map(([k, label]) => (
            <Field key={k} label={label} htmlFor={`adj_${k}`}>
              <Input id={`adj_${k}`} type="number" min={0} value={v[k]} onChange={(e) => setV({ ...v, [k]: e.target.value })} />
            </Field>
          ))}
        </div>
        <Field label="Note (shown on payslip)" htmlFor="adj_note">
          <Input id="adj_note" value={v.note} onChange={(e) => setV({ ...v, note: e.target.value })} placeholder="e.g. 2 days unpaid leave" />
        </Field>
        <div className="flex items-center justify-between">
          <span className={cn('text-sm font-semibold', preview < 0 ? 'text-red-600' : 'text-slate-900')}>Net {taka(preview)}</span>
          <div className="flex gap-2">
            <Button variant="secondary" onClick={onClose}>
              Cancel
            </Button>
            <Button loading={updateItem.isPending} disabled={preview < 0} onClick={save}>
              Save
            </Button>
          </div>
        </div>
      </div>
    </Modal>
  )
}

function PayModal({ runId, branchId, unpaid, onClose }: { runId: number; branchId: number; unpaid: PayrollItem[]; onClose: () => void }) {
  const { runAction } = usePayrollMutations()
  const { data: options } = useExpenseOptions(branchId)
  const [selected, setSelected] = useState<number[]>(unpaid.map((i) => i.id))
  const [from, setFrom] = useState('')
  const account = from || String(options?.paid_from.find((a) => a.subtype === 'bank')?.id ?? options?.paid_from[0]?.id ?? '')
  const total = unpaid.filter((i) => selected.includes(i.id)).reduce((s, i) => s + i.net_pay, 0)

  return (
    <Modal open title="Pay salaries" onClose={onClose}>
      <div className="space-y-4">
        {runAction.isError && <Alert>{errorMessage(runAction.error)}</Alert>}
        <ul className="max-h-72 divide-y divide-slate-100 overflow-y-auto rounded-lg border border-slate-200">
          {unpaid.map((i) => (
            <li key={i.id} className="flex items-center gap-3 px-3 py-2 text-sm">
              <input
                type="checkbox"
                className="size-4 accent-brand-600"
                aria-label={i.employee.name}
                checked={selected.includes(i.id)}
                onChange={(e) => setSelected(e.target.checked ? [...selected, i.id] : selected.filter((x) => x !== i.id))}
              />
              <span className="flex-1">
                {i.employee.name}
                <span className="block text-xs text-slate-500">
                  {i.employee.payment_method} {i.employee.bank_account ?? i.employee.mfs_number ?? ''}
                </span>
              </span>
              <span>{taka(i.net_pay)}</span>
            </li>
          ))}
        </ul>
        <Field label="Pay from" htmlFor="pay_from">
          <Select id="pay_from" value={account} onChange={(e) => setFrom(e.target.value)}>
            {options?.paid_from.map((a) => (
              <option key={a.id} value={a.id}>
                {a.name}
              </option>
            ))}
          </Select>
        </Field>
        <div className="flex justify-end gap-2">
          <Button variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button
            loading={runAction.isPending}
            disabled={!selected.length || !account}
            onClick={() => runAction.mutate({ id: runId, action: 'pay', paid_from_account_id: Number(account), item_ids: selected }, { onSuccess: onClose })}
          >
            Pay {taka(total)}
          </Button>
        </div>
      </div>
    </Modal>
  )
}

/** Any staff member's own payslips (Accounts §১৪). */
export function MyPayslipsPage() {
  const { data, isLoading } = useMyPayslips()
  if (isLoading || !data) return <Spinner className="text-brand-600" />

  return (
    <div className="space-y-4">
      <h1 className="text-xl font-semibold text-slate-900">My payslips</h1>
      {!data.employee ? (
        <Card className="p-5 text-sm text-slate-500">Your login is not linked to an employee record yet. Ask the accounts office.</Card>
      ) : !data.data.length ? (
        <Card className="p-5 text-sm text-slate-500">No payslips yet.</Card>
      ) : (
        <Card className="divide-y divide-slate-100">
          {data.data.map((p) => (
            <a key={p.id} href={payslipUrl(p.id)} target="_blank" rel="noreferrer" className="flex items-center justify-between gap-2 px-4 py-3 hover:bg-slate-50">
              <div>
                <p className="font-medium text-slate-900">{p.label}</p>
                <p className="text-xs text-slate-500">{p.paid_at ? `Paid ${new Date(p.paid_at).toLocaleDateString('en-GB')}` : 'Approved — payment pending'}</p>
              </div>
              <span className="flex items-center gap-2 font-semibold">
                {taka(p.net_pay)} <FileDown className="size-4 text-slate-400" />
              </span>
            </a>
          ))}
        </Card>
      )}
    </div>
  )
}
