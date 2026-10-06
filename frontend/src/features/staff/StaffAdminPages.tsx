import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Mail, Phone, Plus, Trash2, UserRound } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router'
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
import { departmentLabel, payTypeLabel, useEmployees, type Department, type PayType } from '../payroll/api'

/** Staff → Employee Profiles: everyone on the staff as a card with contact and links. */
export function EmployeeProfilesPage() {
  const { data, isLoading } = useEmployees()
  const [department, setDepartment] = useState('')
  const list = (data ?? []).filter((e) => !department || e.department === department)

  return (
    <>
      <PageHeader title="Employee Profiles" description="Contact, role and links for every staff member. Open a profile for salary, session rates and advances." />
      <Card className="mb-4 p-3">
        <Select value={department} onChange={(e) => setDepartment(e.target.value)} className="sm:w-56" aria-label="Department">
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
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
          {list.map((e) => (
            <Link key={e.id} to={`/app/employees/${e.id}`} className="block rounded-xl border border-slate-200 bg-white p-4 shadow-xs transition hover:border-brand-300">
              <div className="flex items-start gap-3">
                <span className="flex size-11 shrink-0 items-center justify-center rounded-full bg-sky-brand-100 text-base font-semibold text-sky-brand-700">{e.name.charAt(0)}</span>
                <div className="min-w-0">
                  <p className="truncate font-semibold text-slate-900">{e.name}</p>
                  <p className="truncate text-sm text-slate-600">{e.designation ?? departmentLabel[e.department]}</p>
                  <p className="text-xs text-slate-500">
                    {e.employee_code} · {e.branch?.name} · since {e.joining_date}
                  </p>
                </div>
              </div>
              <div className="mt-3 flex flex-wrap gap-1.5">
                <Badge tone="blue">{departmentLabel[e.department]}</Badge>
                <Badge>{payTypeLabel[e.pay_type]}</Badge>
                {e.status !== 'active' && <Badge tone="amber">{e.status}</Badge>}
                {e.user && <Badge tone="green">has login</Badge>}
              </div>
              <div className="mt-2 space-y-0.5 text-xs text-slate-500">
                {e.phone && (
                  <p className="flex items-center gap-1">
                    <Phone className="size-3" /> {e.phone}
                  </p>
                )}
                {e.user?.email && (
                  <p className="flex items-center gap-1">
                    <Mail className="size-3" /> {e.user.email}
                  </p>
                )}
                {(e.therapist || e.trainer) && (
                  <p className="flex items-center gap-1">
                    <UserRound className="size-3" /> {e.therapist ? `Therapist profile: ${e.therapist.name}` : `Trainer profile: ${e.trainer?.name}`}
                  </p>
                )}
              </div>
            </Link>
          ))}
        </div>
      )}
    </>
  )
}

interface StructureRow {
  employee: { id: number; name: string; employee_code: string; designation: string | null; department: Department; pay_type: PayType }
  current: { effective_from: string; components: Record<string, number>; total: number; included_sessions: number | null; revenue_share_percent: string | null } | null
  upcoming: string | null
  changes: number
}

/** Staff → Salary Structures: what each person earns per month today. Changes are made on the employee page. */
export function SalaryStructuresPage() {
  const { data, isLoading } = useQuery({ queryKey: ['salary-structures'], queryFn: async () => (await api.get<{ data: StructureRow[] }>('/hr/salary-structures')).data.data })
  const total = (data ?? []).reduce((s, r) => s + (r.current?.total ?? 0), 0)

  return (
    <>
      <PageHeader title="Salary Structures" description="The monthly structure in force today. A raise is a new structure from a date — old payslips never change." />
      <Card className="overflow-x-auto">
        {isLoading ? (
          <Spinner className="m-5 text-brand-600" />
        ) : (
          <table className="w-full text-sm">
            <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
              <tr>
                <th className="px-4 py-2.5 font-medium">Employee</th>
                <th className="px-4 py-2.5 font-medium">Pay type</th>
                <th className="px-4 py-2.5 font-medium">Breakdown</th>
                <th className="px-4 py-2.5 text-right font-medium">Monthly</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {data?.map((r) => (
                <tr key={r.employee.id}>
                  <td className="px-4 py-2.5">
                    <Link to={`/app/employees/${r.employee.id}`} className="font-medium text-slate-900 hover:text-brand-700">
                      {r.employee.name}
                    </Link>
                    <p className="text-xs text-slate-500">
                      {r.employee.designation ?? departmentLabel[r.employee.department]} · {r.employee.employee_code}
                    </p>
                  </td>
                  <td className="px-4 py-2.5 text-slate-600">
                    {payTypeLabel[r.employee.pay_type]}
                    {r.current?.included_sessions ? <span className="block text-xs text-slate-500">{r.current.included_sessions} sessions included</span> : null}
                    {r.current?.revenue_share_percent ? <span className="block text-xs text-slate-500">{Number(r.current.revenue_share_percent)}% of session income</span> : null}
                  </td>
                  <td className="px-4 py-2.5 text-xs text-slate-600">
                    {r.current ? (
                      <>
                        {Object.entries(r.current.components)
                          .map(([k, v]) => `${k} ${taka(v)}`)
                          .join(' · ')}
                        <span className="block text-slate-400">
                          from {r.current.effective_from}
                          {r.changes > 1 && ` · ${r.changes} versions`}
                          {r.upcoming && ` · new one from ${r.upcoming}`}
                        </span>
                      </>
                    ) : (
                      <span className="text-amber-700">No structure yet{r.employee.pay_type === 'per_session' ? ' (paid per session)' : ''}</span>
                    )}
                  </td>
                  <td className="px-4 py-2.5 text-right font-medium">{r.current ? taka(r.current.total) : '—'}</td>
                </tr>
              ))}
            </tbody>
            <tfoot>
              <tr className="bg-slate-50">
                <td colSpan={3} className="px-4 py-2.5 text-right text-sm text-slate-600">
                  Fixed monthly total (before session pay)
                </td>
                <td className="px-4 py-2.5 text-right font-semibold">{taka(total)}</td>
              </tr>
            </tfoot>
          </table>
        )}
      </Card>
    </>
  )
}

/** Accounts → Employee Advances: money given ahead of salary and what is still to be deducted. */
export function AdvancesPage() {
  const [status, setStatus] = useState('open')
  const { data, isLoading } = useQuery({
    queryKey: ['employee-advances', status],
    queryFn: async () =>
      (
        await api.get<{ data: { id: number; date: string; employee: { id: number; name: string; employee_code: string; designation: string | null }; amount: number; installment: number; balance: number; status: string; reason: string | null }[]; outstanding: number }>(
          '/hr/advances',
          { params: { status: status || undefined } },
        )
      ).data,
  })

  return (
    <>
      <PageHeader title="Employee Advances" description="Give an advance from the employee’s page; the monthly installment comes off the next payroll automatically." />
      <Card className="mb-4 flex flex-wrap items-center gap-3 p-3">
        <Select value={status} onChange={(e) => setStatus(e.target.value)} className="sm:w-48" aria-label="Show">
          <option value="open">Still being repaid</option>
          <option value="">All advances</option>
        </Select>
        {data && (
          <span className="ml-auto text-sm text-slate-600">
            Still to recover: <b>{taka(data.outstanding)}</b>
          </span>
        )}
      </Card>
      <Card className="overflow-hidden">
        {isLoading ? (
          <Spinner className="m-5 text-brand-600" />
        ) : !data?.data.length ? (
          <p className="p-5 text-sm text-slate-500">No advances.</p>
        ) : (
          <ul className="divide-y divide-slate-100">
            {data.data.map((a) => (
              <li key={a.id} className="grid gap-2 px-4 py-3 text-sm sm:grid-cols-[1fr_200px] sm:items-center">
                <div>
                  <Link to={`/app/employees/${a.employee.id}`} className="font-medium text-slate-900 hover:text-brand-700">
                    {a.employee.name}
                  </Link>
                  <p className="text-xs text-slate-500">
                    {a.date} · {taka(a.amount)} given · {taka(a.installment)} a month
                    {a.reason && ` · ${a.reason}`}
                  </p>
                </div>
                <div>
                  <div className="flex justify-between text-xs text-slate-500">
                    <span>{a.balance > 0 ? `${taka(a.balance)} left` : 'Repaid'}</span>
                    <span>{Math.round(((a.amount - a.balance) / a.amount) * 100)}%</span>
                  </div>
                  <div className="mt-1 h-1.5 rounded-full bg-slate-100">
                    <div className="h-1.5 rounded-full bg-brand-500" style={{ width: `${((a.amount - a.balance) / a.amount) * 100}%` }} />
                  </div>
                </div>
              </li>
            ))}
          </ul>
        )}
      </Card>
    </>
  )
}

interface LeaveRow {
  id: number
  source: 'staff' | 'therapist'
  name: string
  role: string | null
  type: string
  type_label: string
  from: string
  to: string
  days: number
  reason: string | null
  by: string | null
}

/** Staff → Leave / Absence: who is away, from when to when. A therapist's leave also blocks their booking slots. */
export function LeavePage() {
  const { canAny } = useAuth()
  const qc = useQueryClient()
  const [adding, setAdding] = useState(false)
  const { data, isLoading } = useQuery({ queryKey: ['staff-leaves'], queryFn: async () => (await api.get<{ data: LeaveRow[]; types: Record<string, string> }>('/staff/leaves')).data })
  const cancel = useMutation({ mutationFn: (id: number) => api.delete(`/staff/leaves/${id}`), onSuccess: () => qc.invalidateQueries({ queryKey: ['staff-leaves'] }) })
  const canEdit = canAny('accounts.payroll.manage', 'therapists.manage')
  const today = todayISO()

  return (
    <>
      <PageHeader
        title="Leave / Absence"
        description="This month and the next two. Fridays are not counted as leave days. Salary deductions for unpaid leave are entered in payroll (decision A8)."
        actions={
          canEdit && (
            <Button onClick={() => setAdding(true)}>
              <Plus className="size-4" /> Record leave
            </Button>
          )
        }
      />
      <Card className="overflow-hidden">
        {isLoading ? (
          <Spinner className="m-5 text-brand-600" />
        ) : !data?.data.length ? (
          <p className="p-5 text-sm text-slate-500">Nobody is on leave in this period.</p>
        ) : (
          <ul className="divide-y divide-slate-100">
            {data.data.map((l) => {
              const now = l.from <= today && l.to >= today
              return (
                <li key={`${l.source}-${l.id}`} className={cn('flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm', l.to < today && 'opacity-60')}>
                  <div>
                    <p className="font-medium text-slate-900">
                      {l.name} {now && <Badge tone="amber">away now</Badge>}
                    </p>
                    <p className="text-xs text-slate-500">
                      {l.role} · {l.from === l.to ? l.from : `${l.from} → ${l.to}`} · {l.days} day(s)
                      {l.reason && ` · ${l.reason}`}
                      {l.by && ` · recorded by ${l.by}`}
                    </p>
                  </div>
                  <span className="flex items-center gap-2">
                    <Badge tone={l.type === 'unpaid' ? 'red' : l.type === 'sick' ? 'amber' : 'blue'}>{l.type_label}</Badge>
                    {canEdit && l.source === 'staff' && l.from > today && (
                      <Button variant="ghost" className="min-h-8 px-2 text-red-600" onClick={() => confirm(`Cancel ${l.name}'s leave?`) && cancel.mutate(l.id)} aria-label="Cancel leave">
                        <Trash2 className="size-4" />
                      </Button>
                    )}
                  </span>
                </li>
              )
            })}
          </ul>
        )}
      </Card>
      {adding && data && <LeaveModal types={data.types} onClose={() => setAdding(false)} />}
    </>
  )
}

function LeaveModal({ types, onClose }: { types: Record<string, string>; onClose: () => void }) {
  const qc = useQueryClient()
  const { data: employees } = useEmployees()
  const [v, setV] = useState({ employee_id: '', type: 'casual', start_date: todayISO(), end_date: todayISO(), reason: '' })
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [done, setDone] = useState<string | null>(null)
  const save = useMutation({
    mutationFn: async () => (await api.post<{ data: { days: number; appointments_to_reschedule: number } }>('/staff/leaves', v)).data.data,
    onSuccess: (d) => {
      qc.invalidateQueries({ queryKey: ['staff-leaves'] })
      if (d.appointments_to_reschedule) setDone(`Saved (${d.days} day(s)). ${d.appointments_to_reschedule} booked appointment(s) fall on these days — reschedule them from Appointments.`)
      else onClose()
    },
    onError: (e) => setErrors(validationErrors(e)),
  })

  return (
    <Modal open title="Record leave" onClose={onClose}>
      {done ? (
        <div className="space-y-3">
          <Alert tone="green">{done}</Alert>
          <div className="flex justify-end">
            <Button onClick={onClose}>Close</Button>
          </div>
        </div>
      ) : (
        <div className="space-y-3">
          {save.isError && !Object.keys(errors).length && <Alert>{errorMessage(save.error)}</Alert>}
          <Field label="Who" htmlFor="lv_emp" error={errors.employee_id}>
            <Select id="lv_emp" value={v.employee_id} onChange={(e) => setV({ ...v, employee_id: e.target.value })}>
              <option value="">Choose a staff member…</option>
              {employees
                ?.filter((e) => e.status !== 'left')
                .map((e) => (
                  <option key={e.id} value={e.id}>
                    {e.name} — {e.designation ?? departmentLabel[e.department]}
                  </option>
                ))}
            </Select>
          </Field>
          <div className="grid gap-3 sm:grid-cols-3">
            <Field label="Kind" htmlFor="lv_type">
              <Select id="lv_type" value={v.type} onChange={(e) => setV({ ...v, type: e.target.value })}>
                {Object.entries(types).map(([k, l]) => (
                  <option key={k} value={k}>
                    {l}
                  </option>
                ))}
              </Select>
            </Field>
            <Field label="From" htmlFor="lv_from" error={errors.start_date}>
              <Input id="lv_from" type="date" value={v.start_date} onChange={(e) => setV({ ...v, start_date: e.target.value, end_date: e.target.value > v.end_date ? e.target.value : v.end_date })} />
            </Field>
            <Field label="To" htmlFor="lv_to" error={errors.end_date}>
              <Input id="lv_to" type="date" min={v.start_date} value={v.end_date} onChange={(e) => setV({ ...v, end_date: e.target.value })} />
            </Field>
          </div>
          <Field label="Reason" htmlFor="lv_reason">
            <Input id="lv_reason" value={v.reason} onChange={(e) => setV({ ...v, reason: e.target.value })} />
          </Field>
          <div className="flex justify-end gap-2">
            <Button variant="ghost" onClick={onClose}>
              Cancel
            </Button>
            <Button loading={save.isPending} disabled={!v.employee_id} onClick={() => save.mutate()}>
              Save
            </Button>
          </div>
        </div>
      )}
    </Modal>
  )
}

/** Staff → Staff Assignments: each therapist's and trainer's current children. */
export function AssignmentsPage() {
  const [kind, setKind] = useState('')
  const { data, isLoading } = useQuery({
    queryKey: ['staff-assignments'],
    queryFn: async () =>
      (await api.get<{ data: { kind: 'therapist' | 'trainer'; id: number; name: string; children: { id: number; name: string; patient_code: string; detail: string | null }[] }[] }>('/staff/assignments')).data.data,
  })
  const list = (data ?? []).filter((s) => !kind || s.kind === kind)

  return (
    <>
      <PageHeader title="Staff Assignments" description="Children each therapist and trainer looks after now. To change one, transfer the enrollment from the child’s Enrollments tab." />
      <Card className="mb-4 p-3">
        <Select value={kind} onChange={(e) => setKind(e.target.value)} className="sm:w-48" aria-label="Staff">
          <option value="">Therapists & trainers</option>
          <option value="therapist">Therapists</option>
          <option value="trainer">Trainers</option>
        </Select>
      </Card>
      {isLoading ? (
        <Spinner className="text-brand-600" />
      ) : (
        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
          {list.map((s) => (
            <Card key={`${s.kind}-${s.id}`} className="overflow-hidden">
              <div className="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                <p className="font-semibold text-slate-900">{s.name}</p>
                <Badge tone={s.kind === 'therapist' ? 'blue' : 'green'}>
                  {s.children.length} {s.kind === 'therapist' ? 'patients' : 'students'}
                </Badge>
              </div>
              <ul className="max-h-72 divide-y divide-slate-100 overflow-y-auto text-sm">
                {s.children.map((c) => (
                  <li key={`${c.id}-${c.detail}`} className="flex justify-between gap-2 px-4 py-2">
                    <Link to={`/app/patients/${c.id}?tab=enrollments`} className="text-slate-800 hover:text-brand-700">
                      {c.name}
                    </Link>
                    <span className="truncate text-xs text-slate-500">{c.detail}</span>
                  </li>
                ))}
              </ul>
            </Card>
          ))}
          {!list.length && <p className="text-sm text-slate-500">Nobody is assigned yet.</p>}
        </div>
      )}
    </>
  )
}
