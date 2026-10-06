import {
  BarChart3,
  Bell,
  Building2,
  CalendarCheck,
  ClipboardList,
  GraduationCap,
  Globe,
  HeartPulse,
  History,
  IdCard,
  Landmark,
  LayoutDashboard,
  ListChecks,
  Package,
  Receipt,
  Settings,
  UserRound,
  Users,
  type LucideIcon,
} from 'lucide-react'

export interface NavItem {
  label: string
  /** Path, optionally with a query string that pre-selects a tab or filter on the page. */
  to: string
  /** Shown when the user has any of these permissions (none = everyone in the admin app). */
  permissions?: string[]
  /** Roadmap sprint that delivers the page; set while it is not built yet. */
  sprint?: number
}

export interface NavSection {
  label: string
  icon: LucideIcon
  /** A section without children is a single link (Dashboard). */
  to?: string
  items?: NavItem[]
}

const P = {
  patients: ['patients.view'],
  enrollments: ['enrollments.view'],
  training: ['classes.view', 'training_records.view'],
  therapy: ['therapy_sessions.view', 'appointments.view'],
  appointments: ['appointments.view'],
  assessments: ['assessments.view'],
  packages: ['packages.view'],
  billing: ['invoices.view', 'payments.view'],
  accounts: ['accounts.view'],
  payroll: ['accounts.payroll.manage', 'accounts.payroll.approve'],
  branches: ['branches.view', 'branches.manage'],
  reports: ['reports.view'],
  financial: ['reports.financial'],
  cms: ['cms.manage'],
  notify: ['notifications.send'],
  users: ['users.view', 'users.manage'],
  logs: ['audit_logs.view'],
  settings: ['settings.manage'],
}

/** Admin sidebar — Plan §২ Sitemap (full menu structure). Items with `sprint` open a "coming in Sprint N" page. */
export const adminNav: NavSection[] = [
  { label: 'Dashboard', icon: LayoutDashboard, to: '/app' },
  {
    label: 'Appointments',
    icon: CalendarCheck,
    items: [
      { label: 'All Appointments', to: '/app/appointments/all', permissions: P.appointments, sprint: 16 },
      { label: "Today's Appointments", to: '/app/appointments', permissions: P.appointments },
      { label: 'Calendar', to: '/app/appointments/calendar', permissions: P.appointments, sprint: 16 },
      { label: 'Appointment Requests', to: '/app/online-requests', permissions: ['appointment_requests.manage'] },
      { label: 'Check-in / Queue', to: '/app/appointments/queue', permissions: P.appointments, sprint: 16 },
    ],
  },
  {
    label: 'Patients',
    icon: UserRound,
    items: [
      { label: 'All Patients', to: '/app/patients', permissions: P.patients },
      { label: 'New Patient', to: '/app/patients/new', permissions: ['patients.create'] },
      { label: 'Patient Search', to: '/app/patients?focus=search', permissions: P.patients },
      { label: 'Guardians', to: '/app/guardians', permissions: P.patients, sprint: 16 },
      { label: 'Documents', to: '/app/documents', permissions: P.patients, sprint: 16 },
      { label: 'Consents', to: '/app/consents', permissions: P.patients, sprint: 16 },
      { label: 'Patient Timeline', to: '/app/timeline', permissions: P.patients, sprint: 16 },
    ],
  },
  {
    label: 'Training',
    icon: GraduationCap,
    items: [
      { label: 'Training Dashboard', to: '/app/training', permissions: P.training, sprint: 16 },
      { label: 'Training Students', to: '/app/students', permissions: P.enrollments },
      { label: 'Training Groups / Classes', to: '/app/classes', permissions: ['classes.view'] },
      { label: 'Trainers', to: '/app/trainers', permissions: ['trainers.view'] },
      { label: 'Training Schedules', to: '/app/training/schedules', permissions: ['classes.view'], sprint: 16 },
      { label: 'Attendance', to: '/app/training/attendance', permissions: ['training_attendance.view'], sprint: 16 },
      { label: 'Training Sessions', to: '/app/training/sessions', permissions: ['training_records.view'], sprint: 16 },
      { label: 'Training Records', to: '/app/training-sessions', permissions: ['training_records.view'] },
      { label: 'Activities', to: '/app/training/activities', permissions: ['classes.view'], sprint: 16 },
      { label: 'ITP / Training Plans', to: '/app/plans?type=training', permissions: ['plans.view'], sprint: 16 },
    ],
  },
  {
    label: 'Therapy',
    icon: HeartPulse,
    items: [
      { label: 'Therapy Dashboard', to: '/app/therapy', permissions: P.therapy, sprint: 16 },
      { label: 'Therapy Patients', to: '/app/enrollments?type=therapy&status=active', permissions: P.enrollments, sprint: 16 },
      { label: 'Therapists', to: '/app/therapists', permissions: ['therapists.view'] },
      { label: 'Therapy Services', to: '/app/therapy/services', permissions: ['therapists.view'], sprint: 16 },
      { label: 'Therapist Schedule', to: '/app/therapy/schedule', permissions: ['therapists.view'], sprint: 16 },
      { label: 'Appointments', to: '/app/appointments', permissions: P.appointments },
      { label: 'Therapy Sessions', to: '/app/therapy-sessions', permissions: ['therapy_sessions.view'] },
      { label: 'Session Notes', to: '/app/therapy-sessions?status=draft', permissions: ['therapy_sessions.view'] },
      { label: 'Assessments', to: '/app/assessments', permissions: P.assessments },
      { label: 'Plans & Goals', to: '/app/plans?type=therapy', permissions: ['plans.view'], sprint: 16 },
      { label: 'Home Programs', to: '/app/therapy/home-programs', permissions: ['home_programs.view'], sprint: 16 },
      { label: 'Progress Reports', to: '/app/therapy/progress-reports', permissions: ['plans.view'], sprint: 16 },
    ],
  },
  {
    label: 'Assessments',
    icon: ClipboardList,
    items: [
      { label: 'All Assessments', to: '/app/assessments', permissions: P.assessments },
      { label: 'New Assessment', to: '/app/assessments/new', permissions: ['assessments.write'], sprint: 16 },
      { label: 'Assessment Types', to: '/app/assessments/types', permissions: P.assessments, sprint: 16 },
      { label: 'Assessment Reports', to: '/app/assessments?status=final', permissions: P.assessments },
      { label: 'Recommendations', to: '/app/assessments/recommendations', permissions: P.assessments, sprint: 16 },
      { label: 'Assessment Templates', to: '/app/assessments/templates', permissions: P.assessments, sprint: 16 },
    ],
  },
  {
    label: 'Enrollments',
    icon: ListChecks,
    items: [
      { label: 'All Enrollments', to: '/app/enrollments', permissions: P.enrollments, sprint: 16 },
      { label: 'Training Enrollments', to: '/app/enrollments?type=training', permissions: P.enrollments, sprint: 16 },
      { label: 'Therapy Enrollments', to: '/app/enrollments?type=therapy', permissions: P.enrollments, sprint: 16 },
      { label: 'Active Enrollments', to: '/app/enrollments?status=active', permissions: P.enrollments, sprint: 16 },
      { label: 'On Hold', to: '/app/enrollments?status=on_hold', permissions: P.enrollments, sprint: 16 },
      { label: 'Completed', to: '/app/enrollments?status=completed', permissions: P.enrollments, sprint: 16 },
      { label: 'Discontinued', to: '/app/enrollments?status=discontinued', permissions: P.enrollments, sprint: 16 },
      { label: 'Transfer History', to: '/app/enrollments/transfers', permissions: P.enrollments, sprint: 16 },
    ],
  },
  {
    label: 'Packages',
    icon: Package,
    items: [
      { label: 'All Packages', to: '/app/packages', permissions: P.packages },
      { label: 'Create Package', to: '/app/packages?new=1', permissions: ['packages.manage'] },
      { label: 'Patient Packages', to: '/app/packages?tab=sold', permissions: P.packages },
      { label: 'Package Usage', to: '/app/packages/usage', permissions: P.packages, sprint: 16 },
      { label: 'Expiring Packages', to: '/app/packages?tab=sold&status=expiring', permissions: P.packages },
      { label: 'Package Reports', to: '/app/reports?r=service-utilization', permissions: P.reports },
    ],
  },
  {
    label: 'Billing & Payments',
    icon: Receipt,
    items: [
      { label: 'Billing Dashboard', to: '/app/billing', permissions: P.billing, sprint: 16 },
      { label: 'Invoices', to: '/app/invoices', permissions: ['invoices.view'] },
      { label: 'Payments', to: '/app/payments', permissions: ['payments.view'] },
      { label: 'Due / Outstanding', to: '/app/invoices?tab=dues', permissions: ['invoices.view'] },
      { label: 'Payment Allocations', to: '/app/billing/allocations', permissions: ['payments.view'], sprint: 16 },
      { label: 'Receipts', to: '/app/billing/receipts', permissions: ['payments.view'], sprint: 16 },
      { label: 'Discounts / Concessions', to: '/app/billing/discounts', permissions: ['invoices.view'], sprint: 16 },
      { label: 'Refunds', to: '/app/billing/refunds', permissions: ['payments.view'], sprint: 16 },
      { label: 'Billing Reports', to: '/app/reports?r=revenue', permissions: P.financial },
    ],
  },
  {
    label: 'Accounts',
    icon: Landmark,
    items: [
      { label: 'Accounts Dashboard', to: '/app/accounts', permissions: P.accounts },
      { label: 'Chart of Accounts', to: '/app/accounts/books?tab=chart', permissions: P.accounts },
      { label: 'Journal Entries', to: '/app/accounts/books?tab=journal', permissions: P.accounts },
      { label: 'Payment Vouchers', to: '/app/accounts/vouchers?type=payment', permissions: P.accounts },
      { label: 'Receipt Vouchers', to: '/app/accounts/vouchers?type=receipt', permissions: P.accounts },
      { label: 'Expense Vouchers', to: '/app/accounts/vouchers?type=payment&source=expense', permissions: P.accounts },
      { label: 'Expenses', to: '/app/expenses', permissions: ['accounts.expense.create'] },
      { label: 'Vendors', to: '/app/accounts/vendors', permissions: P.accounts },
      { label: 'Bills / Payables', to: '/app/accounts/bills', permissions: P.accounts, sprint: 16 },
      { label: 'Payroll', to: '/app/payroll', permissions: P.payroll },
      { label: 'Employee Advances', to: '/app/payroll/advances', permissions: P.payroll, sprint: 16 },
      { label: 'Cash Closing', to: '/app/cash-closing', permissions: ['accounts.cash_closing'] },
      { label: 'Bank Accounts', to: '/app/accounts/bank-accounts', permissions: P.accounts, sprint: 16 },
      { label: 'Bank Reconciliation', to: '/app/accounts/reconciliation', permissions: P.accounts },
      { label: 'Fixed Assets', to: '/app/accounts/assets', permissions: P.accounts },
      { label: 'Budgets', to: '/app/accounts/budgets', permissions: ['accounts.reports'] },
      { label: 'Fiscal Years', to: '/app/accounts/books?tab=periods', permissions: P.accounts },
      { label: 'Accounting Periods', to: '/app/accounts/books?tab=periods&view=months', permissions: P.accounts },
    ],
  },
  {
    label: 'Staff',
    icon: IdCard,
    items: [
      { label: 'All Staff', to: '/app/employees', permissions: P.payroll },
      { label: 'Trainers', to: '/app/trainers', permissions: ['trainers.view'] },
      { label: 'Therapists', to: '/app/therapists', permissions: ['therapists.view'] },
      { label: 'Admin Staff', to: '/app/employees?department=admin', permissions: P.payroll },
      { label: 'Employee Profiles', to: '/app/staff/profiles', permissions: P.payroll, sprint: 16 },
      { label: 'Salary Structures', to: '/app/payroll/structures', permissions: P.payroll, sprint: 16 },
      { label: 'Leave / Absence', to: '/app/staff/leave', permissions: ['therapists.view', ...P.payroll], sprint: 16 },
      { label: 'Staff Assignments', to: '/app/staff/assignments', permissions: P.enrollments, sprint: 16 },
      { label: 'My Payslips', to: '/app/my-payslips' },
    ],
  },
  {
    label: 'Branches',
    icon: Building2,
    items: [
      { label: 'All Branches', to: '/app/branches', permissions: P.branches },
      { label: 'Add Branch', to: '/app/branches?new=1', permissions: ['branches.manage'] },
      { label: 'Rooms', to: '/app/rooms', permissions: P.branches, sprint: 16 },
      { label: 'Services by Branch', to: '/app/branches/services', permissions: P.branches, sprint: 16 },
      { label: 'Staff by Branch', to: '/app/branches/staff', permissions: P.branches, sprint: 16 },
      { label: 'Holidays', to: '/app/holidays', permissions: ['branches.view'] },
      { label: 'Branch Settings', to: '/app/settings?tab=branch', permissions: P.settings, sprint: 16 },
    ],
  },
  {
    label: 'Reports & Analytics',
    icon: BarChart3,
    items: [
      { label: 'Dashboard Reports', to: '/app/reports?r=registrations', permissions: P.reports },
      { label: 'Patient Reports', to: '/app/reports?r=patients', permissions: P.reports, sprint: 16 },
      { label: 'Enrollment Reports', to: '/app/reports?r=enrollments', permissions: P.reports, sprint: 16 },
      { label: 'Attendance Reports', to: '/app/reports?r=class-attendance', permissions: P.reports },
      { label: 'Therapy Reports', to: '/app/reports?r=therapist-sessions', permissions: P.reports },
      { label: 'Training Reports', to: '/app/reports?r=trainer-records', permissions: P.reports },
      { label: 'Appointment Reports', to: '/app/reports?r=appointments', permissions: P.reports },
      { label: 'Assessment Reports', to: '/app/reports?r=assessments', permissions: P.reports, sprint: 16 },
      { label: 'Progress Reports', to: '/app/reports?r=goal-achievement', permissions: P.reports },
      { label: 'Billing Reports', to: '/app/reports?r=revenue', permissions: P.financial },
      { label: 'Payment / Due Reports', to: '/app/reports?r=due-aging', permissions: P.financial },
      { label: 'Accounts Reports', to: '/app/accounts/reports', permissions: ['accounts.reports'] },
      { label: 'Staff Reports', to: '/app/reports?r=staff', permissions: P.reports, sprint: 16 },
      { label: 'Branch Reports', to: '/app/reports?r=branch-performance', permissions: P.financial },
      { label: 'Export PDF / Excel', to: '/app/reports', permissions: P.reports },
    ],
  },
  {
    label: 'Website / CMS',
    icon: Globe,
    items: [
      { label: 'Website Dashboard', to: '/app/cms/dashboard', permissions: P.cms, sprint: 16 },
      { label: 'Pages', to: '/app/cms?tab=pages', permissions: P.cms, sprint: 16 },
      { label: 'Page Sections', to: '/app/cms?tab=sections', permissions: P.cms, sprint: 16 },
      { label: 'Services', to: '/app/cms?tab=services', permissions: P.cms },
      { label: 'Team Members', to: '/app/cms?tab=team', permissions: P.cms },
      { label: 'Branches', to: '/app/cms?tab=branches', permissions: P.cms, sprint: 16 },
      { label: 'Gallery', to: '/app/cms?tab=gallery', permissions: P.cms },
      { label: 'Testimonials', to: '/app/cms?tab=testimonials', permissions: P.cms },
      { label: 'FAQ', to: '/app/cms?tab=faqs', permissions: P.cms },
      { label: 'Notices & Updates', to: '/app/cms?tab=notices', permissions: P.cms },
      { label: 'Appointment Requests', to: '/app/online-requests', permissions: ['appointment_requests.manage'] },
      { label: 'Contact Messages', to: '/app/cms?tab=messages', permissions: ['appointment_requests.manage'] },
      { label: 'SEO Settings', to: '/app/cms?tab=seo', permissions: P.cms, sprint: 16 },
    ],
  },
  {
    label: 'Notifications',
    icon: Bell,
    items: [
      { label: 'Notification Center', to: '/app/notifications/center', sprint: 16 },
      { label: 'Announcements', to: '/app/notifications', permissions: P.notify },
      { label: 'Templates', to: '/app/notifications/templates', permissions: P.notify, sprint: 16 },
      { label: 'Notification Logs', to: '/app/notifications/logs', permissions: P.notify, sprint: 16 },
      { label: 'Notification Settings', to: '/app/notifications?tab=settings', permissions: P.notify },
    ],
  },
  {
    label: 'Users',
    icon: Users,
    items: [
      { label: 'All Users', to: '/app/users', permissions: P.users },
      { label: 'Add User', to: '/app/users?new=1', permissions: ['users.manage'] },
      { label: 'Roles', to: '/app/roles', permissions: ['users.view', 'roles.manage'] },
      { label: 'Permissions', to: '/app/roles?tab=permissions', permissions: ['users.view', 'roles.manage'] },
      { label: 'Branch Access', to: '/app/users/branch-access', permissions: P.users, sprint: 16 },
    ],
  },
  {
    label: 'Activity Logs',
    icon: History,
    items: [
      { label: 'System Activity', to: '/app/logs?type=system', permissions: P.logs, sprint: 16 },
      { label: 'Login History', to: '/app/logs?type=login', permissions: P.logs, sprint: 16 },
      { label: 'Patient Activity', to: '/app/logs?type=patient', permissions: P.logs, sprint: 16 },
      { label: 'Clinical Access Logs', to: '/app/logs?type=clinical', permissions: P.logs, sprint: 16 },
      { label: 'Audit Logs', to: '/app/logs', permissions: P.logs, sprint: 16 },
    ],
  },
  {
    label: 'Settings',
    icon: Settings,
    items: [
      { label: 'General Settings', to: '/app/settings?tab=general', permissions: P.settings, sprint: 16 },
      { label: 'Center Information', to: '/app/settings?tab=center', permissions: P.settings, sprint: 16 },
      { label: 'Branch Settings', to: '/app/settings?tab=branch', permissions: P.settings, sprint: 16 },
      { label: 'Patient ID Settings', to: '/app/settings?tab=patient-id', permissions: P.settings, sprint: 16 },
      { label: 'Appointment Settings', to: '/app/settings?tab=appointment', permissions: P.settings, sprint: 16 },
      { label: 'Billing Settings', to: '/app/packages?tab=settings', permissions: P.settings },
      { label: 'Accounts Settings', to: '/app/accounts/books?tab=periods', permissions: P.settings },
      { label: 'Notification Settings', to: '/app/notifications?tab=settings', permissions: P.notify },
      { label: 'Language', to: '/app/settings?tab=language', permissions: P.settings, sprint: 16 },
      { label: 'PDF Settings', to: '/app/settings?tab=pdf', permissions: P.settings, sprint: 16 },
      { label: 'Security', to: '/app/settings?tab=security', permissions: P.settings, sprint: 16 },
      { label: 'Backup', to: '/app/settings?tab=backup', permissions: P.settings, sprint: 16 },
      { label: 'System Settings', to: '/app/settings?tab=system', permissions: P.settings, sprint: 16 },
    ],
  },
]

/** Every link in the menu, with its section — used for search, routing placeholders and active matching. */
export const navLinks = adminNav.flatMap((s) => (s.items ?? [{ label: s.label, to: s.to! }]).map((item) => ({ ...item, section: s.label })))

/** Path part of a menu link. */
export const navPath = (to: string) => to.split('?')[0]

/**
 * Picks the menu link that best describes the current URL: same path with all its query values present,
 * most specific first; failing that, the deepest path the URL sits under (detail pages).
 */
export function activeNavLink(pathname: string, search: string) {
  const params = new URLSearchParams(search)
  let best: { to: string; score: number } | null = null
  for (const link of navLinks) {
    const [path, query = ''] = link.to.split('?')
    const wanted = [...new URLSearchParams(query)]
    let score = -1
    if (path === pathname && wanted.every(([k, v]) => params.get(k) === v)) score = 1000 + wanted.length
    else if (path !== '/app' && pathname.startsWith(path + '/') && !wanted.length) score = path.length
    if (score > (best?.score ?? -1)) best = { to: link.to, score }
  }

  return best?.to
}
