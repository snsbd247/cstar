import { useQuery } from '@tanstack/react-query'
import { CheckInCard } from '../staff/StaffAttendance'
import {
  Activity,
  ArrowRight,
  CalendarCheck,
  Dumbbell,
  GraduationCap,
  HeartPulse,
  Stethoscope,
  UserRound,
  Users,
  Wallet,
  type LucideIcon,
} from 'lucide-react'
import { Link } from 'react-router'
import { api } from '../../api/client'
import { Card, PageHeader } from '../../components/ui/Card'
import { useAuth } from '../../contexts/useAuth'
import { cn } from '../../utils/cn'
import { taka } from '../billing/api'

interface Stat {
  key: string
  label: string
  icon: LucideIcon
  to: string
  money?: boolean
}

type Point = { month: string; value?: number | null; training?: number; therapy?: number }

interface Dashboard {
  data: Record<string, number>
  charts: Partial<Record<'registrations' | 'enrollments' | 'sessions' | 'attendance' | 'revenue', Point[]>>
  actions: { kind: string; count: number; label: string; url: string }[]
}

/** Plan §১২ dashboard cards. Cards the user has no permission for are not returned by the API and stay hidden. */
const stats: Stat[] = [
  { key: 'children', label: 'Total Children', icon: UserRound, to: '/app/patients' },
  { key: 'students', label: 'Regular Students', icon: GraduationCap, to: '/app/students' },
  { key: 'therapy_only', label: 'Therapy-only Patients', icon: HeartPulse, to: '/app/patients' },
  { key: 'both', label: 'Student + Therapy', icon: Activity, to: '/app/patients' },
  { key: 'trainers', label: 'Active Trainers', icon: Dumbbell, to: '/app/trainers' },
  { key: 'therapists', label: 'Active Therapists', icon: Stethoscope, to: '/app/therapists' },
  { key: 'appointments_today', label: "Today's Appointments", icon: CalendarCheck, to: '/app/appointments' },
  { key: 'collection_today', label: "Today's Collection", icon: Wallet, to: '/app/payments', money: true },
  { key: 'total_due', label: 'Total Due', icon: Users, to: '/app/invoices?tab=dues', money: true },
]

export default function AdminDashboard() {
  const { user } = useAuth()
  const branches = user?.branches?.map((b) => b.name).join(', ')
  const { data } = useQuery({ queryKey: ['dashboard'], queryFn: async () => (await api.get<Dashboard>('/dashboard')).data })
  const visible = data ? stats.filter((s) => s.key in data.data) : stats
  const charts = data?.charts ?? {}

  return (
    <>
      <PageHeader title={`Welcome, ${user?.name}`} description={`${user?.primary_role_label}${branches ? ` · ${branches}` : ''}`} />
      <CheckInCard />

      {!!data?.actions.length && (
        <Card className="mb-4 p-4">
          <h2 className="mb-2 font-semibold text-slate-900">Needs attention</h2>
          <ul className="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
            {data.actions.map((a, i) => (
              <li key={i}>
                <Link to={a.url} className="flex items-center justify-between gap-2 rounded-lg border border-slate-100 px-3 py-2 text-sm hover:border-brand-200 hover:bg-brand-50/40">
                  <span className="truncate text-slate-700">{a.label}</span>
                  <span className={cn('flex items-center gap-1 font-semibold', a.kind === 'due' ? 'text-red-600' : 'text-amber-700')}>
                    {a.kind === 'due' ? taka(a.count) : a.count} <ArrowRight className="size-3.5" />
                  </span>
                </Link>
              </li>
            ))}
          </ul>
        </Card>
      )}

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        {visible.map((stat) => (
          <Link key={stat.key} to={stat.to}>
            <Card className="flex items-center gap-4 p-5 transition hover:border-brand-200">
              <span className="flex size-11 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                <stat.icon className="size-5" />
              </span>
              <div>
                <p className="text-sm text-slate-500">{stat.label}</p>
                <p className={`text-2xl font-semibold ${data ? (stat.key === 'total_due' && data.data[stat.key] > 0 ? 'text-red-600' : 'text-slate-900') : 'text-slate-300'}`}>
                  {data ? (stat.money ? taka(data.data[stat.key]) : data.data[stat.key]) : '—'}
                </p>
              </div>
            </Card>
          </Link>
        ))}
      </div>

      <div className="mt-5 grid gap-4 lg:grid-cols-2">
        {charts.revenue && <MiniChart title="Monthly revenue" points={charts.revenue} series={[['value', 'Revenue', 'bg-brand-500']]} money />}
        {charts.enrollments && (
          <MiniChart
            title="New enrollments — training vs therapy"
            points={charts.enrollments}
            series={[
              ['training', 'Training', 'bg-brand-500'],
              ['therapy', 'Therapy', 'bg-sky-brand-500'],
            ]}
          />
        )}
        {charts.registrations && <MiniChart title="New registrations" points={charts.registrations} series={[['value', 'Children', 'bg-sky-brand-500']]} />}
        {charts.sessions && <MiniChart title="Therapy sessions held" points={charts.sessions} series={[['value', 'Sessions', 'bg-brand-500']]} />}
        {charts.attendance && <MiniChart title="Training attendance rate" points={charts.attendance} series={[['value', 'Attendance %', 'bg-amber-400']]} percent />}
      </div>
    </>
  )
}

/** Six-month bar chart drawn with CSS (no chart library on shared hosting). */
function MiniChart({ title, points, series, money, percent }: { title: string; points: Point[]; series: [keyof Point, string, string][]; money?: boolean; percent?: boolean }) {
  const peak = percent ? 100 : Math.max(1, ...points.flatMap((p) => series.map(([k]) => Number(p[k]) || 0)))
  const show = (v: number) => (money ? taka(v) : percent ? `${v}%` : String(v))

  return (
    <Card className="p-5">
      <h3 className="mb-3 font-semibold text-slate-900">{title}</h3>
      <div className="flex h-40 items-end gap-3" role="img" aria-label={title}>
        {points.map((p) => (
          <div key={p.month} className="flex flex-1 flex-col items-center gap-1">
            <div className="flex h-32 w-full items-end justify-center gap-1">
              {series.map(([k, label, color]) => (
                <div key={k} className={cn('w-full max-w-7 rounded-t', color)} style={{ height: `${((Number(p[k]) || 0) / peak) * 100}%` }} title={`${label}: ${show(Number(p[k]) || 0)}`} />
              ))}
            </div>
            <span className="text-[11px] text-slate-500">{p.month}</span>
          </div>
        ))}
      </div>
      {series.length > 1 && (
        <p className="mt-2 flex gap-3 text-xs text-slate-500">
          {series.map(([k, label, color]) => (
            <span key={k} className="flex items-center gap-1">
              <span className={cn('size-2.5 rounded-sm', color)} /> {label}
            </span>
          ))}
        </p>
      )}
    </Card>
  )
}
