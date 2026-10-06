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
import { Card, PageHeader } from '../../components/ui/Card'
import { useAuth } from '../../contexts/useAuth'

interface Stat {
  label: string
  icon: LucideIcon
  sprint: number
}

/** Plan §১২ dashboard cards; each fills with live numbers once its module ships. */
const stats: Stat[] = [
  { label: 'Total Children', icon: UserRound, sprint: 6 },
  { label: 'Regular Students', icon: GraduationCap, sprint: 7 },
  { label: 'Therapy-only Patients', icon: HeartPulse, sprint: 8 },
  { label: 'Student + Therapy', icon: Activity, sprint: 8 },
  { label: 'Active Trainers', icon: Dumbbell, sprint: 7 },
  { label: 'Active Therapists', icon: Stethoscope, sprint: 8 },
  { label: "Today's Appointments", icon: CalendarCheck, sprint: 8 },
  { label: "Today's Collection", icon: Wallet, sprint: 10 },
  { label: 'Total Due', icon: Users, sprint: 10 },
]

export default function AdminDashboard() {
  const { user } = useAuth()
  const branches = user?.branches?.map((b) => b.name).join(', ')

  return (
    <>
      <PageHeader
        title={`Welcome, ${user?.name}`}
        description={`${user?.primary_role_label}${branches ? ` · ${branches}` : ''}`}
      />

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        {stats.map((stat) => (
          <Card key={stat.label} className="flex items-center gap-4 p-5">
            <span className="flex size-11 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
              <stat.icon className="size-5" />
            </span>
            <div>
              <p className="text-sm text-slate-500">{stat.label}</p>
              <p className="text-2xl font-semibold text-slate-300">—</p>
              <p className="text-[11px] text-slate-400">Live from Sprint {stat.sprint}</p>
            </div>
          </Card>
        ))}
      </div>
    </>
  )
}
