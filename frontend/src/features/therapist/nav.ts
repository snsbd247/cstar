import { CalendarClock, CalendarDays, ClipboardList, HeartPulse, Users } from 'lucide-react'
import type { BottomNavItem } from '../../layouts/MobileAppLayout'

export const therapistNav: BottomNavItem[] = [
  { label: 'Today', to: '/therapist', icon: CalendarClock },
  { label: 'Schedule', to: '/therapist/schedule', icon: CalendarDays },
  { label: 'Patients', to: '/therapist/patients', icon: Users },
  { label: 'Sessions', to: '/therapist/sessions', icon: HeartPulse },
  { label: 'Assessments', to: '/therapist/assessments', icon: ClipboardList },
]
