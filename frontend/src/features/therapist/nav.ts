import { CalendarClock, ClipboardList, HeartPulse, UserRound, Users } from 'lucide-react'
import type { BottomNavItem } from '../../layouts/MobileAppLayout'

export const therapistNav: BottomNavItem[] = [
  { label: 'Today', to: '/therapist', icon: CalendarClock },
  { label: 'Patients', to: '/therapist/patients', icon: Users },
  { label: 'Sessions', to: '/therapist/sessions', icon: HeartPulse },
  { label: 'Assessments', to: '/therapist/assessments', icon: ClipboardList },
  { label: 'Profile', to: '/therapist/profile', icon: UserRound },
]
