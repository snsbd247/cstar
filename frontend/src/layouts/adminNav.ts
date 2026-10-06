import {
  Banknote,
  BarChart3,
  IdCard,
  ReceiptText,
  BookOpen,
  Coins,
  FileCheck2,
  HandCoins,
  Wallet,
  Bell,
  Building2,
  CalendarCheck,
  CalendarOff,
  ClipboardList,
  CreditCard,
  Dumbbell,
  FileText,
  GraduationCap,
  Globe,
  HeartPulse,
  Inbox,
  LayoutDashboard,
  Landmark,
  Package,
  Receipt,
  School,
  Settings,
  ShieldCheck,
  Stethoscope,
  UserRound,
  Users,
  type LucideIcon,
} from 'lucide-react'

export interface NavItem {
  label: string
  to: string
  icon: LucideIcon
  /** Shown when the user has any of these permissions (none = everyone in the admin app). */
  permissions?: string[]
  /** Roadmap sprint that delivers the page; set while the module is not built yet. */
  sprint?: number
}

export interface NavGroup {
  title?: string
  items: NavItem[]
}

/** Admin sidebar — Plan §২ Sitemap. Items with `sprint` open a "coming in Sprint N" page. */
export const adminNav: NavGroup[] = [
  { items: [{ label: 'Dashboard', to: '/app', icon: LayoutDashboard }] },
  {
    title: 'Children',
    items: [
      { label: 'Patients', to: '/app/patients', icon: UserRound, permissions: ['patients.view'] },
      { label: 'Students / Training', to: '/app/students', icon: GraduationCap, permissions: ['enrollments.view'] },
      { label: 'Assessments', to: '/app/assessments', icon: ClipboardList, permissions: ['assessments.view'] },
    ],
  },
  {
    title: 'Training',
    items: [
      { label: 'Classes', to: '/app/classes', icon: School, permissions: ['classes.view'] },
      { label: 'Trainers', to: '/app/trainers', icon: Dumbbell, permissions: ['trainers.view'] },
      { label: 'Training Sessions', to: '/app/training-sessions', icon: FileText, permissions: ['training_records.view'] },
    ],
  },
  {
    title: 'Therapy',
    items: [
      { label: 'Therapists', to: '/app/therapists', icon: Stethoscope, permissions: ['therapists.view'] },
      { label: 'Online Requests', to: '/app/online-requests', icon: Inbox, permissions: ['appointment_requests.manage'] },
      { label: 'Appointments', to: '/app/appointments', icon: CalendarCheck, permissions: ['appointments.view'] },
      { label: 'Therapy Sessions', to: '/app/therapy-sessions', icon: HeartPulse, permissions: ['therapy_sessions.view'] },
    ],
  },
  {
    title: 'Billing',
    items: [
      { label: 'Packages', to: '/app/packages', icon: Package, permissions: ['packages.view'] },
      { label: 'Invoices', to: '/app/invoices', icon: Receipt, permissions: ['invoices.view'] },
      { label: 'Payments', to: '/app/payments', icon: CreditCard, permissions: ['payments.view'] },
    ],
  },
  {
    title: 'Accounts',
    items: [
      { label: 'Accounts Dashboard', to: '/app/accounts', icon: Landmark, permissions: ['accounts.view'] },
      { label: 'Expenses', to: '/app/expenses', icon: HandCoins, permissions: ['accounts.expense.create'] },
      { label: 'Vouchers', to: '/app/accounts/vouchers', icon: FileCheck2, permissions: ['accounts.view'] },
      { label: 'Cash Closing', to: '/app/cash-closing', icon: Wallet, permissions: ['accounts.cash_closing'] },
      { label: 'Employees', to: '/app/employees', icon: IdCard, permissions: ['accounts.payroll.manage', 'accounts.payroll.approve'] },
      { label: 'Payroll', to: '/app/payroll', icon: Banknote, permissions: ['accounts.payroll.manage', 'accounts.payroll.approve'] },
      { label: 'Financial Reports', to: '/app/accounts/reports', icon: Coins, permissions: ['accounts.reports'] },
      { label: 'Books & Months', to: '/app/accounts/books', icon: BookOpen, permissions: ['accounts.view'] },
    ],
  },
  {
    title: 'Insights',
    items: [{ label: 'Reports', to: '/app/reports', icon: BarChart3, permissions: ['reports.view'], sprint: 14 }],
  },
  {
    title: 'Administration',
    items: [
      { label: 'Branches', to: '/app/branches', icon: Building2, permissions: ['branches.view', 'branches.manage'] },
      { label: 'Users', to: '/app/users', icon: Users, permissions: ['users.view', 'users.manage'] },
      { label: 'Roles & Permissions', to: '/app/roles', icon: ShieldCheck, permissions: ['users.view', 'roles.manage'] },
      { label: 'Holidays', to: '/app/holidays', icon: CalendarOff, permissions: ['branches.view'] },
      { label: 'My Payslips', to: '/app/my-payslips', icon: ReceiptText },
      { label: 'Website CMS', to: '/app/cms', icon: Globe, permissions: ['cms.manage', 'appointment_requests.manage'] },
      { label: 'Notifications', to: '/app/notifications', icon: Bell, permissions: ['notifications.send'], sprint: 14 },
      { label: 'Settings', to: '/app/settings', icon: Settings, permissions: ['settings.manage'], sprint: 16 },
    ],
  },
]
