import { useQuery } from '@tanstack/react-query'
import {
  Activity,
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
import { taka } from '../billing/api'

interface Stat {
  key: string
  label: string
  icon: LucideIcon
  to: string
  money?: boolean
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
  const { data } = useQuery({ queryKey: ['dashboard'], queryFn: async () => (await api.get<{ data: Record<string, number> }>('/dashboard')).data.data })
  const visible = data ? stats.filter((s) => s.key in data) : stats

  return (
    <>
      <PageHeader title={`Welcome, ${user?.name}`} description={`${user?.primary_role_label}${branches ? ` · ${branches}` : ''}`} />

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        {visible.map((stat) => (
          <Link key={stat.key} to={stat.to}>
            <Card className="flex items-center gap-4 p-5 transition hover:border-brand-200">
              <span className="flex size-11 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                <stat.icon className="size-5" />
              </span>
              <div>
                <p className="text-sm text-slate-500">{stat.label}</p>
                <p className={`text-2xl font-semibold ${data ? (stat.key === 'total_due' && data[stat.key] > 0 ? 'text-red-600' : 'text-slate-900') : 'text-slate-300'}`}>
                  {data ? (stat.money ? taka(data[stat.key]) : data[stat.key]) : '—'}
                </p>
              </div>
            </Card>
          </Link>
        ))}
      </div>
    </>
  )
}
