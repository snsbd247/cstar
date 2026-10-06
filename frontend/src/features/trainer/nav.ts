import { CalendarDays, ClipboardCheck, NotebookPen, ReceiptText, Users } from 'lucide-react'
import type { BottomNavItem } from '../../layouts/MobileAppLayout'

export const trainerNav: BottomNavItem[] = [
  { label: 'Today', to: '/trainer', icon: CalendarDays },
  { label: 'Students', to: '/trainer/students', icon: Users },
  { label: 'Attendance', to: '/trainer/attendance', icon: ClipboardCheck },
  { label: 'Records', to: '/trainer/records', icon: NotebookPen },
  { label: 'Payslips', to: '/trainer/payslips', icon: ReceiptText },
]
