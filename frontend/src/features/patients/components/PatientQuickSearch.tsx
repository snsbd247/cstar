import { Search } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router'
import { usePatients } from '../api'
import { PatientAvatar, PatientTypeBadge } from './badges'

/** Global "find a child" box (receptionist fast path: Search → Profile). */
export function PatientQuickSearch() {
  const navigate = useNavigate()
  const [term, setTerm] = useState('')
  const [debounced, setDebounced] = useState('')
  const [open, setOpen] = useState(false)
  const [active, setActive] = useState(0)
  const box = useRef<HTMLDivElement>(null)
  const { data, isFetching } = usePatients({ search: debounced || undefined })
  const results = debounced.length >= 2 ? (data?.data.slice(0, 6) ?? []) : []

  useEffect(() => {
    const t = setTimeout(() => setDebounced(term.trim()), 250)
    return () => clearTimeout(t)
  }, [term])

  useEffect(() => {
    const close = (e: MouseEvent) => !box.current?.contains(e.target as Node) && setOpen(false)
    document.addEventListener('mousedown', close)
    return () => document.removeEventListener('mousedown', close)
  }, [])

  const go = (id: number) => {
    setOpen(false)
    setTerm('')
    navigate(`/app/patients/${id}`)
  }

  return (
    <div ref={box} className="relative w-full max-w-md">
      <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
      <input
        value={term}
        onChange={(e) => {
          setTerm(e.target.value)
          setOpen(true)
          setActive(0)
        }}
        onFocus={() => setOpen(true)}
        onKeyDown={(e) => {
          if (e.key === 'ArrowDown') setActive((a) => Math.min(a + 1, results.length - 1))
          if (e.key === 'ArrowUp') setActive((a) => Math.max(a - 1, 0))
          if (e.key === 'Enter' && results[active]) go(results[active].id)
          if (e.key === 'Escape') setOpen(false)
        }}
        placeholder="Find a child — name, ID or mobile"
        aria-label="Find a child"
        className="w-full rounded-lg border border-slate-200 bg-slate-50 py-2 pl-9 pr-3 text-sm placeholder:text-slate-400 focus:border-brand-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-500/20"
      />
      {open && debounced.length >= 2 && (
        <div className="absolute inset-x-0 top-full z-40 mt-1 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg">
          {results.length === 0 ? (
            <p className="px-4 py-3 text-sm text-slate-500">{isFetching ? 'Searching…' : 'No child found.'}</p>
          ) : (
            <ul>
              {results.map((p, i) => (
                <li key={p.id}>
                  <button
                    onMouseEnter={() => setActive(i)}
                    onClick={() => go(p.id)}
                    className={`flex w-full items-center gap-3 px-3 py-2 text-left ${i === active ? 'bg-slate-50' : ''}`}
                  >
                    <PatientAvatar id={p.id} name={p.name} hasPhoto={p.has_photo} size="sm" />
                    <span className="min-w-0 flex-1">
                      <span className="block truncate text-sm font-medium text-slate-900">{p.name}</span>
                      <span className="block truncate text-xs text-slate-500">
                        {p.patient_code} · {p.age} · {p.phone}
                      </span>
                    </span>
                    <PatientTypeBadge type={p.type} />
                  </button>
                </li>
              ))}
            </ul>
          )}
        </div>
      )}
    </div>
  )
}
