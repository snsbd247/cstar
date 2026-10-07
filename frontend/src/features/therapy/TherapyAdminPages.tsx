import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ChevronLeft, ChevronRight, FileText, Pencil, Plus, Search } from 'lucide-react'
import { useEffect, useState } from 'react'
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
import { todayISO } from '../../utils/format'
import { progressReportUrl } from '../assessments/api'
import { statusStyle, useTherapists, type AppointmentStatus, type PracticeLog } from './api'
import { PracticeFeedbackSummary } from './components/PracticeFeedbackSummary'

type Meta = { current_page: number; last_page: number; total: number }

function useDebounced(value: string) {
  const [v, setV] = useState(value)
  useEffect(() => {
    const t = setTimeout(() => setV(value.trim()), 300)
    return () => clearTimeout(t)
  }, [value])
  return v
}

/** Therapy → Therapy Dashboard. */
export function TherapyDashboardPage() {
  const { data, isLoading } = useQuery({
    queryKey: ['therapy-dashboard'],
    queryFn: async () =>
      (
        await api.get<{
          data: {
            kpis: { today: number; active_patients: number; sessions_this_week: number; notes_pending: number; no_show_rate: number | null; draft_assessments: number }
            today_by_status: Partial<Record<AppointmentStatus, number>>
            therapists_today: { name: string; total: number; done: number }[]
            on_leave: { name: string; until: string; reason: string | null }[]
            pending_recommendations: number
          }
        }>('/therapy/dashboard')
      ).data.data,
  })
  if (isLoading || !data) return <Spinner className="text-brand-600" />
  const k = data.kpis

  return (
    <>
      <PageHeader title="Therapy Dashboard" description="One-to-one therapy today and this month." />
      <div className="mb-4 grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
        <Stat label="Appointments today" value={k.today} to="/app/appointments/queue" />
        <Stat label="Children in therapy" value={k.active_patients} to="/app/enrollments?type=therapy&status=active" />
        <Stat label="Sessions (7 days)" value={k.sessions_this_week} to="/app/therapy-sessions" />
        <Stat label="Notes to finalize" value={k.notes_pending} tone={k.notes_pending ? 'amber' : 'green'} to="/app/reports?r=pending-notes" />
        <Stat label="No-show rate (month)" value={k.no_show_rate === null ? null : `${k.no_show_rate}%`} tone={(k.no_show_rate ?? 0) > 15 ? 'red' : undefined} to="/app/reports?r=therapist-sessions" />
        <Stat label="Draft assessments" value={k.draft_assessments} to="/app/assessments?status=draft" />
      </div>
      <div className="grid gap-4 lg:grid-cols-3">
        <Card className="p-4">
          <h2 className="font-semibold text-slate-900">Today by status</h2>
          <ul className="mt-2 space-y-1.5 text-sm">
            {(Object.keys(statusStyle) as AppointmentStatus[])
              .filter((s) => data.today_by_status[s])
              .map((s) => (
                <li key={s} className="flex items-center justify-between">
                  <span className={cn('rounded-md px-2 py-0.5 text-xs ring-1 ring-inset', statusStyle[s].className)}>{statusStyle[s].label}</span>
                  <b>{data.today_by_status[s]}</b>
                </li>
              ))}
            {!Object.keys(data.today_by_status).length && <li className="text-slate-500">No appointments today.</li>}
          </ul>
        </Card>
        <Card className="p-4">
          <h2 className="font-semibold text-slate-900">Therapists today</h2>
          <ul className="mt-2 space-y-2 text-sm">
            {data.therapists_today.map((t) => (
              <li key={t.name}>
                <div className="flex justify-between">
                  <span className="text-slate-700">{t.name}</span>
                  <span className="text-slate-500">
                    {t.done}/{t.total}
                  </span>
                </div>
                <div className="mt-1 h-1.5 rounded-full bg-slate-100">
                  <div className="h-1.5 rounded-full bg-brand-500" style={{ width: `${(t.done / t.total) * 100}%` }} />
                </div>
              </li>
            ))}
            {!data.therapists_today.length && <li className="text-slate-500">Nobody is booked today.</li>}
          </ul>
        </Card>
        <div className="space-y-4">
          <Card className="p-4">
            <h2 className="font-semibold text-slate-900">On leave today</h2>
            {data.on_leave.length ? (
              <ul className="mt-2 space-y-1 text-sm text-slate-700">
                {data.on_leave.map((l) => (
                  <li key={l.name}>
                    {l.name} <span className="text-xs text-slate-500">until {l.until}</span>
                  </li>
                ))}
              </ul>
            ) : (
              <p className="mt-1 text-sm text-slate-500">Everyone is in.</p>
            )}
          </Card>
          <Stat label="Assessment recommendations waiting" value={data.pending_recommendations} to="/app/assessments/recommendations" tone={data.pending_recommendations ? 'amber' : undefined} />
        </div>
      </div>
    </>
  )
}

const weekdayIndex = (date: string) => new Date(`${date}T00:00:00`).getDay()
const shiftWeek = (date: string, weeks: number) => {
  const d = new Date(`${date}T00:00:00`)
  d.setDate(d.getDate() + weeks * 7)
  return d.toLocaleDateString('en-CA')
}

/** Therapy → Therapist Schedule: working hours, leave and booked appointments for a week. */
export function TherapistWeekPage() {
  const [week, setWeek] = useState(todayISO())
  const { data, isLoading } = useQuery({
    queryKey: ['therapist-schedule', week],
    queryFn: async () =>
      (
        await api.get<{
          data: {
            week_start: string
            days: string[]
            therapists: { id: number; name: string; type: string | null; hours: Record<string, string[]>; leave: { from: string; to: string; reason: string | null }[]; appointments: Record<string, number> }[]
          }
        }>('/therapy/schedule', { params: { week } })
      ).data.data,
  })

  return (
    <>
      <PageHeader title="Therapist Schedule" description="Working hours (set on each therapist), leave and how many appointments are booked each day." />
      <Card className="mb-4 flex items-center gap-2 p-3">
        <Button variant="secondary" onClick={() => setWeek(shiftWeek(week, -1))} aria-label="Previous week">
          <ChevronLeft className="size-4" />
        </Button>
        <span className="min-w-40 text-center text-sm font-semibold text-slate-900">
          {data && `Week of ${new Date(`${data.week_start}T00:00:00`).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })}`}
        </span>
        <Button variant="secondary" onClick={() => setWeek(shiftWeek(week, 1))} aria-label="Next week">
          <ChevronRight className="size-4" />
        </Button>
        <Button variant="ghost" onClick={() => setWeek(todayISO())}>
          This week
        </Button>
        <Link to="/app/therapists" className="ml-auto text-sm font-medium text-brand-700 hover:underline">
          Edit hours & leave →
        </Link>
      </Card>
      {isLoading || !data ? (
        <Spinner className="text-brand-600" />
      ) : (
        <Card className="overflow-x-auto">
          <table className="w-full min-w-[860px] text-sm">
            <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
              <tr>
                <th className="px-4 py-2.5 font-medium">Therapist</th>
                {data.days.map((d) => (
                  <th key={d} className={cn('px-2 py-2.5 text-center font-medium', d === todayISO() && 'text-brand-700')}>
                    {new Date(`${d}T00:00:00`).toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric' })}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {data.therapists.map((t) => (
                <tr key={t.id}>
                  <td className="px-4 py-2.5 font-medium text-slate-900">{t.name}</td>
                  {data.days.map((d) => {
                    const onLeave = t.leave.find((l) => l.from <= d && l.to >= d)
                    const hours = t.hours[String(weekdayIndex(d))] ?? []
                    const booked = t.appointments[d] ?? 0
                    return (
                      <td key={d} className="px-2 py-2 text-center align-top">
                        {onLeave ? (
                          <span className="inline-block rounded bg-amber-50 px-1.5 py-0.5 text-xs text-amber-800 ring-1 ring-inset ring-amber-200" title={onLeave.reason ?? undefined}>
                            Leave
                          </span>
                        ) : hours.length ? (
                          <>
                            {hours.map((h) => (
                              <span key={h} className="block text-[11px] text-slate-600">
                                {h}
                              </span>
                            ))}
                            {booked > 0 && (
                              <Link to={`/app/appointments?date=${d}`} className="mt-0.5 inline-block rounded bg-brand-50 px-1.5 text-[11px] font-medium text-brand-800 hover:underline">
                                {booked} booked
                              </Link>
                            )}
                          </>
                        ) : (
                          <span className="text-xs text-slate-300">—</span>
                        )}
                      </td>
                    )
                  })}
                </tr>
              ))}
            </tbody>
          </table>
        </Card>
      )}
    </>
  )
}

/** Therapy → Home Programs: what families were asked to practise at home after each session. */
export function HomeProgramsPage() {
  const { data: therapists } = useTherapists()
  const [therapistId, setTherapistId] = useState('')
  const [search, setSearch] = useState('')
  const q = useDebounced(search)
  const [page, setPage] = useState(1)
  const { data, isLoading } = useQuery({
    queryKey: ['home-programs', therapistId, q, page],
    queryFn: async () =>
      (
        await api.get<{ data: { id: number; date: string; patient: { id: number; name: string; patient_code: string }; therapist: string | null; service: string | null; home_practice: string; practice_feedback: PracticeLog[] }[]; meta: Meta }>(
          '/therapy/home-programs',
          { params: { therapist_id: therapistId || undefined, q: q || undefined, page } },
        )
      ).data,
  })

  return (
    <>
      <PageHeader title="Home Programs" description="Home practice given in finalized therapy sessions — parents see the same text in their portal." />
      <Card className="mb-4 flex flex-col gap-2 p-3 sm:flex-row">
        <div className="relative sm:w-72">
          <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
          <Input value={search} onChange={(e) => (setSearch(e.target.value), setPage(1))} placeholder="Child or patient ID" className="pl-9" aria-label="Search" />
        </div>
        <Select value={therapistId} onChange={(e) => (setTherapistId(e.target.value), setPage(1))} className="sm:w-64" aria-label="Therapist">
          <option value="">All therapists</option>
          {therapists?.map((t) => (
            <option key={t.id} value={t.id}>
              {t.name}
            </option>
          ))}
        </Select>
      </Card>
      <Card className="overflow-hidden">
        {isLoading ? (
          <Spinner className="m-5 text-brand-600" />
        ) : !data?.data.length ? (
          <p className="p-5 text-sm text-slate-500">No home practice recorded yet.</p>
        ) : (
          <ul className="divide-y divide-slate-100">
            {data.data.map((s) => (
              <li key={s.id} className="px-4 py-3">
                <div className="flex flex-wrap items-baseline justify-between gap-2">
                  <Link to={`/app/patients/${s.patient.id}?tab=therapy`} className="font-medium text-slate-900 hover:text-brand-700">
                    {s.patient.name}
                  </Link>
                  <span className="text-xs text-slate-500">
                    {s.date} · {s.service} · {s.therapist}
                  </span>
                </div>
                <p className="mt-1 whitespace-pre-line text-sm text-slate-700">{s.home_practice}</p>
                <PracticeFeedbackSummary logs={s.practice_feedback} />
              </li>
            ))}
          </ul>
        )}
        <Pager meta={data?.meta} onPage={setPage} />
      </Card>
    </>
  )
}

/** Therapy → Progress Reports: download the progress report PDF of any child in a programme. */
export function ProgressReportsPage() {
  const [search, setSearch] = useState('')
  const q = useDebounced(search)
  const [page, setPage] = useState(1)
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const { data, isLoading } = useQuery({
    queryKey: ['progress-reports', q, page],
    queryFn: async () =>
      (
        await api.get<{
          data: { patient: { id: number; name: string; patient_code: string }; programmes: string[]; last_assessment: string | null; active_plans: number; sessions_90_days: number }[]
          meta: Meta
        }>('/therapy/progress-reports', { params: { q: q || undefined, page } })
      ).data,
  })

  return (
    <>
      <PageHeader title="Progress Reports" description="The PDF lists programmes, plan goals with progress, sessions and assessments. Leave the dates empty for the last 3 months." />
      <Card className="mb-4 flex flex-col gap-2 p-3 sm:flex-row">
        <div className="relative sm:w-72">
          <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
          <Input value={search} onChange={(e) => (setSearch(e.target.value), setPage(1))} placeholder="Child or patient ID" className="pl-9" aria-label="Search" />
        </div>
        <Input type="date" value={from} onChange={(e) => setFrom(e.target.value)} className="sm:w-44" aria-label="Report from" />
        <Input type="date" value={to} onChange={(e) => setTo(e.target.value)} className="sm:w-44" aria-label="Report to" />
      </Card>
      <Card className="overflow-hidden">
        {isLoading ? (
          <Spinner className="m-5 text-brand-600" />
        ) : !data?.data.length ? (
          <p className="p-5 text-sm text-slate-500">No child is in a programme.</p>
        ) : (
          <ul className="divide-y divide-slate-100">
            {data.data.map((r) => (
              <li key={r.patient.id} className="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm">
                <div>
                  <Link to={`/app/patients/${r.patient.id}?tab=assessments`} className="font-medium text-slate-900 hover:text-brand-700">
                    {r.patient.name}
                  </Link>{' '}
                  <span className="text-xs text-slate-500">{r.patient.patient_code}</span>
                  <p className="text-xs text-slate-500">
                    {r.programmes.join(' · ')} — {r.active_plans} active plan(s), {r.sessions_90_days} sessions in 90 days
                    {r.last_assessment ? `, last assessed ${r.last_assessment}` : ', not assessed yet'}
                  </p>
                </div>
                <a href={progressReportUrl(r.patient.id, from || undefined, to || undefined)} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 rounded-lg px-3 py-2 text-slate-600 hover:bg-slate-100">
                  <FileText className="size-4" /> PDF
                </a>
              </li>
            ))}
          </ul>
        )}
        <Pager meta={data?.meta} onPage={setPage} />
      </Card>
    </>
  )
}

interface ServiceRow {
  id: number
  name: string
  name_bn: string | null
  category: 'therapy' | 'training' | 'assessment' | 'consultation'
  default_duration_min: number
  default_price: string | null
  is_bookable_online: boolean
  show_on_website: boolean
  is_active: boolean
  therapists: number
}

const categoryLabel = { therapy: 'Therapy', assessment: 'Assessment', consultation: 'Consultation', training: 'Training' }

/** Therapy → Therapy Services: what the center offers, default length and price. Website text is edited in Website / CMS. */
export function ServicesPage() {
  const { can } = useAuth()
  const [editing, setEditing] = useState<ServiceRow | 'new' | null>(null)
  const { data, isLoading } = useQuery({ queryKey: ['service-catalog'], queryFn: async () => (await api.get<{ data: ServiceRow[] }>('/service-catalog')).data.data })

  return (
    <>
      <PageHeader
        title="Therapy Services"
        description="Default length and price are used when booking and billing. Which therapist gives which service is set on each therapist."
        actions={
          can('packages.manage') && (
            <Button onClick={() => setEditing('new')}>
              <Plus className="size-4" /> New service
            </Button>
          )
        }
      />
      <Card className="overflow-x-auto">
        {isLoading ? (
          <Spinner className="m-5 text-brand-600" />
        ) : (
          <table className="w-full text-sm">
            <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
              <tr>
                <th className="px-4 py-2.5 font-medium">Service</th>
                <th className="px-4 py-2.5 font-medium">Kind</th>
                <th className="px-4 py-2.5 font-medium">Length</th>
                <th className="px-4 py-2.5 font-medium">Price</th>
                <th className="px-4 py-2.5 font-medium">Therapists</th>
                <th className="px-4 py-2.5 font-medium" />
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {data?.map((s) => (
                <tr key={s.id} className={cn(!s.is_active && 'opacity-50')}>
                  <td className="px-4 py-2.5">
                    <p className="font-medium text-slate-900">{s.name}</p>
                    <p className="text-xs text-slate-500">
                      {s.name_bn}
                      {s.is_bookable_online && ' · parents can request'}
                      {!s.is_active && ' · not offered'}
                    </p>
                  </td>
                  <td className="px-4 py-2.5">
                    <Badge tone={s.category === 'therapy' ? 'blue' : s.category === 'training' ? 'green' : 'gray'}>{categoryLabel[s.category]}</Badge>
                  </td>
                  <td className="px-4 py-2.5 text-slate-600">{s.default_duration_min} min</td>
                  <td className="px-4 py-2.5 text-slate-600">{s.default_price ? `৳${Number(s.default_price).toLocaleString('en-IN')}` : '—'}</td>
                  <td className="px-4 py-2.5 text-slate-600">{s.category === 'training' ? '—' : s.therapists}</td>
                  <td className="px-4 py-2.5 text-right">
                    {can('packages.manage') && (
                      <button onClick={() => setEditing(s)} aria-label={`Edit ${s.name}`} className="text-slate-400 hover:text-slate-700">
                        <Pencil className="size-4" />
                      </button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </Card>
      {editing && <ServiceModal service={editing === 'new' ? undefined : editing} onClose={() => setEditing(null)} />}
    </>
  )
}

function ServiceModal({ service, onClose }: { service?: ServiceRow; onClose: () => void }) {
  const qc = useQueryClient()
  const [v, setV] = useState({
    name: service?.name ?? '',
    name_bn: service?.name_bn ?? '',
    category: service?.category ?? 'therapy',
    default_duration_min: String(service?.default_duration_min ?? 45),
    default_price: service?.default_price ?? '',
    is_bookable_online: service?.is_bookable_online ?? true,
    show_on_website: service?.show_on_website ?? true,
    is_active: service?.is_active ?? true,
  })
  const [errors, setErrors] = useState<Record<string, string>>({})
  const save = useMutation({
    mutationFn: () => {
      const body = { ...v, default_duration_min: Number(v.default_duration_min), default_price: v.default_price === '' ? null : Number(v.default_price) }
      return service ? api.put(`/service-catalog/${service.id}`, body) : api.post('/service-catalog', body)
    },
    onSuccess: () => (qc.invalidateQueries({ queryKey: ['service-catalog'] }), qc.invalidateQueries({ queryKey: ['bookable-services'] }), onClose()),
    onError: (e) => setErrors(validationErrors(e)),
  })

  return (
    <Modal open title={service ? `Edit ${service.name}` : 'New service'} onClose={onClose}>
      <div className="space-y-3">
        {save.isError && !Object.keys(errors).length && <Alert>{errorMessage(save.error)}</Alert>}
        <div className="grid gap-3 sm:grid-cols-2">
          <Field label="Name" htmlFor="sv_name" error={errors.name}>
            <Input id="sv_name" value={v.name} onChange={(e) => setV({ ...v, name: e.target.value })} />
          </Field>
          <Field label="Name in Bangla" htmlFor="sv_bn">
            <Input id="sv_bn" value={v.name_bn} onChange={(e) => setV({ ...v, name_bn: e.target.value })} />
          </Field>
          <Field label="Kind" htmlFor="sv_cat">
            <Select id="sv_cat" value={v.category} onChange={(e) => setV({ ...v, category: e.target.value as ServiceRow['category'] })}>
              {Object.entries(categoryLabel).map(([k, l]) => (
                <option key={k} value={k}>
                  {l}
                </option>
              ))}
            </Select>
          </Field>
          <Field label="Length (minutes)" htmlFor="sv_len" error={errors.default_duration_min}>
            <Input id="sv_len" type="number" value={v.default_duration_min} onChange={(e) => setV({ ...v, default_duration_min: e.target.value })} />
          </Field>
          <Field label="Price per session (৳)" htmlFor="sv_price" error={errors.default_price} hint="Packages can have their own price">
            <Input id="sv_price" type="number" value={v.default_price} onChange={(e) => setV({ ...v, default_price: e.target.value })} />
          </Field>
        </div>
        {(
          [
            ['is_bookable_online', 'Parents can request it from the website and portal'],
            ['show_on_website', 'Show on the public website'],
            ['is_active', 'Offered now'],
          ] as const
        ).map(([k, label]) => (
          <label key={k} className="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" className="size-4 accent-brand-600" checked={v[k]} onChange={(e) => setV({ ...v, [k]: e.target.checked })} /> {label}
          </label>
        ))}
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
