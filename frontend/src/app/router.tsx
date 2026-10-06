import { APP_BASE } from '../api/client'
import { createBrowserRouter, type RouteObject } from 'react-router'
import ChangePasswordPage from '../features/auth/ChangePasswordPage'
import LoginPage from '../features/auth/LoginPage'
import BranchesPage from '../features/branches/BranchesPage'
import AdminDashboard from '../features/dashboard/AdminDashboard'
import PatientFormPage from '../features/patients/PatientFormPage'
import PatientProfilePage from '../features/patients/PatientProfilePage'
import PatientsPage from '../features/patients/PatientsPage'
import CmsPage from '../features/cms/CmsPage'
import ClassDetailPage from '../features/training/ClassDetailPage'
import ClassesPage from '../features/training/ClassesPage'
import HolidaysPage from '../features/training/HolidaysPage'
import StudentsPage from '../features/training/StudentsPage'
import TrainersPage from '../features/training/TrainersPage'
import TrainingRecordsPage from '../features/training/TrainingRecordsPage'
import AppointmentsPage from '../features/therapy/AppointmentsPage'
import TherapistsPage from '../features/therapy/TherapistsPage'
import TherapySessionsPage from '../features/therapy/TherapySessionsPage'
import AssessmentsPage, { AssessmentDetailPage } from '../features/assessments/AssessmentsPage'
import AccountsPage from '../features/accounts/AccountsPage'
import AccountsDashboard from '../features/accounts/AccountsDashboard'
import { BudgetsPage, FixedAssetsPage, ReconciliationDetailPage, ReconciliationsPage, VendorDetailPage, VendorsPage } from '../features/accounts/AccountsCPages'
import CashClosingPage from '../features/accounts/CashClosingPage'
import ExpensesPage from '../features/accounts/ExpensesPage'
import ReportsPage from '../features/accounts/ReportsPage'
import VouchersPage from '../features/accounts/VouchersPage'
import EmployeesPage, { EmployeeDetailPage } from '../features/payroll/EmployeesPage'
import PayrollPage, { MyPayslipsPage, PayrollRunPage } from '../features/payroll/PayrollPage'
import NotificationsPage from '../features/notifications/NotificationsPage'
import OperationalReportsPage from '../features/reports/ReportsPage'
import InvoiceDetailPage from '../features/billing/InvoiceDetailPage'
import InvoicesPage from '../features/billing/InvoicesPage'
import PackagesPage from '../features/billing/PackagesPage'
import PaymentsPage from '../features/billing/PaymentsPage'
import { TherapistAssessmentPage, TherapistAssessmentsPage, TherapistNewAssessmentPage } from '../features/therapist/TherapistAssessments'
import { TherapistPatientPage, TherapistPatientsPage, TherapistSchedulePage, TherapistSessionPage, TherapistSessionsPage } from '../features/therapist/TherapistPages'
import { TrainerAttendancePage, TrainerRecordsPage, TrainerStudentPage, TrainerStudentsPage } from '../features/trainer/TrainerPages'
import OnlineRequestsPage from '../features/requests/OnlineRequestsPage'
import { portalNav } from '../features/portal/nav'
import { PortalBilling, PortalHome, PortalProfile, PortalProgress, PortalSchedule } from '../features/portal/PortalApp'
import { therapistNav } from '../features/therapist/nav'
import { TherapistToday } from '../features/therapist/TherapistApp'
import { trainerNav } from '../features/trainer/nav'
import { TrainerToday } from '../features/trainer/TrainerApp'
import RolesPage from '../features/users/RolesPage'
import UsersPage from '../features/users/UsersPage'
import AdminLayout from '../layouts/AdminLayout'
import { navLinks, navPath } from '../layouts/adminNav'
import { MobileAppLayout } from '../layouts/MobileAppLayout'
import ComingSoon, { NotFound } from '../pages/ComingSoon'
import { HomeRedirect } from '../routes/HomeRedirect'
import { RequireAuth, RequirePermission } from '../routes/RequireAuth'

const adminPages: RouteObject[] = [
  { index: true, element: <AdminDashboard /> },
  { path: 'patients', element: <RequirePermission permission="patients.view"><PatientsPage /></RequirePermission> },
  { path: 'patients/new', element: <RequirePermission permission="patients.create"><PatientFormPage /></RequirePermission> },
  { path: 'patients/:id', element: <RequirePermission permission="patients.view"><PatientProfilePage /></RequirePermission> },
  { path: 'patients/:id/edit', element: <RequirePermission permission="patients.update"><PatientFormPage /></RequirePermission> },
  { path: 'students', element: <RequirePermission permission="enrollments.view"><StudentsPage /></RequirePermission> },
  { path: 'classes', element: <RequirePermission permission="classes.view"><ClassesPage /></RequirePermission> },
  { path: 'classes/:id', element: <RequirePermission permission="classes.view"><ClassDetailPage /></RequirePermission> },
  { path: 'trainers', element: <RequirePermission permission="trainers.view"><TrainersPage /></RequirePermission> },
  { path: 'training-sessions', element: <RequirePermission permission="training_records.view"><TrainingRecordsPage /></RequirePermission> },
  { path: 'therapists', element: <RequirePermission permission="therapists.view"><TherapistsPage /></RequirePermission> },
  { path: 'appointments', element: <RequirePermission permission="appointments.view"><AppointmentsPage /></RequirePermission> },
  { path: 'therapy-sessions', element: <RequirePermission permission="therapy_sessions.view"><TherapySessionsPage /></RequirePermission> },
  { path: 'assessments', element: <RequirePermission permission="assessments.view"><AssessmentsPage /></RequirePermission> },
  { path: 'assessments/:id', element: <RequirePermission permission="assessments.view"><AssessmentDetailPage /></RequirePermission> },
  { path: 'packages', element: <RequirePermission permission="packages.view"><PackagesPage /></RequirePermission> },
  { path: 'invoices', element: <RequirePermission permission="invoices.view"><InvoicesPage /></RequirePermission> },
  { path: 'invoices/:id', element: <RequirePermission permission="invoices.view"><InvoiceDetailPage /></RequirePermission> },
  { path: 'payments', element: <RequirePermission permission="payments.view"><PaymentsPage /></RequirePermission> },
  { path: 'accounts', element: <RequirePermission permission="accounts.view"><AccountsDashboard /></RequirePermission> },
  { path: 'accounts/books', element: <RequirePermission permission="accounts.view"><AccountsPage /></RequirePermission> },
  { path: 'accounts/vendors', element: <RequirePermission permission="accounts.view"><VendorsPage /></RequirePermission> },
  { path: 'accounts/vendors/:id', element: <RequirePermission permission="accounts.view"><VendorDetailPage /></RequirePermission> },
  { path: 'accounts/reconciliation', element: <RequirePermission permission="accounts.view"><ReconciliationsPage /></RequirePermission> },
  { path: 'accounts/reconciliation/:id', element: <RequirePermission permission="accounts.view"><ReconciliationDetailPage /></RequirePermission> },
  { path: 'accounts/assets', element: <RequirePermission permission="accounts.view"><FixedAssetsPage /></RequirePermission> },
  { path: 'accounts/budgets', element: <RequirePermission permission="accounts.reports"><BudgetsPage /></RequirePermission> },
  { path: 'employees', element: <RequirePermission permission={['accounts.payroll.manage', 'accounts.payroll.approve']}><EmployeesPage /></RequirePermission> },
  { path: 'employees/:id', element: <RequirePermission permission={['accounts.payroll.manage', 'accounts.payroll.approve']}><EmployeeDetailPage /></RequirePermission> },
  { path: 'payroll', element: <RequirePermission permission={['accounts.payroll.manage', 'accounts.payroll.approve']}><PayrollPage /></RequirePermission> },
  { path: 'payroll/:id', element: <RequirePermission permission={['accounts.payroll.manage', 'accounts.payroll.approve']}><PayrollRunPage /></RequirePermission> },
  { path: 'my-payslips', element: <MyPayslipsPage /> },
  { path: 'reports', element: <RequirePermission permission="reports.view"><OperationalReportsPage /></RequirePermission> },
  { path: 'notifications', element: <RequirePermission permission="notifications.send"><NotificationsPage /></RequirePermission> },
  { path: 'accounts/vouchers', element: <RequirePermission permission="accounts.view"><VouchersPage /></RequirePermission> },
  { path: 'accounts/reports', element: <RequirePermission permission="accounts.reports"><ReportsPage /></RequirePermission> },
  { path: 'expenses', element: <RequirePermission permission="accounts.expense.create"><ExpensesPage /></RequirePermission> },
  { path: 'cash-closing', element: <RequirePermission permission="accounts.cash_closing"><CashClosingPage /></RequirePermission> },
  { path: 'holidays', element: <RequirePermission permission="branches.view"><HolidaysPage /></RequirePermission> },
  { path: 'online-requests', element: <RequirePermission permission="appointment_requests.manage"><OnlineRequestsPage /></RequirePermission> },
  { path: 'cms', element: <RequirePermission permission={['cms.manage', 'appointment_requests.manage']}><CmsPage /></RequirePermission> },
  { path: 'branches', element: <RequirePermission permission={['branches.view', 'branches.manage']}><BranchesPage /></RequirePermission> },
  { path: 'users', element: <RequirePermission permission={['users.view', 'users.manage']}><UsersPage /></RequirePermission> },
  { path: 'roles', element: <RequirePermission permission={['users.view', 'roles.manage']}><RolesPage /></RequirePermission> },
]

// Menu entries whose page is not built yet get a placeholder page (one per path).
const builtPaths = new Set(adminPages.map((r) => r.path))
const plannedAdminPages: RouteObject[] = [
  ...new Map(
    navLinks
      .filter((item) => item.sprint && !builtPaths.has(navPath(item.to).replace('/app/', '')))
      .map((item) => [navPath(item.to), { path: navPath(item.to).replace('/app/', ''), element: <ComingSoon title={item.label} sprint={item.sprint} /> }] as const),
  ).values(),
]

export const router = createBrowserRouter([
  { path: '/', element: <HomeRedirect /> },
  { path: '/login', element: <LoginPage /> },
  {
    element: <RequireAuth roles={['super_admin', 'branch_admin', 'receptionist', 'trainer', 'therapist', 'accountant', 'parent']} />,
    children: [{ path: '/change-password', element: <ChangePasswordPage /> }],
  },
  {
    path: '/app',
    element: <RequireAuth roles={['super_admin', 'branch_admin', 'receptionist', 'accountant']} />,
    children: [
      {
        element: <AdminLayout />,
        children: [
          ...adminPages,
          ...plannedAdminPages,
        ],
      },
    ],
  },
  {
    path: '/trainer',
    element: <RequireAuth roles={['trainer']} />,
    children: [
      {
        element: <MobileAppLayout title="Trainer" nav={trainerNav} payslipsTo="/trainer/payslips" />,
        children: [
          { index: true, element: <TrainerToday /> },
          { path: 'attendance', element: <TrainerAttendancePage /> },
          { path: 'records', element: <TrainerRecordsPage /> },
          { path: 'students', element: <TrainerStudentsPage /> },
          { path: 'students/:enrollmentId', element: <TrainerStudentPage /> },
          { path: 'profile', element: <MyPayslipsPage /> },
          { path: 'payslips', element: <MyPayslipsPage /> },
        ],
      },
    ],
  },
  {
    path: '/therapist',
    element: <RequireAuth roles={['therapist']} />,
    children: [
      {
        element: <MobileAppLayout title="Therapist" nav={therapistNav} payslipsTo="/therapist/payslips" />,
        children: [
          { index: true, element: <TherapistToday /> },
          { path: 'schedule', element: <TherapistSchedulePage /> },
          { path: 'patients', element: <TherapistPatientsPage /> },
          { path: 'patients/:enrollmentId', element: <TherapistPatientPage /> },
          { path: 'sessions', element: <TherapistSessionsPage /> },
          { path: 'session/:appointmentId', element: <TherapistSessionPage /> },
          { path: 'assessments', element: <TherapistAssessmentsPage /> },
          { path: 'assessments/new', element: <TherapistNewAssessmentPage /> },
          { path: 'assessments/:id', element: <TherapistAssessmentPage /> },
          { path: 'payslips', element: <MyPayslipsPage /> },
        ],
      },
    ],
  },
  {
    path: '/portal',
    element: <RequireAuth roles={['parent']} />,
    children: [
      {
        element: <MobileAppLayout title="অভিভাবক পোর্টাল" nav={portalNav} bangla />,
        children: [
          { index: true, element: <PortalHome /> },
          { path: 'schedule', element: <PortalSchedule /> },
          { path: 'progress', element: <PortalProgress /> },
          { path: 'billing', element: <PortalBilling /> },
          { path: 'profile', element: <PortalProfile /> },
        ],
      },
    ],
  },
  { path: '*', element: <NotFound /> },
], { basename: APP_BASE || undefined })
