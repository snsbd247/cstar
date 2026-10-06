import { useQuery } from '@tanstack/react-query'
import { ArrowLeft, Pencil, Plus } from 'lucide-react'
import { useState } from 'react'
import { Link, useParams, useSearchParams } from 'react-router'
import { api, errorMessage, validationErrors } from '../../api/client'
import { Button } from '../../components/ui/Button'
import { Alert, Badge, Card, PageHeader } from '../../components/ui/Card'
import { Field, Input, Select } from '../../components/ui/Field'
import { Modal } from '../../components/ui/Modal'
import { Spinner } from '../../components/ui/Spinner'
import { useAuth } from '../../contexts/useAuth'
import { todayISO } from '../../utils/format'
import { useExpenseOptions } from '../accounts/api'
import { taka } from '../billing/api'
import { departmentLabel, payTypeLabel, payslipUrl, useEmployee, useEmployees, usePayrollMutations, type Department, type Employee, type PayType } from './api'

/** Accounts §৭.১: everyone who is paid — trainers and therapists link to their profile. */
export default function EmployeesPage() {
  const { can } = useAuth()
  const [params] = useSearchParams()
  const [status, setStatus] = useState('')
  const [department, setDepartment] = useState(params.get('department') ?? '')
  const { data, isLoading } = useEmployees(status || undefined)
  const [editing, setEditing] = useState<Employee | 'new' | null>(null)
  const groups = (['therapist', 'trainer', 'admin', 'support'] as Department[]).filter((d) => !department || d === department).map((d) => [d, data?.filter((e) => e.department === d) ?? []] as const)

  return (
    <>
      <PageHeader
        title="Employees"
        description="All staff on the payroll — therapists, trainers, front desk and support staff."
        actions={
          can('accounts.payroll.manage') && (
            <Button onClick={() => setEditing('new')}>
              <Plus className="size-4" /> Add employee
            </Button>
          )
        }
      />
      <Card className="mb-4 flex flex-col gap-2 p-3 sm:flex-row">
        <Select value={status} onChange={(e) => setStatus(e.target.value)} className="sm:w-48" aria-label="Status">
          <option value="">Current staff</option>
          <option value="left">Left</option>
        </Select>
        <Select value={department} onChange={(e) => setDepartment(e.target.value)} className="sm:w-48" aria-label="Department">
          <option value="">All departments</option>
          {Object.entries(departmentLabel).map(([k, l]) => (
            <option key={k} value={k}>
              {l}
            </option>
          ))}
        </Select>
      </Card>
      {isLoading ? (
        <Spinner className="text-brand-600" />
      ) : (
        <div className="space-y-4">
          {groups
            .filter(([, list]) => list.length)
            .map(([dept, list]) => (
              <Card key={dept} className="overflow-hidden">
                <p className="bg-slate-50 px-4 py-2 text-sm font-semibold text-slate-700">
                  {departmentLabel[dept]} <span className="font-normal text-slate-500">({list.length})</span>
                </p>
                <div className="divide-y divide-slate-100">
                  {list.map((e) => (
                    <Link key={e.id} to={`/app/employees/${e.id}`} className="flex flex-wrap items-center justify-between gap-2 px-4 py-3 hover:bg-slate-50">
                      <div>
                        <p className="font-medium text-slate-900">
                          {e.name} <span className="text-xs font-normal text-slate-500">· {e.employee_code}</span>
                        </p>
                        <p className="text-xs text-slate-500">
                          {e.designation} · {payTypeLabel[e.pay_type]}
                          {e.therapist && ' · therapist profile linked'}
                          {e.trainer && ' · trainer profile linked'}
                          {e.user && ' · has login'}
                        </p>
                      </div>
                      <div className="flex items-center gap-3 text-sm">
                        {e.advance_balance > 0 && <Badge tone="amber">advance {taka(e.advance_balance)}</Badge>}
                        {e.status !== 'active' && <Badge>{e.status}</Badge>}
                        <span className="w-24 text-right font-medium">{e.monthly_salary ? taka(e.monthly_salary) : '—'}</span>
                      </div>
                    </Link>
                  ))}
                </div>
              </Card>
            ))}
          {data?.length === 0 && <Card className="p-5 text-sm text-slate-500">No employees yet.</Card>}
        </div>
      )}
      {editing && <EmployeeModal employee={editing === 'new' ? undefined : editing} onClose={() => setEditing(null)} />}
    </>
  )
}

function useEmployeeOptions() {
  return useQuery({
    queryKey: ['employee-options'],
    queryFn: async () =>
      (
        await api.get<{
          data: {
            therapists: { id: number; name: string; employee_id: number | null }[]
            trainers: { id: number; name: string; employee_id: number | null }[]
            users: { id: number; name: string; email: string | null }[]
            services: { id: number; name: string }[]
          }
        }>('/hr/employee-options')
      ).data.data,
  })
}

function EmployeeModal({ employee, onClose }: { employee?: Employee; onClose: () => void }) {
  const { user } = useAuth()
  const { data: options } = useEmployeeOptions()
  const { saveEmployee } = usePayrollMutations()
  const [v, setV] = useState({
    name: employee?.name ?? '',
    designation: employee?.designation ?? '',
    department: employee?.department ?? ('admin' as Department),
    branch_id: employee?.branch_id ?? user?.branches?.[0]?.id ?? 0,
    joining_date: employee?.joining_date ?? todayISO(),
    left_date: employee?.left_date ?? '',
    employment_type: employee?.employment_type ?? 'full_time',
    pay_type: employee?.pay_type ?? ('fixed' as PayType),
    phone: employee?.phone ?? '',
    payment_method: employee?.payment_method ?? 'bank',
    bank_name: employee?.bank_name ?? '',
    bank_account: employee?.bank_account ?? '',
    mfs_number: employee?.mfs_number ?? '',
    user_id: employee?.user_id ?? '',
    therapist_id: employee?.therapist?.id ?? '',
    trainer_id: employee?.trainer?.id ?? '',
    status: employee?.status ?? 'active',
  })
  const [error, setError] = useState<string | null>(null)
  const set = (patch: Partial<typeof v>) => setV((x) => ({ ...x, ...patch }))

  const save = async () => {
    setError(null)
    try {
      await saveEmployee.mutateAsync({
        id: employee?.id,
        ...v,
        left_date: v.left_date || null,
        user_id: v.user_id || null,
        therapist_id: v.department === 'therapist' ? v.therapist_id || null : null,
        trainer_id: v.department === 'trainer' ? v.trainer_id || null : null,
        status: v.left_date ? 'left' : v.status,
      })
      onClose()
    } catch (e) {
      setError(Object.values(validationErrors(e))[0] ?? errorMessage(e))
    }
  }

  return (
    <Modal open wide title={employee ? `Edit ${employee.name}` : 'Add employee'} onClose={onClose}>
      <div className="space-y-4">
        {error && <Alert>{error}</Alert>}
        <div className="grid gap-3 sm:grid-cols-2">
          <Field label="Name" htmlFor="em_name">
            <Input id="em_name" value={v.name} onChange={(e) => set({ name: e.target.value })} />
          </Field>
          <Field label="Designation" htmlFor="em_des">
            <Input id="em_des" value={v.designation} onChange={(e) => set({ designation: e.target.value })} />
          </Field>
          <Field label="Department" htmlFor="em_dept">
            <Select id="em_dept" value={v.department} onChange={(e) => set({ department: e.target.value as Department })}>
              {Object.entries(departmentLabel).map(([k, l]) => (
                <option key={k} value={k}>
                  {l}
                </option>
              ))}
            </Select>
          </Field>
          <Field label="Branch" htmlFor="em_branch">
            <Select id="em_branch" value={v.branch_id} onChange={(e) => set({ branch_id: Number(e.target.value) })}>
              {user?.branches?.map((b) => (
                <option key={b.id} value={b.id}>
                  {b.name}
                </option>
              ))}
            </Select>
          </Field>
          {v.department === 'therapist' && (
            <Field label="Therapist profile" htmlFor="em_th" hint="Session pay is counted from this therapist's finalized sessions">
              <Select id="em_th" value={v.therapist_id} onChange={(e) => set({ therapist_id: Number(e.target.value) || '' })}>
                <option value="">Not linked</option>
                {options?.therapists
                  .filter((t) => !t.employee_id || t.employee_id === employee?.id)
                  .map((t) => (
                    <option key={t.id} value={t.id}>
                      {t.name}
                    </option>
                  ))}
              </Select>
            </Field>
          )}
          {v.department === 'trainer' && (
            <Field label="Trainer profile" htmlFor="em_tr">
              <Select id="em_tr" value={v.trainer_id} onChange={(e) => set({ trainer_id: Number(e.target.value) || '' })}>
                <option value="">Not linked</option>
                {options?.trainers
                  .filter((t) => !t.employee_id || t.employee_id === employee?.id)
                  .map((t) => (
                    <option key={t.id} value={t.id}>
                      {t.name}
                    </option>
                  ))}
              </Select>
            </Field>
          )}
          <Field label="Login (to see own payslips)" htmlFor="em_user">
            <Select id="em_user" value={v.user_id} onChange={(e) => set({ user_id: Number(e.target.value) || '' })}>
              <option value="">No login</option>
              {options?.users.map((u) => (
                <option key={u.id} value={u.id}>
                  {u.name} {u.email ? `(${u.email})` : ''}
                </option>
              ))}
            </Select>
          </Field>
          <Field label="Employment" htmlFor="em_emp">
            <Select id="em_emp" value={v.employment_type} onChange={(e) => set({ employment_type: e.target.value as Employee['employment_type'] })}>
              <option value="full_time">Full time</option>
              <option value="part_time">Part time</option>
              <option value="visiting">Visiting</option>
            </Select>
          </Field>
          <Field label="Pay type" htmlFor="em_pay">
            <Select id="em_pay" value={v.pay_type} onChange={(e) => set({ pay_type: e.target.value as PayType })}>
              {Object.entries(payTypeLabel).map(([k, l]) => (
                <option key={k} value={k}>
                  {l}
                </option>
              ))}
            </Select>
          </Field>
          <Field label="Joining date" htmlFor="em_join">
            <Input id="em_join" type="date" value={v.joining_date} onChange={(e) => set({ joining_date: e.target.value })} />
          </Field>
          {employee && (
            <Field label="Left on (if leaving)" htmlFor="em_left">
              <Input id="em_left" type="date" value={v.left_date} onChange={(e) => set({ left_date: e.target.value })} />
            </Field>
          )}
          <Field label="Phone" htmlFor="em_phone">
            <Input id="em_phone" value={v.phone} onChange={(e) => set({ phone: e.target.value })} />
          </Field>
          <Field label="Salary paid by" htmlFor="em_method">
            <Select id="em_method" value={v.payment_method} onChange={(e) => set({ payment_method: e.target.value as Employee['payment_method'] })}>
              <option value="bank">Bank</option>
              <option value="cash">Cash</option>
              <option value="bkash">bKash</option>
              <option value="nagad">Nagad</option>
            </Select>
          </Field>
          {v.payment_method === 'bank' ? (
            <>
              <Field label="Bank" htmlFor="em_bank">
                <Input id="em_bank" value={v.bank_name} onChange={(e) => set({ bank_name: e.target.value })} />
              </Field>
              <Field label="Account no." htmlFor="em_acc">
                <Input id="em_acc" value={v.bank_account} onChange={(e) => set({ bank_account: e.target.value })} />
              </Field>
            </>
          ) : (
            v.payment_method !== 'cash' && (
              <Field label="bKash / Nagad number" htmlFor="em_mfs">
                <Input id="em_mfs" value={v.mfs_number} onChange={(e) => set({ mfs_number: e.target.value })} />
              </Field>
            )
          )}
        </div>
        <div className="flex justify-end gap-2">
          <Button variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button loading={saveEmployee.isPending} disabled={!v.name} onClick={save}>
            Save
          </Button>
        </div>
      </div>
    </Modal>
  )
}

export function EmployeeDetailPage() {
  const { id } = useParams()
  const { can } = useAuth()
  const { data: e, isLoading } = useEmployee(Number(id))
  const [modal, setModal] = useState<'edit' | 'structure' | 'rate' | 'advance' | null>(null)
  const manage = can('accounts.payroll.manage')

  if (isLoading || !e) return <Spinner className="text-brand-600" />
  const current = e.structures.find((s) => s.effective_from <= todayISO())

  return (
    <>
      <Link to="/app/employees" className="mb-3 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-800">
        <ArrowLeft className="size-4" /> Employees
      </Link>
      <Card className="flex flex-wrap items-start justify-between gap-3 p-5">
        <div>
          <h1 className="text-xl font-semibold text-slate-900">{e.name}</h1>
          <p className="text-sm text-slate-500">
            {e.employee_code} · {e.designation} · {departmentLabel[e.department]} · {e.branch?.name}
          </p>
          <p className="text-sm text-slate-500">
            {payTypeLabel[e.pay_type]} · joined {e.joining_date}
            {e.left_date && ` · left ${e.left_date}`} · paid by {e.payment_method} {e.bank_account ?? e.mfs_number ?? ''}
          </p>
        </div>
        {manage && (
          <Button variant="secondary" onClick={() => setModal('edit')}>
            <Pencil className="size-4" /> Edit
          </Button>
        )}
      </Card>

      <div className="mt-4 grid gap-4 lg:grid-cols-2">
        <Card className="p-5">
          <div className="flex items-center justify-between">
            <h3 className="font-semibold text-slate-900">Salary</h3>
            {manage && (
              <Button variant="ghost" onClick={() => setModal('structure')}>
                <Plus className="size-4" /> New salary from a date
              </Button>
            )}
          </div>
          {current ? (
            <ul className="mt-2 space-y-1 text-sm">
              {[
                ['Basic', current.basic],
                ['House rent', current.house_rent],
                ['Medical', current.medical],
                ['Conveyance', current.conveyance],
                ...(current.other_allowances ?? []).map((a) => [a.name, a.amount] as [string, number]),
              ]
                .filter(([, a]) => Number(a) > 0)
                .map(([n, a]) => (
                  <li key={n as string} className="flex justify-between">
                    <span className="text-slate-600">{n}</span>
                    <span>{taka(Number(a))}</span>
                  </li>
                ))}
              <li className="flex justify-between border-t border-slate-100 pt-1 font-semibold">
                <span>Monthly (from {current.effective_from})</span>
                <span>{taka(current.total)}</span>
              </li>
              {e.pay_type === 'mixed' && <li className="text-xs text-slate-500">Covers the first {current.included_sessions} sessions each month; extra sessions are paid per session.</li>}
              {e.pay_type === 'revenue_share' && <li className="text-xs text-slate-500">{current.revenue_share_percent}% of the income from their sessions.</li>}
            </ul>
          ) : (
            <p className="mt-2 text-sm text-slate-500">No salary set yet.</p>
          )}

          {['per_session', 'mixed'].includes(e.pay_type) && (
            <div className="mt-4">
              <div className="flex items-center justify-between">
                <h4 className="text-sm font-semibold text-slate-800">Rate per session</h4>
                {manage && (
                  <Button variant="ghost" onClick={() => setModal('rate')}>
                    <Plus className="size-4" /> Rate
                  </Button>
                )}
              </div>
              <ul className="mt-1 text-sm">
                {e.rates.map((r) => (
                  <li key={r.id} className="flex justify-between">
                    <span className="text-slate-600">
                      {r.service?.name ?? 'Any therapy'} <span className="text-xs">(from {r.effective_from})</span>
                    </span>
                    <span>{taka(r.rate)}</span>
                  </li>
                ))}
                {e.rates.length === 0 && <li className="text-slate-500">No rate yet.</li>}
              </ul>
            </div>
          )}
        </Card>

        <Card className="p-5">
          <div className="flex items-center justify-between">
            <h3 className="font-semibold text-slate-900">Advances</h3>
            {manage && (
              <Button variant="ghost" onClick={() => setModal('advance')}>
                <Plus className="size-4" /> Give advance
              </Button>
            )}
          </div>
          <ul className="mt-2 divide-y divide-slate-100 text-sm">
            {e.advances.map((a) => (
              <li key={a.id} className="flex justify-between py-2">
                <span>
                  {a.date} · {taka(a.amount)} <span className="text-xs text-slate-500">({taka(a.installment)}/month)</span>
                  {a.reason && <span className="block text-xs text-slate-500">{a.reason}</span>}
                </span>
                {a.status === 'active' ? <Badge tone="amber">{taka(a.balance)} left</Badge> : <Badge tone="green">settled</Badge>}
              </li>
            ))}
            {e.advances.length === 0 && <li className="py-2 text-slate-500">None.</li>}
          </ul>

          <h3 className="mt-5 font-semibold text-slate-900">Payslips</h3>
          <ul className="mt-2 divide-y divide-slate-100 text-sm">
            {e.payslips.map((p) => (
              <li key={p.id} className="flex items-center justify-between py-2">
                <a href={payslipUrl(p.id)} target="_blank" rel="noreferrer" className="text-sky-brand-600">
                  {p.label}
                </a>
                <span>
                  {taka(p.net_pay)} {p.paid_at ? <Badge tone="green">paid</Badge> : <Badge tone="amber">unpaid</Badge>}
                </span>
              </li>
            ))}
            {e.payslips.length === 0 && <li className="py-2 text-slate-500">None yet.</li>}
          </ul>
        </Card>
      </div>

      {modal === 'edit' && <EmployeeModal employee={e} onClose={() => setModal(null)} />}
      {modal === 'structure' && <StructureModal employeeId={e.id} payType={e.pay_type} onClose={() => setModal(null)} />}
      {modal === 'rate' && <RateModal employeeId={e.id} onClose={() => setModal(null)} />}
      {modal === 'advance' && <AdvanceModal employeeId={e.id} branchId={e.branch_id} onClose={() => setModal(null)} />}
    </>
  )
}

function useSave<T extends (...a: never[]) => Promise<unknown>>(fn: T, onClose: () => void) {
  const [error, setError] = useState<string | null>(null)
  const run = async (...args: Parameters<T>) => {
    setError(null)
    try {
      await fn(...args)
      onClose()
    } catch (e) {
      setError(Object.values(validationErrors(e))[0] ?? errorMessage(e))
    }
  }
  return { error, run }
}

function StructureModal({ employeeId, payType, onClose }: { employeeId: number; payType: PayType; onClose: () => void }) {
  const { saveStructure } = usePayrollMutations()
  const [v, setV] = useState({ effective_from: todayISO().slice(0, 8) + '01', basic: '', house_rent: '', medical: '', conveyance: '', included_sessions: '', revenue_share_percent: '' })
  const { error, run } = useSave(saveStructure.mutateAsync, onClose)
  const num = (k: keyof typeof v, label: string) => (
    <Field label={label} htmlFor={`st_${k}`}>
      <Input id={`st_${k}`} type="number" min={0} value={v[k]} onChange={(e) => setV({ ...v, [k]: e.target.value })} />
    </Field>
  )

  return (
    <Modal open title="Salary from a date" onClose={onClose}>
      <div className="space-y-4">
        {error && <Alert>{error}</Alert>}
        <p className="text-sm text-slate-500">A raise is a new salary from a date; earlier payslips keep the old figures.</p>
        <Field label="From" htmlFor="st_from">
          <Input id="st_from" type="date" value={v.effective_from} onChange={(e) => setV({ ...v, effective_from: e.target.value })} />
        </Field>
        <div className="grid grid-cols-2 gap-3">
          {num('basic', 'Basic (৳)')}
          {num('house_rent', 'House rent')}
          {num('medical', 'Medical')}
          {num('conveyance', 'Conveyance')}
          {payType === 'mixed' && num('included_sessions', 'Sessions covered by salary')}
          {payType === 'revenue_share' && num('revenue_share_percent', 'Revenue share %')}
        </div>
        <div className="flex justify-end gap-2">
          <Button variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button
            loading={saveStructure.isPending}
            onClick={() =>
              run({
                employeeId,
                effective_from: v.effective_from,
                basic: Number(v.basic) || 0,
                house_rent: Number(v.house_rent) || 0,
                medical: Number(v.medical) || 0,
                conveyance: Number(v.conveyance) || 0,
                included_sessions: Number(v.included_sessions) || 0,
                revenue_share_percent: v.revenue_share_percent ? Number(v.revenue_share_percent) : null,
              })
            }
          >
            Save
          </Button>
        </div>
      </div>
    </Modal>
  )
}

function RateModal({ employeeId, onClose }: { employeeId: number; onClose: () => void }) {
  const { saveRate } = usePayrollMutations()
  const { data: options } = useEmployeeOptions()
  const [v, setV] = useState({ service_id: '', rate: '', effective_from: todayISO().slice(0, 8) + '01' })
  const { error, run } = useSave(saveRate.mutateAsync, onClose)

  return (
    <Modal open title="Rate per session" onClose={onClose}>
      <div className="space-y-4">
        {error && <Alert>{error}</Alert>}
        <Field label="Therapy" htmlFor="rt_service">
          <Select id="rt_service" value={v.service_id} onChange={(e) => setV({ ...v, service_id: e.target.value })}>
            <option value="">Any therapy</option>
            {options?.services.map((s) => (
              <option key={s.id} value={s.id}>
                {s.name}
              </option>
            ))}
          </Select>
        </Field>
        <div className="grid grid-cols-2 gap-3">
          <Field label="Rate (৳)" htmlFor="rt_rate">
            <Input id="rt_rate" type="number" min={0} value={v.rate} onChange={(e) => setV({ ...v, rate: e.target.value })} />
          </Field>
          <Field label="From" htmlFor="rt_from">
            <Input id="rt_from" type="date" value={v.effective_from} onChange={(e) => setV({ ...v, effective_from: e.target.value })} />
          </Field>
        </div>
        <div className="flex justify-end gap-2">
          <Button variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button loading={saveRate.isPending} disabled={!v.rate} onClick={() => run({ employeeId, service_id: Number(v.service_id) || null, rate: Number(v.rate), effective_from: v.effective_from })}>
            Save
          </Button>
        </div>
      </div>
    </Modal>
  )
}

function AdvanceModal({ employeeId, branchId, onClose }: { employeeId: number; branchId: number; onClose: () => void }) {
  const { giveAdvance } = usePayrollMutations()
  const { data: options } = useExpenseOptions(branchId)
  const [v, setV] = useState({ date: todayISO(), amount: '', installment: '', paid_from_account_id: '', reason: '' })
  const { error, run } = useSave(giveAdvance.mutateAsync, onClose)
  const from = v.paid_from_account_id || String(options?.paid_from[0]?.id ?? '')

  return (
    <Modal open title="Give salary advance" onClose={onClose}>
      <div className="space-y-4">
        {error && <Alert>{error}</Alert>}
        <div className="grid grid-cols-2 gap-3">
          <Field label="Amount (৳)" htmlFor="ad_amount">
            <Input id="ad_amount" type="number" min={1} value={v.amount} onChange={(e) => setV({ ...v, amount: e.target.value })} />
          </Field>
          <Field label="Cut per month (৳)" htmlFor="ad_inst">
            <Input id="ad_inst" type="number" min={1} value={v.installment} onChange={(e) => setV({ ...v, installment: e.target.value })} placeholder="all at once" />
          </Field>
          <Field label="Date" htmlFor="ad_date">
            <Input id="ad_date" type="date" max={todayISO()} value={v.date} onChange={(e) => setV({ ...v, date: e.target.value })} />
          </Field>
          <Field label="Paid from" htmlFor="ad_from">
            <Select id="ad_from" value={from} onChange={(e) => setV({ ...v, paid_from_account_id: e.target.value })}>
              {options?.paid_from.map((a) => (
                <option key={a.id} value={a.id}>
                  {a.name}
                </option>
              ))}
            </Select>
          </Field>
        </div>
        <Field label="Reason" htmlFor="ad_reason">
          <Input id="ad_reason" value={v.reason} onChange={(e) => setV({ ...v, reason: e.target.value })} />
        </Field>
        <div className="flex justify-end gap-2">
          <Button variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button
            loading={giveAdvance.isPending}
            disabled={!Number(v.amount) || !from}
            onClick={() => run({ employeeId, date: v.date, amount: Number(v.amount), installment: Number(v.installment) || null, paid_from_account_id: Number(from), reason: v.reason || null })}
          >
            Give {Number(v.amount) > 0 ? taka(Number(v.amount)) : ''}
          </Button>
        </div>
      </div>
    </Modal>
  )
}
