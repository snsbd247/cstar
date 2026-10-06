import { CalendarDays, ClipboardCheck, NotebookPen, UserRound, Users } from 'lucide-react'
import type { BottomNavItem } from '../../layouts/MobileAppLayout'

export const trainerNav: BottomNavItem[] = [
  { label: 'Today', to: '/trainer', icon: CalendarDays },
  { label: 'Students', to: '/trainer/students', icon: Users },
  { label: 'Attendance', to: '/trainer/attendance', icon: ClipboardCheck },
  { label: 'Records', to: '/trainer/records', icon: NotebookPen },
  { label: 'Profile', to: '/trainer/profile', icon: UserRound },
]
