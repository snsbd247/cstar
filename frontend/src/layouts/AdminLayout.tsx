import { ChevronRight, LogOut, Menu, Search, X } from 'lucide-react'
import { useState } from 'react'
import { Link, Outlet, useLocation, useNavigate, useSearchParams } from 'react-router'
import { Logo } from '../components/shared/Logo'
import { NotificationBell } from '../components/shared/NotificationBell'
import { useAuth } from '../contexts/useAuth'
import { PatientQuickSearch } from '../features/patients/components/PatientQuickSearch'
import { cn } from '../utils/cn'
import { activeNavLink, adminNav, navLinks, type NavItem } from './adminNav'
import { PageErrorBoundary } from '../components/ErrorBoundary'

const FILTER_PARAMS = ['status', 'type', 'department', 'source', 'view', 'focus']

export default function AdminLayout() {
  const [open, setOpen] = useState(false)
  const { can } = useAuth()
  const { pathname } = useLocation()
  const [params] = useSearchParams()
  // Menu links pre-set filters through the URL; pages read them once, so a new filter means a fresh page.
  const pageKey = [pathname, ...FILTER_PARAMS.map((k) => params.get(k) ?? '')].join('|')

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

      <div className="sticky top-14 z-20 flex items-center justify-between gap-3 border-b border-slate-200 bg-white/95 px-4 py-2 backdrop-blur sm:px-6 lg:top-0 lg:px-8">
        {can('patients.view') ? <PatientQuickSearch /> : <span />}
        <NotificationBell />
      </div>

      <main className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <PageErrorBoundary>
          <Outlet key={pageKey} />
        </PageErrorBoundary>
      </main>
    </div>
  )
}

function SidebarNav({ onNavigate }: { onNavigate: () => void }) {
  const { canAny } = useAuth()
  const location = useLocation()
  const [query, setQuery] = useState('')
  const [toggled, setToggled] = useState<Record<string, boolean>>(() => {
    try {
      return JSON.parse(localStorage.getItem('cstar.nav') ?? '{}')
    } catch {
      return {}
    }
  })
  const active = activeNavLink(location.pathname, location.search)
  const visible = (item: NavItem) => !item.permissions || canAny(...item.permissions)
  const sections = adminNav
    .map((s) => ({ ...s, items: s.items?.filter(visible) }))
    .filter((s) => !s.items || s.items.length > 0)

  const toggle = (label: string, open: boolean) => {
    const next = { ...toggled, [label]: !open }
    setToggled(next)
    try {
      localStorage.setItem('cstar.nav', JSON.stringify(next))
    } catch {
      // Remembering open sections is only a convenience.
    }
  }

  const q = query.trim().toLowerCase()
  const matches = q ? navLinks.filter((l) => l.to !== '/app' && visible(l) && `${l.section} ${l.label}`.toLowerCase().includes(q)) : []

  return (
    <nav className="flex min-h-0 flex-1 flex-col">
      <div className="px-3 pb-2">
        <div className="relative">
          <Search className="pointer-events-none absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-slate-400" />
          <input
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder="Find a page…"
            aria-label="Find a page in the menu"
            className="w-full rounded-lg border border-slate-200 bg-slate-50 py-1.5 pl-8 pr-2 text-sm placeholder:text-slate-400 focus:border-brand-300 focus:bg-white focus:outline-none"
          />
        </div>
      </div>

      <div className="flex-1 overflow-y-auto px-3 pb-3">
        {q ? (
          <ul className="space-y-0.5">
            {matches.map((l) => (
              <li key={l.section + l.to + l.label}>
                <Link
                  to={l.to}
                  onClick={() => (setQuery(''), onNavigate())}
                  className="block rounded-lg px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-50 hover:text-slate-900"
                >
                  <span className="font-medium">{l.label}</span>
                  <span className="block text-[11px] text-slate-400">{l.section}</span>
                </Link>
              </li>
            ))}
            {!matches.length && <li className="px-3 py-2 text-sm text-slate-400">No page matches “{query}”.</li>}
          </ul>
        ) : (
          <ul className="space-y-0.5">
            {sections.map((s) => {
              if (!s.items) {
                return (
                  <li key={s.label}>
                    <Link to={s.to!} onClick={onNavigate} className={linkClass(active === s.to, true)}>
                      <s.icon className="size-4 shrink-0" />
                      <span className="flex-1 truncate">{s.label}</span>
                    </Link>
                  </li>
                )
              }
              const holdsActive = s.items.some((i) => i.to === active)
              const open = toggled[s.label] ?? holdsActive
              return (
                <li key={s.label}>
                  <button
                    type="button"
                    onClick={() => toggle(s.label, open)}
                    aria-expanded={open}
                    className={cn(linkClass(false, true), 'w-full text-left', holdsActive && 'text-brand-700')}
                  >
                    <s.icon className="size-4 shrink-0" />
                    <span className="flex-1 truncate">{s.label}</span>
                    <ChevronRight className={cn('size-3.5 shrink-0 text-slate-400 transition-transform', open && 'rotate-90')} />
                  </button>
                  {open && (
                    <ul className="mb-1 ml-5 space-y-0.5 border-l border-slate-100 pl-2">
                      {s.items.map((item) => (
                        <li key={item.to + item.label}>
                          <Link to={item.to} onClick={onNavigate} className={linkClass(item.to === active, false)}>
                            <span className="flex-1 truncate">{item.label}</span>
                            {item.sprint && <span className="rounded bg-slate-100 px-1.5 text-[10px] text-slate-400">soon</span>}
                          </Link>
                        </li>
                      ))}
                    </ul>
                  )}
                </li>
              )
            })}
          </ul>
        )}
      </div>
    </nav>
  )
}

const linkClass = (isActive: boolean, top: boolean) =>
  cn(
    'flex items-center gap-3 rounded-lg px-3 text-sm transition',
    top ? 'py-2 font-medium' : 'py-1.5',
    isActive ? 'bg-brand-50 font-medium text-brand-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900',
  )

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
