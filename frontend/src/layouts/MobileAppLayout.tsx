import { LogOut, ReceiptText, type LucideIcon } from 'lucide-react'
import { Link, NavLink, Outlet, useNavigate } from 'react-router'
import { Logo } from '../components/shared/Logo'
import { NotificationBell } from '../components/shared/NotificationBell'
import { useAuth } from '../contexts/useAuth'
import { cn } from '../utils/cn'
import { PageErrorBoundary } from '../components/ErrorBoundary'

export interface BottomNavItem {
  label: string
  to: string
  icon: LucideIcon
  /** Shown only to clinical supervisors (Sprint 22). */
  supervisorOnly?: boolean
}

/**
 * Shared shell for the task-focused, mobile-first apps (Trainer, Therapist, Parent):
 * slim top bar + bottom navigation on phones, the same nav as a top tab row on wider screens.
 */
export function MobileAppLayout({ title, nav, bangla, payslipsTo }: { title: string; nav: BottomNavItem[]; bangla?: boolean; payslipsTo?: string }) {
  const { user, logout } = useAuth()
  const navigate = useNavigate()
  nav = nav.filter((item) => !item.supervisorOnly || user?.is_clinical_supervisor)

  return (
    <div className={cn('min-h-screen pb-20 sm:pb-0', bangla && 'font-bn')}>
      <header className="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur">
        <div className="mx-auto flex h-14 max-w-5xl items-center justify-between px-4">
          <div className="flex items-center gap-3">
            <Logo compact />
            <div className="leading-tight">
              <p className="text-sm font-semibold text-slate-900">{title}</p>
              <p className="text-xs text-slate-500">{user?.name}</p>
            </div>
          </div>
          <div className="flex items-center gap-1">
            <NotificationBell bangla={bangla} />
            {payslipsTo && (
              <Link to={payslipsTo} className="rounded-md p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="My payslips" title="My payslips">
                <ReceiptText className="size-5" />
              </Link>
            )}
            <button
              onClick={async () => {
                await logout()
                navigate('/login', { replace: true })
              }}
              className="rounded-md p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
              aria-label="Sign out"
            >
              <LogOut className="size-5" />
            </button>
          </div>
        </div>
        <nav className="mx-auto hidden max-w-5xl gap-1 px-4 sm:flex">
          {nav.map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              end
              className={({ isActive }) =>
                cn('flex items-center gap-2 border-b-2 px-3 py-2.5 text-sm font-medium', isActive ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-800')
              }
            >
              <item.icon className="size-4" />
              {item.label}
            </NavLink>
          ))}
        </nav>
      </header>

      <main className="mx-auto max-w-5xl px-4 py-5">
        <PageErrorBoundary>
          <Outlet />
        </PageErrorBoundary>
      </main>

      <nav className="fixed inset-x-0 bottom-0 z-30 grid border-t border-slate-200 bg-white pb-[env(safe-area-inset-bottom)] sm:hidden" style={{ gridTemplateColumns: `repeat(${nav.length}, minmax(0, 1fr))` }}>
        {nav.map((item) => (
          <NavLink
            key={item.to}
            to={item.to}
            end
            className={({ isActive }) => cn('flex flex-col items-center gap-0.5 py-2 text-[11px] font-medium', isActive ? 'text-brand-700' : 'text-slate-500')}
          >
            <item.icon className="size-5" />
            {item.label}
          </NavLink>
        ))}
      </nav>
    </div>
  )
}
