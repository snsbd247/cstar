import { LogOut, Menu, X } from 'lucide-react'
import { useState } from 'react'
import { NavLink, Outlet, useNavigate } from 'react-router'
import { Logo } from '../components/shared/Logo'
import { useAuth } from '../contexts/useAuth'
import { PatientQuickSearch } from '../features/patients/components/PatientQuickSearch'
import { cn } from '../utils/cn'
import { adminNav } from './adminNav'

export default function AdminLayout() {
  const [open, setOpen] = useState(false)
  const { can } = useAuth()

  return (
    <div className="min-h-screen lg:pl-64">
      {/* Mobile top bar */}
      <header className="sticky top-0 z-30 flex h-14 items-center justify-between border-b border-slate-200 bg-white px-4 lg:hidden">
        <Logo compact />
        <button onClick={() => setOpen(true)} className="rounded-md p-2 text-slate-600 hover:bg-slate-100" aria-label="Open menu">
          <Menu className="size-5" />
        </button>
      </header>

      {open && <div className="fixed inset-0 z-40 bg-slate-900/40 lg:hidden" onClick={() => setOpen(false)} />}

      <aside
        className={cn(
          'fixed inset-y-0 left-0 z-50 flex w-64 flex-col border-r border-slate-200 bg-white transition-transform lg:translate-x-0',
          open ? 'translate-x-0' : '-translate-x-full',
        )}
      >
        <div className="flex h-16 items-center justify-between px-4">
          <Logo />
          <button onClick={() => setOpen(false)} className="rounded-md p-1 text-slate-400 lg:hidden" aria-label="Close menu">
            <X className="size-5" />
          </button>
        </div>
        <SidebarNav onNavigate={() => setOpen(false)} />
        <UserBox />
      </aside>

      {can('patients.view') && (
        <div className="sticky top-14 z-20 border-b border-slate-200 bg-white/95 px-4 py-2 backdrop-blur sm:px-6 lg:top-0 lg:px-8">
          <PatientQuickSearch />
        </div>
      )}

      <main className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <Outlet />
      </main>
    </div>
  )
}

function SidebarNav({ onNavigate }: { onNavigate: () => void }) {
  const { canAny } = useAuth()

  return (
    <nav className="flex-1 space-y-5 overflow-y-auto px-3 py-2">
      {adminNav.map((group, i) => {
        const items = group.items.filter((item) => !item.permissions || canAny(...item.permissions))
        if (!items.length) return null
        return (
          <div key={group.title ?? i}>
            {group.title && <p className="mb-1 px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400">{group.title}</p>}
            <ul className="space-y-0.5">
              {items.map((item) => (
                <li key={item.to}>
                  <NavLink
                    to={item.to}
                    end={item.to === '/app'}
                    onClick={onNavigate}
                    className={({ isActive }) =>
                      cn(
                        'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition',
                        isActive ? 'bg-brand-50 text-brand-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900',
                      )
                    }
                  >
                    <item.icon className="size-4 shrink-0" />
                    <span className="flex-1 truncate">{item.label}</span>
                    {item.sprint && <span className="rounded bg-slate-100 px-1.5 text-[10px] text-slate-400">soon</span>}
                  </NavLink>
                </li>
              ))}
            </ul>
          </div>
        )
      })}
    </nav>
  )
}

function UserBox() {
  const { user, logout } = useAuth()
  const navigate = useNavigate()

  return (
    <div className="border-t border-slate-200 p-3">
      <div className="flex items-center gap-3 rounded-lg px-2 py-2">
        <span className="flex size-9 items-center justify-center rounded-full bg-sky-brand-100 text-sm font-semibold text-sky-brand-700">
          {user?.name.charAt(0)}
        </span>
        <div className="min-w-0 flex-1">
          <p className="truncate text-sm font-medium text-slate-900">{user?.name}</p>
          <p className="truncate text-xs text-slate-500">{user?.primary_role_label}</p>
        </div>
        <button
          onClick={async () => {
            await logout()
            navigate('/login', { replace: true })
          }}
          className="rounded-md p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
          aria-label="Sign out"
          title="Sign out"
        >
          <LogOut className="size-4" />
        </button>
      </div>
    </div>
  )
}
