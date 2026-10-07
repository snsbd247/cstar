import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Clock, LogIn, LogOut } from 'lucide-react'
import { useState } from 'react'
import { api, errorMessage, validationErrors } from '../../api/client'
import { Button } from '../../components/ui/Button'
import { Alert, Card, PageHeader } from '../../components/ui/Card'
import { Field, Input, Select } from '../../components/ui/Field'
import { Modal } from '../../components/ui/Modal'
import { Spinner } from '../../components/ui/Spinner'
import { useAuth } from '../../contexts/useAuth'
import { cn } from '../../utils/cn'
import { todayISO } from '../../utils/format'

interface Day {
  date: string
  status: string
  source: string
  note: string | null
  check_in: string | null
  check_out: string | null
}

/** Sprint 20: "Check in / Check out" for any staff member whose login is linked to an employee record. */
export function CheckInCard() {
  const qc = useQueryClient()
  const { data } = useQuery({
    queryKey: ['my-attendance'],
    queryFn: async () => (await api.get<{ data: { employee: { name: string }; today: Day | null; office_start: string; month: { present: number; late: number } } | null }>('/me/attendance')).data.data,
  })
  const punch = useMutation({
    mutationFn: (action: 'check-in' | 'check-out') => api.post(`/me/attendance/${action}`, {}),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['my-attendance'] }),
  })
  if (!data) return null
  const t = data.today

  return (
    <Card className="mb-4 flex flex-wrap items-center justify-between gap-3 p-4">
      <div className="flex items-center gap-3">
        <Clock className="size-5 text-brand-600" />
        <div className="text-sm">
          <p className="font-medium text-slate-900">
            {!t?.check_in ? 'Not checked in yet today' : t.check_out ? `Checked out at ${t.check_out}` : `Checked in at ${t.check_in}`}
            {t?.status === 'late' && <span className="ml-2 text-xs font-normal text-amber-700">late</span>}
          </p>
          <p className="text-xs text-slate-500">
            Office starts {data.office_start} · this month: {data.month.present} day(s) present{data.month.late ? `, ${data.month.late} late` : ''}
          </p>
        </div>
      </div>
      {punch.isError && <p className="text-xs text-red-600">{Object.values(validationErrors(punch.error))[0] ?? errorMessage(punch.error)}</p>}
      {!t?.check_in ? (
        <Button loading={punch.isPending} onClick={() => punch.mutate('check-in')}>
          <LogIn className="size-4" /> Check in
        </Button>
      ) : (
        !t.check_out && (
          <Button variant="secondary" loading={punch.isPending} onClick={() => punch.mutate('check-out')}>
            <LogOut className="size-4" /> Check out
          </Button>
        )
      )}
    </Card>
  )
}

interface Cell {
  code: string
  in: string | null
  out: string | null
  note: string | null
}
interface Sheet {
  month: string
  days: { date: string; weekday: number; off: boolean }[]
  rows: { employee: { id: number; name: string; designation: string | null; department: string }; cells: Record<string, Cell | null>; summary: Record<string, number> }[]
}

const codes: Record<string, [string, string]> = {
  present: ['P', 'bg-brand-100 text-brand-800'],
  late: ['L', 'bg-amber-100 text-amber-800'],
  half_day: ['H', 'bg-sky-100 text-sky-800'],
  absent: ['A', 'bg-red-100 text-red-700'],
  leave: ['Lv', 'bg-violet-100 text-violet-800'],
  holiday: ['Hol', 'bg-slate-100 text-slate-500'],
  off: ['–', 'text-slate-300'],
  unmarked: ['?', 'text-slate-400'],
}
const statusLabel: Record<string, string> = { present: 'Present', late: 'Late', half_day: 'Half day', absent: 'Absent' }

/** Staff → Attendance (Sprint 20): monthly sheet; HR clicks a day to enter or correct it. */
export default function StaffAttendancePage() {
  const { can } = useAuth()
  const [month, setMonth] = useState(() => new Date().toLocaleDateString('en-CA').slice(0, 7))
  const [department, setDepartment] = useState('')
  const [editing, setEditing] = useState<{ employee: Sheet['rows'][number]['employee']; date: string; cell: Cell | null } | null>(null)
  const { data, isLoading } = useQuery({
    queryKey: ['staff-attendance', month, department],
    queryFn: async () => (await api.get<{ data: Sheet }>('/hr/attendance', { params: { month, department: department || undefined } })).data.data,
  })
  const canEdit = can('accounts.payroll.manage')
  const today = todayISO()

  return (
    <>
      <PageHeader
        title="Staff Attendance"
        description="Staff check in and out from their dashboard. Leave, holidays and the weekly day off fill in by themselves; click a day to enter or correct it."
      />
      <Card className="mb-4 flex flex-wrap items-center gap-3 p-3">
        <Input type="month" value={month} onChange={(e) => setMonth(e.target.value)} className="sm:w-44" aria-label="Month" />
        <Select value={department} onChange={(e) => setDepartment(e.target.value)} className="sm:w-44" aria-label="Department">
          <option value="">All departments</option>
          <option value="therapist">Therapists</option>
          <option value="trainer">Trainers</option>
          <option value="admin">Admin</option>
          <option value="support">Support</option>
        </Select>
        <span className="flex flex-wrap gap-2 text-xs text-slate-500">
          {Object.entries(codes).map(([k, [c, cls]]) => (
            <span key={k} className="flex items-center gap-1">
              <span className={cn('rounded px-1 font-semibold', cls)}>{c}</span>
              {k.replace('_', ' ')}
            </span>
          ))}
        </span>
      </Card>
      <Card className="overflow-x-auto">
        {isLoading || !data ? (
          <Spinner className="m-5 text-brand-600" />
        ) : !data.rows.length ? (
          <p className="p-5 text-sm text-slate-500">No staff found.</p>
        ) : (
          <table className="w-full text-xs">
            <thead>
              <tr className="border-b border-slate-100 text-slate-500">
                <th className="sticky left-0 bg-white px-3 py-2 text-left font-medium">Staff</th>
                {data.days.map((d) => (
                  <th key={d.date} className={cn('px-0.5 py-2 text-center font-medium', d.off && 'text-slate-300', d.date === today && 'text-brand-700')}>
                    {Number(d.date.slice(8))}
                  </th>
                ))}
                <th className="px-2 py-2 text-right font-medium">P</th>
                <th className="px-2 py-2 text-right font-medium">L</th>
                <th className="px-2 py-2 text-right font-medium">A</th>
                <th className="px-2 py-2 text-right font-medium">Lv</th>
                <th className="px-2 py-2 text-right font-medium">?</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-50">
              {data.rows.map((r) => (
                <tr key={r.employee.id}>
                  <td className="sticky left-0 whitespace-nowrap bg-white px-3 py-1.5">
                    <p className="font-medium text-slate-800">{r.employee.name}</p>
                    <p className="text-slate-400">{r.employee.designation ?? r.employee.department}</p>
                  </td>
                  {data.days.map((d) => {
                    const cell = r.cells[d.date]
                    const [label, cls] = cell ? codes[cell.code] : ['', '']
                    const editable = canEdit && d.date <= today
                    return (
                      <td key={d.date} className="px-0.5 py-1 text-center">
                        <button
                          disabled={!editable}
                          title={cell ? [statusLabel[cell.code] ?? cell.code, cell.in && `in ${cell.in}`, cell.out && `out ${cell.out}`, cell.note].filter(Boolean).join(' · ') : undefined}
                          onClick={() => setEditing({ employee: r.employee, date: d.date, cell })}
                          className={cn('min-w-7 rounded px-1 py-0.5 font-semibold', cls, editable && 'hover:ring-1 hover:ring-brand-300')}
                        >
                          {label}
                        </button>
                      </td>
                    )
                  })}
                  <td className="px-2 text-right font-semibold text-brand-700">{r.summary.present + r.summary.late}</td>
                  <td className="px-2 text-right text-amber-700">{r.summary.late}</td>
                  <td className="px-2 text-right text-red-600">{r.summary.absent}</td>
                  <td className="px-2 text-right text-violet-700">{r.summary.leave}</td>
                  <td className="px-2 text-right text-slate-400">{r.summary.unmarked}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </Card>
      {editing && <EditDay {...editing} onClose={() => setEditing(null)} />}
    </>
  )
}

function EditDay({ employee, date, cell, onClose }: { employee: { id: number; name: string }; date: string; cell: Cell | null; onClose: () => void }) {
  const qc = useQueryClient()
  const recorded = cell && cell.code in statusLabel
  const [v, setV] = useState({ status: recorded ? cell.code : 'present', check_in: cell?.in ?? '', check_out: cell?.out ?? '', note: recorded ? (cell.note ?? '') : '' })
  const [errors, setErrors] = useState<Record<string, string>>({})
  const save = useMutation({
    mutationFn: (status?: string) => api.put('/hr/attendance', { employee_id: employee.id, date, ...v, status: status ?? v.status, check_in: v.check_in || null, check_out: v.check_out || null }),
    onSuccess: () => (qc.invalidateQueries({ queryKey: ['staff-attendance'] }), onClose()),
    onError: (e) => setErrors(validationErrors(e)),
  })

  return (
    <Modal open title={`${employee.name} — ${date}`} onClose={onClose}>
      <div className="space-y-3">
        {cell && !recorded && <p className="text-sm text-slate-500">Currently: {cell.code}{cell.note && ` (${cell.note})`}</p>}
        {save.isError && !Object.keys(errors).length && <Alert>{errorMessage(save.error)}</Alert>}
        <Field label="Status" htmlFor="sa_status">
          <Select id="sa_status" value={v.status} onChange={(e) => setV({ ...v, status: e.target.value })}>
            {Object.entries(statusLabel).map(([k, l]) => (
              <option key={k} value={k}>
                {l}
              </option>
            ))}
          </Select>
        </Field>
        <div className="grid grid-cols-2 gap-3">
          <Field label="Check in" htmlFor="sa_in" error={errors.check_in}>
            <Input id="sa_in" type="time" value={v.check_in} onChange={(e) => setV({ ...v, check_in: e.target.value })} />
          </Field>
          <Field label="Check out" htmlFor="sa_out" error={errors.check_out}>
            <Input id="sa_out" type="time" value={v.check_out} onChange={(e) => setV({ ...v, check_out: e.target.value })} />
          </Field>
        </div>
        <Field label="Note" htmlFor="sa_note">
          <Input id="sa_note" value={v.note} onChange={(e) => setV({ ...v, note: e.target.value })} />
        </Field>
        <div className="flex justify-between gap-2">
          {recorded ? (
            <Button variant="ghost" className="text-red-600" onClick={() => save.mutate('clear')}>
              Clear entry
            </Button>
          ) : (
            <span />
          )}
          <span className="flex gap-2">
            <Button variant="ghost" onClick={onClose}>
              Cancel
            </Button>
            <Button loading={save.isPending} onClick={() => save.mutate(undefined)}>
              Save
            </Button>
          </span>
        </div>
      </div>
    </Modal>
  )
}
