import { useQuery } from '@tanstack/react-query'
import { ChevronLeft, ChevronRight, Clock, Search } from 'lucide-react'
import { useEffect, useMemo, useState, type ReactNode } from 'react'
import { Link, useNavigate } from 'react-router'
import { api } from '../../api/client'
import { Button } from '../../components/ui/Button'
import { Card, PageHeader } from '../../components/ui/Card'
import { Input, Select } from '../../components/ui/Field'
import { Pager } from '../../components/ui/Pager'
import { Spinner } from '../../components/ui/Spinner'
import { useAuth } from '../../contexts/useAuth'
import { cn } from '../../utils/cn'
import { todayISO } from '../../utils/format'
import { statusStyle, useTherapists, type Appointment, type AppointmentStatus } from './api'
import { AppointmentActions, StatusPill } from './components/AppointmentActions'

type Meta = { current_page: number; last_page: number; total: number }
const iso = (d: Date) => d.toLocaleDateString('en-CA')
const addDays = (date: string, n: number) => {
  const d = new Date(`${date}T00:00:00`)
  d.setDate(d.getDate() + n)
  return iso(d)
}
const typeLabel: Record<string, string> = { therapy: 'Therapy', assessment: 'Assessment', consultation: 'Consultation', follow_up: 'Follow-up' }

/** Appointments → All Appointments: searchable list over any period, newest first. */
export function AllAppointmentsPage() {
  const [from, setFrom] = useState(todayISO().slice(0, 8) + '01')
  const [to, setTo] = useState('')
  const [status, setStatus] = useState('')
  const [type, setType] = useState('')
  const [therapistId, setTherapistId] = useState('')
  const [search, setSearch] = useState('')
  const [q, setQ] = useState('')
  const [page, setPage] = useState(1)
  const { data: therapists } = useTherapists()

  useEffect(() => {
    const t = setTimeout(() => (setQ(search.trim()), setPage(1)), 300)
    return () => clearTimeout(t)
  }, [search])

  const params = { from: from || undefined, to: to || undefined, status: status || undefined, type: type || undefined, therapist_id: therapistId || undefined, q: q || undefined, desc: 1, per_page: 30, page }
  const { data, isLoading } = useQuery({
    queryKey: ['appointments', 'all', params],
    queryFn: async () => (await api.get<{ data: Appointment[]; meta: Meta }>('/appointments', { params })).data,
  })
  const reset = (set: (v: string) => void) => (v: string) => (set(v), setPage(1))

  return (
    <>
      <PageHeader title="All Appointments" description="Every therapy, assessment and consultation appointment — search by child, ID or appointment number." />
      <Card className="mb-4 grid gap-2 p-3 sm:grid-cols-2 lg:grid-cols-6">
        <div className="relative lg:col-span-2">
          <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
          <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Child, patient ID or APT no." className="pl-9" aria-label="Search appointments" />
        </div>
        <Input type="date" value={from} onChange={(e) => reset(setFrom)(e.target.value)} aria-label="From date" />
        <Input type="date" value={to} onChange={(e) => reset(setTo)(e.target.value)} aria-label="To date" />
        <Select value={status} onChange={(e) => reset(setStatus)(e.target.value)} aria-label="Status">
          <option value="">Any status</option>
          {(Object.keys(statusStyle) as AppointmentStatus[]).map((s) => (
            <option key={s} value={s}>
              {statusStyle[s].label}
            </option>
          ))}
        </Select>
        <Select value={type} onChange={(e) => reset(setType)(e.target.value)} aria-label="Type">
          <option value="">Any type</option>
          {Object.entries(typeLabel).map(([k, l]) => (
            <option key={k} value={k}>
              {l}
            </option>
          ))}
        </Select>
        <Select value={therapistId} onChange={(e) => reset(setTherapistId)(e.target.value)} aria-label="Therapist" className="lg:col-span-2">
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
          <div className="p-6">
            <Spinner className="text-brand-600" />
          </div>
        ) : !data?.data.length ? (
          <p className="p-6 text-sm text-slate-500">No appointments match.</p>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                  <th className="px-4 py-2.5 font-medium">When</th>
                  <th className="px-4 py-2.5 font-medium">Child</th>
                  <th className="px-4 py-2.5 font-medium">Service</th>
                  <th className="px-4 py-2.5 font-medium">Therapist</th>
                  <th className="px-4 py-2.5 font-medium">Status</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {data.data.map((a) => (
                  <tr key={a.id} className="hover:bg-slate-50">
                    <td className="whitespace-nowrap px-4 py-2.5">
                      <Link to={`/app/appointments?date=${a.date}`} className="font-medium text-slate-900 hover:text-brand-700">
                        {new Date(`${a.date}T00:00:00`).toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' })}
                      </Link>
                      <span className="block text-xs text-slate-500">
                        {a.start_time}–{a.end_time} · {a.appointment_code}
                      </span>
                    </td>
                    <td className="px-4 py-2.5">
                      <Link to={`/app/patients/${a.patient?.id}?tab=therapy`} className="text-slate-900 hover:text-brand-700">
                        {a.patient?.name}
                      </Link>
                      <span className="block text-xs text-slate-500">{a.patient?.patient_code}</span>
                    </td>
                    <td className="px-4 py-2.5 text-slate-700">
                      {a.service?.name}
                      <span className="block text-xs text-slate-500">
                        {typeLabel[a.type]}
                        {a.source === 'recurring' && ' · weekly'}
                      </span>
                    </td>
                    <td className="px-4 py-2.5 text-slate-700">{a.therapist?.name}</td>
                    <td className="px-4 py-2.5">
                      <StatusPill status={a.status} />
                      {a.cancel_reason && <span className="block max-w-48 truncate text-xs text-slate-500">{a.cancel_reason}</span>}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
        <Pager meta={data?.meta} onPage={setPage} />
      </Card>
    </>
  )
}

const palette = ['bg-brand-50 text-brand-800 ring-brand-200', 'bg-sky-brand-50 text-sky-brand-800 ring-sky-brand-200', 'bg-amber-50 text-amber-800 ring-amber-200', 'bg-violet-50 text-violet-800 ring-violet-200', 'bg-rose-50 text-rose-800 ring-rose-200', 'bg-teal-50 text-teal-800 ring-teal-200']
const weekdays = ['Sat', 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri']

/** Saturday-first week (the center's week starts on Saturday). */
function weekStart(date: string) {
  const d = new Date(`${date}T00:00:00`)
  const back = (d.getDay() + 1) % 7
  d.setDate(d.getDate() - back)
  return iso(d)
}

/** Appointments → Calendar: month or week at a glance, coloured by therapist. Click a day for its board. */
export function CalendarPage() {
  const navigate = useNavigate()
  const [view, setView] = useState<'month' | 'week'>('month')
  const [anchor, setAnchor] = useState(todayISO())
  const [therapistId, setTherapistId] = useState('')
  const { data: therapists } = useTherapists()

  const { start, days } = useMemo(() => {
    if (view === 'week') {
      const s = weekStart(anchor)
      return { start: s, days: Array.from({ length: 7 }, (_, i) => addDays(s, i)) }
    }
    const first = anchor.slice(0, 8) + '01'
    const s = weekStart(first)
    const lastDay = new Date(Number(anchor.slice(0, 4)), Number(anchor.slice(5, 7)), 0).getDate()
    const end = anchor.slice(0, 8) + String(lastDay).padStart(2, '0')
    const list: string[] = []
    for (let d = s; d <= end || list.length % 7 !== 0; d = addDays(d, 1)) list.push(d)
    return { start: s, days: list }
  }, [view, anchor])
  const end = days[days.length - 1]

  const { data, isLoading } = useQuery({
    queryKey: ['appointments', 'calendar', start, end, therapistId],
    queryFn: async () => (await api.get<{ data: Appointment[] }>('/appointments', { params: { from: start, to: end, therapist_id: therapistId || undefined, per_page: 500 } })).data.data,
  })
  const byDay = useMemo(() => {
    const map: Record<string, Appointment[]> = {}
    for (const a of data ?? []) if (!['cancelled', 'rescheduled'].includes(a.status)) (map[a.date] ??= []).push(a)
    return map
  }, [data])
  const colour = (id?: number) => palette[(therapists?.findIndex((t) => t.id === id) ?? 0) % palette.length]
  const month = anchor.slice(0, 7)
  const title =
    view === 'month'
      ? new Date(`${anchor.slice(0, 8)}01T00:00:00`).toLocaleDateString('en-GB', { month: 'long', year: 'numeric' })
      : `${new Date(`${start}T00:00:00`).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })} – ${new Date(`${end}T00:00:00`).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })}`
  const move = (dir: number) => {
    if (view === 'week') return setAnchor(addDays(anchor, dir * 7))
    const d = new Date(`${anchor.slice(0, 8)}01T00:00:00`)
    d.setMonth(d.getMonth() + dir)
    setAnchor(iso(d))
  }

  return (
    <>
      <PageHeader title="Calendar" description="Cancelled and rescheduled appointments are hidden. Click a day to open its board." />
      <Card className="mb-4 flex flex-wrap items-center gap-2 p-3">
        <Button variant="secondary" onClick={() => move(-1)} aria-label="Previous">
          <ChevronLeft className="size-4" />
        </Button>
        <span className="min-w-44 text-center font-semibold text-slate-900">{title}</span>
        <Button variant="secondary" onClick={() => move(1)} aria-label="Next">
          <ChevronRight className="size-4" />
        </Button>
        <Button variant="ghost" onClick={() => setAnchor(todayISO())}>
          Today
        </Button>
        <div className="flex rounded-lg border border-slate-200 p-0.5">
          {(['month', 'week'] as const).map((v) => (
            <button key={v} onClick={() => setView(v)} className={cn('rounded-md px-3 py-1.5 text-sm capitalize', view === v ? 'bg-brand-600 text-white' : 'text-slate-600 hover:bg-slate-50')}>
              {v}
            </button>
          ))}
        </div>
        <Select value={therapistId} onChange={(e) => setTherapistId(e.target.value)} className="ml-auto sm:w-64" aria-label="Therapist">
          <option value="">All therapists</option>
          {therapists?.map((t) => (
            <option key={t.id} value={t.id}>
              {t.name}
            </option>
          ))}
        </Select>
      </Card>

      {isLoading ? (
        <Spinner className="text-brand-600" />
      ) : (
        <Card className="overflow-x-auto">
          <div className="grid min-w-[720px] grid-cols-7">
            {weekdays.map((d) => (
              <div key={d} className={cn('border-b border-slate-200 bg-slate-50 px-2 py-2 text-center text-xs font-semibold uppercase text-slate-500', d === 'Fri' && 'text-slate-400')}>
                {d}
              </div>
            ))}
            {days.map((d) => {
              const list = byDay[d] ?? []
              const shown = view === 'month' ? list.slice(0, 3) : list
              return (
                <button
                  key={d}
                  onClick={() => navigate(`/app/appointments?date=${d}`)}
                  className={cn(
                    'flex flex-col gap-1 border-b border-r border-slate-100 p-1.5 text-left align-top hover:bg-slate-50',
                    view === 'month' ? 'min-h-28' : 'min-h-80',
                    view === 'month' && d.slice(0, 7) !== month && 'bg-slate-50/60 text-slate-400',
                  )}
                >
                  <span className={cn('inline-flex size-6 items-center justify-center rounded-full text-xs font-medium', d === todayISO() ? 'bg-brand-600 text-white' : 'text-slate-600')}>{Number(d.slice(8))}</span>
                  {shown.map((a) => (
                    <span key={a.id} className={cn('truncate rounded px-1.5 py-0.5 text-[11px] ring-1 ring-inset', colour(a.therapist?.id))} title={`${a.start_time} ${a.patient?.name} — ${a.service?.name} (${a.therapist?.name})`}>
                      {a.start_time} {a.patient?.name}
                      {view === 'week' && <span className="block truncate opacity-75">{a.service?.name} · {a.therapist?.name}</span>}
                    </span>
                  ))}
                  {list.length > shown.length && <span className="px-1 text-[11px] text-slate-500">+{list.length - shown.length} more</span>}
                </button>
              )
            })}
          </div>
        </Card>
      )}
      {therapists && therapists.length > 0 && (
        <div className="mt-3 flex flex-wrap gap-2 text-xs">
          {therapists
            .filter((t) => t.status === 'active')
            .map((t) => (
              <span key={t.id} className={cn('rounded px-2 py-0.5 ring-1 ring-inset', colour(t.id))}>
                {t.name}
              </span>
            ))}
        </div>
      )}
    </>
  )
}

// "now" is the time of the last refresh, so the numbers move every 30 seconds with the data.
const minutesSince = (stamp: string, now: number) => Math.max(0, Math.round((now - new Date(stamp).getTime()) / 60000))
const minutesLate = (a: Appointment, now: number) => {
  const [h, m] = a.start_time.split(':').map(Number)
  const start = new Date(now)
  start.setHours(h, m, 0, 0)
  return Math.round((now - start.getTime()) / 60000)
}

/** Appointments → Check-in / Queue: today at the front desk — who is expected, who is waiting, who is done. Refreshes itself. */
export function QueuePage() {
  const { can } = useAuth()
  const today = todayISO()
  const { data, isLoading, dataUpdatedAt } = useQuery({
    queryKey: ['appointments', 'queue', today],
    queryFn: async () => (await api.get<{ data: Appointment[] }>('/appointments', { params: { date: today, per_page: 500 } })).data.data,
    refetchInterval: 30_000,
  })
  const list = data ?? []
  const expected = list.filter((a) => ['pending', 'confirmed'].includes(a.status))
  const waiting = list.filter((a) => a.status === 'checked_in')
  const done = list.filter((a) => ['completed', 'no_show', 'cancelled'].includes(a.status))

  return (
    <>
      <PageHeader
        title="Check-in / Queue"
        description={`Today, ${new Date(`${today}T00:00:00`).toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long' })} · updates every 30 seconds${dataUpdatedAt ? ` (last ${new Date(dataUpdatedAt).toLocaleTimeString('en-GB', { timeStyle: 'short' })})` : ''}`}
      />
      <div className="mb-4 grid gap-3 sm:grid-cols-3">
        {[
          ['Expected', expected.length, 'text-slate-900'],
          ['Waiting / in session', waiting.length, 'text-amber-700'],
          ['Finished today', done.filter((a) => a.status === 'completed').length, 'text-brand-700'],
        ].map(([label, n, tone]) => (
          <Card key={label as string} className="p-4">
            <p className="text-xs text-slate-500">{label}</p>
            <p className={cn('mt-1 text-2xl font-semibold', tone as string)}>{n}</p>
          </Card>
        ))}
      </div>
      {isLoading ? (
        <Spinner className="text-brand-600" />
      ) : (
        <div className="grid gap-4 lg:grid-cols-3">
          <QueueColumn title="Expected" empty="Nobody else is expected today.">
            {expected.map((a) => {
              const late = minutesLate(a, dataUpdatedAt)
              return (
                <li key={a.id} className="px-4 py-3">
                  <QueueLine a={a} />
                  {late > 10 && <p className="text-xs font-medium text-red-600">{late} min late</p>}
                  {can('appointments.manage') && (
                    <div className="mt-2">
                      <AppointmentActions appointment={a} compact />
                    </div>
                  )}
                </li>
              )
            })}
          </QueueColumn>
          <QueueColumn title="Waiting / in session" empty="Nobody is waiting.">
            {waiting.map((a) => (
              <li key={a.id} className="px-4 py-3">
                <QueueLine a={a} />
                <p className="flex items-center gap-1 text-xs text-amber-700">
                  <Clock className="size-3.5" />
                  {a.session ? 'In session — note started' : `Waiting ${a.checked_in_at ? minutesSince(a.checked_in_at, dataUpdatedAt) : 0} min`}
                </p>
              </li>
            ))}
          </QueueColumn>
          <QueueColumn title="Done" empty="Nothing finished yet.">
            {done.map((a) => (
              <li key={a.id} className="flex items-start justify-between gap-2 px-4 py-3 opacity-80">
                <QueueLine a={a} />
                <StatusPill status={a.status} />
              </li>
            ))}
          </QueueColumn>
        </div>
      )}
    </>
  )
}

function QueueColumn({ title, empty, children }: { title: string; empty: string; children: ReactNode[] }) {
  return (
    <Card className="overflow-hidden">
      <p className="border-b border-slate-100 bg-slate-50 px-4 py-2.5 text-sm font-semibold text-slate-700">
        {title} <span className="font-normal text-slate-500">({children.length})</span>
      </p>
      {children.length ? <ul className="divide-y divide-slate-100">{children}</ul> : <p className="px-4 py-4 text-sm text-slate-400">{empty}</p>}
    </Card>
  )
}

function QueueLine({ a }: { a: Appointment }) {
  return (
    <div>
      <p className="text-sm font-semibold text-slate-900">
        {a.start_time} ·{' '}
        <Link to={`/app/patients/${a.patient?.id}?tab=therapy`} className="hover:text-brand-700">
          {a.patient?.name}
        </Link>
      </p>
      <p className="text-xs text-slate-500">
        {a.service?.name} · {a.therapist?.name}
        {a.patient?.phone && (
          <>
            {' · '}
            <a href={`tel:${a.patient.phone}`} className="hover:underline">
              {a.patient.phone}
            </a>
          </>
        )}
      </p>
    </div>
  )
}
