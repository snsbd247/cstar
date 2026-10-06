import { createBrowserRouter } from 'react-router'
import ChangePasswordPage from '../features/auth/ChangePasswordPage'
import LoginPage from '../features/auth/LoginPage'
import BranchesPage from '../features/branches/BranchesPage'
import AdminDashboard from '../features/dashboard/AdminDashboard'
import PatientFormPage from '../features/patients/PatientFormPage'
import PatientProfilePage from '../features/patients/PatientProfilePage'
import PatientsPage from '../features/patients/PatientsPage'
import { portalNav } from '../features/portal/nav'
import { PortalHome } from '../features/portal/PortalApp'
import { therapistNav } from '../features/therapist/nav'
import { TherapistToday } from '../features/therapist/TherapistApp'
import { trainerNav } from '../features/trainer/nav'
import { TrainerToday } from '../features/trainer/TrainerApp'
import RolesPage from '../features/users/RolesPage'
import UsersPage from '../features/users/UsersPage'
import AdminLayout from '../layouts/AdminLayout'
import { adminNav } from '../layouts/adminNav'
import { MobileAppLayout, type BottomNavItem } from '../layouts/MobileAppLayout'
import ComingSoon, { NotFound } from '../pages/ComingSoon'
import { HomeRedirect } from '../routes/HomeRedirect'
import { RequireAuth, RequirePermission } from '../routes/RequireAuth'

// Sidebar entries whose module is not built yet get a placeholder page.
const plannedAdminPages = adminNav
  .flatMap((g) => g.items)
  .filter((item) => item.sprint)
  .map((item) => ({ path: item.to.replace('/app/', ''), element: <ComingSoon title={item.label} sprint={item.sprint} /> }))

const plannedMobilePages = (nav: BottomNavItem[], base: string, bangla = false) =>
  nav.filter((item) => item.to !== base).map((item) => ({ path: item.to.replace(`${base}/`, ''), element: <ComingSoon title={item.label} bangla={bangla} /> }))

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
          { index: true, element: <AdminDashboard /> },
          { path: 'patients', element: <RequirePermission permission="patients.view"><PatientsPage /></RequirePermission> },
          { path: 'patients/new', element: <RequirePermission permission="patients.create"><PatientFormPage /></RequirePermission> },
          { path: 'patients/:id', element: <RequirePermission permission="patients.view"><PatientProfilePage /></RequirePermission> },
          { path: 'patients/:id/edit', element: <RequirePermission permission="patients.update"><PatientFormPage /></RequirePermission> },
          { path: 'branches', element: <RequirePermission permission={['branches.view', 'branches.manage']}><BranchesPage /></RequirePermission> },
          { path: 'users', element: <RequirePermission permission={['users.view', 'users.manage']}><UsersPage /></RequirePermission> },
          { path: 'roles', element: <RequirePermission permission={['users.view', 'roles.manage']}><RolesPage /></RequirePermission> },
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
        element: <MobileAppLayout title="Trainer" nav={trainerNav} />,
        children: [{ index: true, element: <TrainerToday /> }, ...plannedMobilePages(trainerNav, '/trainer')],
      },
    ],
  },
  {
    path: '/therapist',
    element: <RequireAuth roles={['therapist']} />,
    children: [
      {
        element: <MobileAppLayout title="Therapist" nav={therapistNav} />,
        children: [{ index: true, element: <TherapistToday /> }, ...plannedMobilePages(therapistNav, '/therapist')],
      },
    ],
  },
  {
    path: '/portal',
    element: <RequireAuth roles={['parent']} />,
    children: [
      {
        element: <MobileAppLayout title="অভিভাবক পোর্টাল" nav={portalNav} bangla />,
        children: [{ index: true, element: <PortalHome /> }, ...plannedMobilePages(portalNav, '/portal', true)],
      },
    ],
  },
  { path: '*', element: <NotFound /> },
])
