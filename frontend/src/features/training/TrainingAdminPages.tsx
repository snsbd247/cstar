import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Pencil, Plus, Search } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router'
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
import { todayISO } from '../../utils/format'
import { useClasses } from './api'

type Meta = { current_page: number; last_page: number; total: number }

interface DayClass {
  id: number
  name: string
  code: string
  trainer: string | null
  start: string
  end: string
  is_holiday: boolean
  students: number
  marked: number
  present: number
  absent: number
  records: number
}

function DayTable({ classes, date }: { classes: DayClass[]; date: string }) {
  if (!classes.length) return <p className="p-5 text-sm text-slate-500">No class meets on this day.</p>

  return (
    <div className="overflow-x-auto">
      <table className="w-full text-sm">
        <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
          <tr>
            <th className="px-4 py-2.5 font-medium">Class</th>
            <th className="px-4 py-2.5 font-medium">Time</th>
            <th className="px-4 py-2.5 font-medium">Attendance</th>
            <th className="px-4 py-2.5 font-medium">Records</th>
            <th className="px-4 py-2.5" />
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-100">
          {classes.map((c) => {
            const done = c.marked >= c.students && c.students > 0
            return (
              <tr key={c.id}>
                <td className="px-4 py-2.5">
                  <p className="font-medium text-slate-900">{c.name}</p>
                  <p className="text-xs text-slate-500">{c.trainer ?? 'No trainer'}</p>
                </td>
                <td className="whitespace-nowrap px-4 py-2.5 text-slate-600">
                  {c.start}–{c.end}
                </td>
                <td className="px-4 py-2.5">
                  {c.is_holiday ? (
                    <Badge tone="gray">Holiday</Badge>
                  ) : (
                    <>
                      <Badge tone={done ? 'green' : c.marked ? 'amber' : 'red'}>{done ? 'Marked' : c.marked ? `${c.marked} of ${c.students} marked` : 'Not marked'}</Badge>
                      <span className="ml-2 text-xs text-slate-500">
                        {c.present} present · {c.absent} absent
                      </span>
                    </>
                  )}
                </td>
                <td className="px-4 py-2.5 text-slate-600">
                  {c.records} of {c.present}
                </td>
                <td className="px-4 py-2.5 text-right">
                  <Link to={`/app/classes/${c.id}?date=${date}`} className="text-sm font-medium text-brand-700 hover:underline">
                    Open class →
                  </Link>
                </td>
              </tr>
            )
          })}
        </tbody>
      </table>
    </div>
  )
}

/** Training → Training Dashboard. */
export function TrainingDashboardPage() {
  const { data, isLoading } = useQuery({
    queryKey: ['training-dashboard'],
    queryFn: async () =>
      (
        await api.get<{
          data: {
            kpis: { active_students: number; active_classes: number; attendance_rate: number | null; records_this_month: number }
            today: DayClass[]
            near_capacity: { id: number; name: string; seats: number; max: number }[]
            holidays: { date: string; name: string }[]
          }
        }>('/training/dashboard')
      ).data.data,
  })
  if (isLoading || !data) return <Spinner className="text-brand-600" />

  return (
    <>
      <PageHeader title="Training Dashboard" description="Regular training at a glance — classes, students and attendance." />
      <div className="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <Stat label="Active students" value={data.kpis.active_students} to="/app/students" />
        <Stat label="Active classes" value={data.kpis.active_classes} to="/app/classes" />
        <Stat label="Attendance this month" value={data.kpis.attendance_rate === null ? null : `${data.kpis.attendance_rate}%`} to="/app/reports?r=class-attendance" tone={data.kpis.attendance_rate !== null && data.kpis.attendance_rate < 75 ? 'amber' : 'green'} />
        <Stat label="Training records this month" value={data.kpis.records_this_month} to="/app/training-sessions" />
      </div>
      <div className="grid gap-4 lg:grid-cols-[1fr_300px]">
        <Card className="overflow-hidden">
          <h2 className="border-b border-slate-100 px-4 py-3 font-semibold text-slate-900">Today’s classes</h2>
          <DayTable classes={data.today} date={todayISO()} />
        </Card>
        <div className="space-y-4">
          <Card className="p-4">
            <h2 className="font-semibold text-slate-900">Nearly full</h2>
            {data.near_capacity.length ? (
              <ul className="mt-2 space-y-1 text-sm">
                {data.near_capacity.map((c) => (
                  <li key={c.id} className="flex justify-between">
                    <Link to={`/app/classes/${c.id}`} className="text-slate-700 hover:text-brand-700">
                      {c.name}
                    </Link>
                    <span className={c.seats >= c.max ? 'text-red-600' : 'text-amber-700'}>
                      {c.seats}/{c.max}
                    </span>
                  </li>
                ))}
              </ul>
            ) : (
              <p className="mt-1 text-sm text-slate-500">Every class has room.</p>
            )}
          </Card>
          <Card className="p-4">
            <h2 className="font-semibold text-slate-900">Holidays (next 30 days)</h2>
            {data.holidays.length ? (
              <ul className="mt-2 space-y-1 text-sm text-slate-700">
                {data.holidays.map((h) => (
                  <li key={h.date + h.name}>
                    {new Date(`${h.date}T00:00:00`).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })} — {h.name}
                  </li>
                ))}
              </ul>
            ) : (
              <p className="mt-1 text-sm text-slate-500">None.</p>
            )}
          </Card>
        </div>
      </div>
    </>
  )
}

const week = [6, 0, 1, 2, 3, 4, 5]
const dayName = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']

/** Training → Training Schedules: one weekly grid of all classes. */
export function TrainingSchedulePage() {
  const { data, isLoading } = useQuery({
    queryKey: ['training-schedule'],
    queryFn: async () =>
      (
        await api.get<{ data: { id: number; name: string; code: string; branch: string; trainer: string | null; seats: number; max: number | null; days: { weekday: number; start: string; end: string }[] }[] }>(
          '/training/schedule',
        )
      ).data.data,
  })

  return (
    <>
      <PageHeader title="Training Schedules" description="When each class meets. Change a class’s days on its page." />
      {isLoading ? (
        <Spinner className="text-brand-600" />
      ) : (
        <Card className="overflow-x-auto">
          <table className="w-full min-w-[760px] text-sm">
            <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
              <tr>
                <th className="px-4 py-2.5 font-medium">Class</th>
                {week.map((d) => (
                  <th key={d} className={cn('px-2 py-2.5 text-center font-medium', d === 5 && 'text-slate-400')}>
                    {dayName[d]}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {data?.map((c) => (
                <tr key={c.id}>
                  <td className="px-4 py-2.5">
                    <Link to={`/app/classes/${c.id}`} className="font-medium text-slate-900 hover:text-brand-700">
                      {c.name}
                    </Link>
                    <p className="text-xs text-slate-500">
                      {c.trainer ?? 'No trainer'} · {c.branch} · {c.seats}
                      {c.max ? `/${c.max}` : ''} students
                    </p>
                  </td>
                  {week.map((d) => {
                    const slot = c.days.find((s) => s.weekday === d)
                    return (
                      <td key={d} className="px-2 py-2.5 text-center">
                        {slot && (
                          <span className="inline-block rounded bg-brand-50 px-1.5 py-0.5 text-xs text-brand-800 ring-1 ring-inset ring-brand-200">
                            {slot.start}–{slot.end}
                          </span>
                        )}
                      </td>
                    )
                  })}
                </tr>
              ))}
              {data?.length === 0 && (
                <tr>
                  <td colSpan={8} className="px-4 py-4 text-slate-500">
                    No active classes.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </Card>
      )}
    </>
  )
}

/** Training → Attendance: has every class been marked today (or any day)? */
export function TrainingAttendancePage() {
  const [date, setDate] = useState(todayISO())
  const { data, isLoading } = useQuery({
    queryKey: ['training-attendance-day', date],
    queryFn: async () => (await api.get<{ data: { date: string; classes: DayClass[] } }>('/training/attendance-day', { params: { date } })).data.data,
  })

  return (
    <>
      <PageHeader title="Attendance" description="Trainers mark attendance in their app; you can also mark or correct it on the class page." />
      <Card className="mb-4 flex items-center gap-2 p-3">
        <Input type="date" value={date} max={todayISO()} onChange={(e) => setDate(e.target.value)} className="w-44" aria-label="Date" />
        <Button variant="ghost" onClick={() => setDate(todayISO())}>
          Today
        </Button>
        <Link to="/app/reports?r=class-attendance" className="ml-auto text-sm font-medium text-brand-700 hover:underline">
          Attendance report →
        </Link>
      </Card>
      <Card className="overflow-hidden">{isLoading || !data ? <Spinner className="m-5 text-brand-600" /> : <DayTable classes={data.classes} date={date} />}</Card>
    </>
  )
}

interface SessionRow {
  id: number
  date: string
  class: { id: number; name: string; code: string }
  trainer: string | null
  theme: string | null
  notes: string | null
  present: number
  absent: number
  records: number
}

/** Training → Training Sessions: each time a class met. */
export function TrainingSessionsPage() {
  const { data: classes } = useClasses()
  const [classId, setClassId] = useState('')
  const [page, setPage] = useState(1)
  const { data, isLoading } = useQuery({
    queryKey: ['training-sessions-admin', classId, page],
    queryFn: async () => (await api.get<{ data: SessionRow[]; meta: Meta }>('/training/sessions', { params: { class_id: classId || undefined, page } })).data,
  })

  return (
    <>
      <PageHeader title="Training Sessions" description="Each class meeting, with attendance and how many students got a training record." />
      <Card className="mb-4 p-3">
        <Select value={classId} onChange={(e) => (setClassId(e.target.value), setPage(1))} className="sm:w-72" aria-label="Class">
          <option value="">All classes</option>
          {classes?.map((c) => (
            <option key={c.id} value={c.id}>
              {c.name}
            </option>
          ))}
        </Select>
      </Card>
      <Card className="overflow-hidden">
        {isLoading ? (
          <Spinner className="m-5 text-brand-600" />
        ) : !data?.data.length ? (
          <p className="p-5 text-sm text-slate-500">No sessions yet — a session starts when attendance or records are written for a class.</p>
        ) : (
          <ul className="divide-y divide-slate-100">
            {data.data.map((s) => (
              <li key={s.id} className="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm">
                <div>
                  <Link to={`/app/classes/${s.class.id}?date=${s.date}`} className="font-medium text-slate-900 hover:text-brand-700">
                    {s.class.name}
                  </Link>
                  <p className="text-xs text-slate-500">
                    {new Date(`${s.date}T00:00:00`).toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' })} · {s.trainer ?? 'No trainer'}
                    {s.theme && ` · ${s.theme}`}
                  </p>
                </div>
                <span className="text-xs text-slate-600">
                  {s.present} present · {s.absent} absent · {s.records} records
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

interface Activity {
  id: number
  name: string
  name_bn: string | null
  applies_to: 'training' | 'therapy' | 'both'
  is_active: boolean
  sort_order: number
}

const appliesLabel = { training: 'Training', therapy: 'Therapy', both: 'Training & therapy' }

/** Training → Activities: the list trainers and therapists pick from in records and session notes. */
export function ActivitiesPage() {
  const { canAny } = useAuth()
  const [editing, setEditing] = useState<Activity | 'new' | null>(null)
  const [filter, setFilter] = useState('')
  const { data, isLoading } = useQuery({ queryKey: ['activity-types-admin'], queryFn: async () => (await api.get<{ data: Activity[] }>('/activity-types')).data.data })
  const list = (data ?? []).filter((a) => !filter || a.applies_to === filter || a.applies_to === 'both')
  const canEdit = canAny('classes.manage', 'therapists.manage')

  return (
    <>
      <PageHeader
        title="Activities"
        description="Activities trainers tick in training records and therapists in session notes. Turn one off instead of deleting it — old records keep it."
        actions={
          canEdit && (
            <Button onClick={() => setEditing('new')}>
              <Plus className="size-4" /> New activity
            </Button>
          )
        }
      />
      <Card className="mb-4 p-3">
        <Select value={filter} onChange={(e) => setFilter(e.target.value)} className="sm:w-56" aria-label="Used in">
          <option value="">Used anywhere</option>
          <option value="training">Used in training</option>
          <option value="therapy">Used in therapy</option>
        </Select>
      </Card>
      <Card className="overflow-hidden">
        {isLoading ? (
          <Spinner className="m-5 text-brand-600" />
        ) : (
          <ul className="divide-y divide-slate-100">
            {list.map((a) => (
              <li key={a.id} className={cn('flex items-center justify-between gap-2 px-4 py-2.5 text-sm', !a.is_active && 'opacity-50')}>
                <span>
                  <span className="font-medium text-slate-900">{a.name}</span>
                  {a.name_bn && <span className="text-slate-500"> · {a.name_bn}</span>}
                </span>
                <span className="flex items-center gap-2">
                  <Badge tone={a.applies_to === 'therapy' ? 'blue' : a.applies_to === 'training' ? 'green' : 'gray'}>{appliesLabel[a.applies_to]}</Badge>
                  {!a.is_active && <Badge>off</Badge>}
                  {canEdit && (
                    <button onClick={() => setEditing(a)} aria-label={`Edit ${a.name}`} className="text-slate-400 hover:text-slate-700">
                      <Pencil className="size-4" />
                    </button>
                  )}
                </span>
              </li>
            ))}
          </ul>
        )}
      </Card>
      {editing && <ActivityModal activity={editing === 'new' ? undefined : editing} onClose={() => setEditing(null)} />}
    </>
  )
}

function ActivityModal({ activity, onClose }: { activity?: Activity; onClose: () => void }) {
  const qc = useQueryClient()
  const [v, setV] = useState({ name: activity?.name ?? '', name_bn: activity?.name_bn ?? '', applies_to: activity?.applies_to ?? 'both', is_active: activity?.is_active ?? true })
  const [errors, setErrors] = useState<Record<string, string>>({})
  const save = useMutation({
    mutationFn: () => (activity ? api.put(`/activity-types/${activity.id}`, v) : api.post('/activity-types', v)),
    onSuccess: () => (qc.invalidateQueries({ queryKey: ['activity-types-admin'] }), qc.invalidateQueries({ queryKey: ['activity-types'] }), onClose()),
    onError: (e) => setErrors(validationErrors(e)),
  })

  return (
    <Modal open title={activity ? 'Edit activity' : 'New activity'} onClose={onClose}>
      <div className="space-y-3">
        {save.isError && !Object.keys(errors).length && <Alert>{errorMessage(save.error)}</Alert>}
        <Field label="Name" htmlFor="ac_name" error={errors.name}>
          <Input id="ac_name" value={v.name} onChange={(e) => setV({ ...v, name: e.target.value })} />
        </Field>
        <Field label="Name in Bangla" htmlFor="ac_bn">
          <Input id="ac_bn" value={v.name_bn} onChange={(e) => setV({ ...v, name_bn: e.target.value })} />
        </Field>
        <Field label="Used in" htmlFor="ac_applies">
          <Select id="ac_applies" value={v.applies_to} onChange={(e) => setV({ ...v, applies_to: e.target.value as Activity['applies_to'] })}>
            {Object.entries(appliesLabel).map(([k, l]) => (
              <option key={k} value={k}>
                {l}
              </option>
            ))}
          </Select>
        </Field>
        <label className="flex items-center gap-2 text-sm text-slate-700">
          <input type="checkbox" className="size-4 accent-brand-600" checked={v.is_active} onChange={(e) => setV({ ...v, is_active: e.target.checked })} /> In use
        </label>
        <div className="flex justify-end gap-2">
          <Button variant="ghost" onClick={onClose}>
            Cancel
          </Button>
          <Button loading={save.isPending} onClick={() => save.mutate()}>
            Save
          </Button>
        </div>
      </div>
    </Modal>
  )
}

interface PlanRow {
  id: number
  title: string
  status: string
  start_date: string | null
  review_date: string | null
  review_due: boolean
  type: 'training' | 'therapy'
  programme: string
  patient: { id: number; name: string; patient_code: string }
  goals: number
  achieved: number
  progress: number | null
}

/** ITP / Training Plans and Therapy Plans & Goals — every individual plan, with goal progress. */
export function PlansPage() {
  const [params] = useSearchParams()
  const type = params.get('type') ?? ''
  const [status, setStatus] = useState('active')
  const [review, setReview] = useState('')
  const [search, setSearch] = useState('')
  const [q, setQ] = useState('')
  const [page, setPage] = useState(1)

  useEffect(() => {
    const t = setTimeout(() => (setQ(search.trim()), setPage(1)), 300)
    return () => clearTimeout(t)
  }, [search])

  const { data, isLoading } = useQuery({
    queryKey: ['plans-admin', type, status, review, q, page],
    queryFn: async () =>
      (await api.get<{ data: PlanRow[]; meta: Meta }>('/plans', { params: { type: type || undefined, status: status || undefined, review: review || undefined, q: q || undefined, page } })).data,
  })
  const title = type === 'training' ? 'ITP / Training Plans' : type === 'therapy' ? 'Therapy Plans & Goals' : 'Plans & Goals'

  return (
    <>
      <PageHeader title={title} description="Individual plans with their goals. Plans are written and updated from the child’s Training or Therapy tab." />
      <Card className="mb-4 flex flex-col gap-2 p-3 sm:flex-row">
        <div className="relative sm:w-72">
          <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
          <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Child, patient ID or plan" className="pl-9" aria-label="Search plans" />
        </div>
        <Select value={status} onChange={(e) => (setStatus(e.target.value), setPage(1))} className="sm:w-44" aria-label="Plan status">
          <option value="active">Active</option>
          <option value="closed">Closed</option>
          <option value="">Any status</option>
        </Select>
        <Select value={review} onChange={(e) => (setReview(e.target.value), setPage(1))} className="sm:w-56" aria-label="Review">
          <option value="">Any review date</option>
          <option value="due">Review due within 7 days</option>
        </Select>
      </Card>
      <Card className="overflow-hidden">
        {isLoading ? (
          <Spinner className="m-5 text-brand-600" />
        ) : !data?.data.length ? (
          <p className="p-5 text-sm text-slate-500">No plans match.</p>
        ) : (
          <ul className="divide-y divide-slate-100">
            {data.data.map((p) => (
              <li key={p.id} className="grid gap-2 px-4 py-3 text-sm sm:grid-cols-[1fr_220px] sm:items-center">
                <div>
                  <Link to={`/app/patients/${p.patient.id}?tab=${p.type}`} className="font-medium text-slate-900 hover:text-brand-700">
                    {p.patient.name}
                  </Link>{' '}
                  <span className="text-xs text-slate-500">{p.patient.patient_code}</span>
                  <p className="text-slate-700">{p.title}</p>
                  <p className="text-xs text-slate-500">
                    {p.programme}
                    {p.review_date && (
                      <span className={p.review_due ? 'font-medium text-amber-700' : ''}>
                        {' '}
                        · review {p.review_date}
                      </span>
                    )}
                    {p.status !== 'active' && ` · ${p.status}`}
                  </p>
                </div>
                <div>
                  <div className="flex justify-between text-xs text-slate-500">
                    <span>
                      {p.achieved}/{p.goals} goals achieved
                    </span>
                    <span>{p.progress ?? 0}%</span>
                  </div>
                  <div className="mt-1 h-2 rounded-full bg-slate-100">
                    <div className="h-2 rounded-full bg-brand-500" style={{ width: `${p.progress ?? 0}%` }} />
                  </div>
                </div>
              </li>
            ))}
          </ul>
        )}
        <Pager meta={data?.meta} onPage={setPage} />
      </Card>
    </>
  )
}
