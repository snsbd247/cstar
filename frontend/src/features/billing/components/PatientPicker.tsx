import { useEffect, useState } from 'react'
import { Input } from '../../../components/ui/Field'
import { usePatients } from '../../patients/api'

export interface PickedPatient {
  id: number
  name: string
  patient_code: string
  home_branch_id?: number | null
}

/** Search box that returns one child (for starting an invoice or payment outside the profile). */
export function PatientPicker({ onPick }: { onPick: (p: PickedPatient) => void }) {
  const [term, setTerm] = useState('')
  const [debounced, setDebounced] = useState('')
  const { data, isFetching } = usePatients({ search: debounced || undefined })

  useEffect(() => {
    const t = setTimeout(() => setDebounced(term.trim()), 250)
    return () => clearTimeout(t)
  }, [term])

  const results = debounced.length >= 2 ? (data?.data.slice(0, 8) ?? []) : []

  return (
    <div className="space-y-2">
      <Input autoFocus aria-label="Find a child" placeholder="Child name, ID or mobile…" value={term} onChange={(e) => setTerm(e.target.value)} />
      {debounced.length >= 2 && !isFetching && results.length === 0 && <p className="text-sm text-slate-500">No child found.</p>}
      <ul className="divide-y divide-slate-100">
        {results.map((p) => (
          <li key={p.id}>
            <button
              type="button"
              onClick={() => onPick({ id: p.id, name: p.name, patient_code: p.patient_code, home_branch_id: p.home_branch?.id })}
              className="flex w-full items-center justify-between px-2 py-2.5 text-left hover:bg-slate-50"
            >
              <span className="font-medium text-slate-900">{p.name}</span>
              <span className="text-xs text-slate-500">
                {p.patient_code} · {p.phone}
              </span>
            </button>
          </li>
        ))}
      </ul>
    </div>
  )
}
