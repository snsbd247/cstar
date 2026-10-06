import { Navigate, Outlet, useLocation } from 'react-router'
import { FullPageSpinner } from '../components/ui/Spinner'
import { useAuth } from '../contexts/useAuth'
import type { RoleName } from '../types'

/**
 * Guards one app area (/app, /trainer, /therapist, /portal).
 * A user whose role does not belong here is sent to their own home app.
 * Super Admin may open every area (useful for support and previews).
 */
export function RequireAuth({ roles }: { roles: RoleName[] }) {
  const { user, isLoading } = useAuth()
  const location = useLocation()

  if (isLoading) return <FullPageSpinner />
  if (!user) return <Navigate to="/login" replace state={{ from: location.pathname }} />
  if (user.must_change_password && location.pathname !== '/change-password') {
    return <Navigate to="/change-password" replace />
  }
  if (!user.is_super_admin && !user.roles.some((r) => roles.includes(r))) {
    return <Navigate to={user.home_path} replace />
  }

  return <Outlet />
}

/** Wraps a single page that needs a permission inside an area. */
export function RequirePermission({ permission, children }: { permission: string | string[]; children: React.ReactNode }) {
  const { canAny } = useAuth()
  const list = Array.isArray(permission) ? permission : [permission]

  if (!canAny(...list)) {
    return (
      <div className="rounded-xl border border-amber-200 bg-amber-50 p-6 text-amber-900">
        You do not have permission to view this page.
      </div>
    )
  }
  return <>{children}</>
}
