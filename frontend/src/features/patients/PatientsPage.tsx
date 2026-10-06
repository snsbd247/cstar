import { ChevronLeft, ChevronRight, Plus, Search } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router'
import { Button } from '../../components/ui/Button'
import { Card, PageHeader } from '../../components/ui/Card'
import { Input, Select } from '../../components/ui/Field'
import { Spinner } from '../../components/ui/Spinner'
import { useAuth } from '../../contexts/useAuth'
import { usePatients, type PatientFilters } from './api'
import { PatientAvatar, PatientTypeBadge } from './components/badges'

export default function PatientsPage() {
  const { can } = useAuth()
  const navigate = useNavigate()
  const [filters, setFilters] = useState<PatientFilters>({ page: 1 })
  const [search, setSearch] = useState('')
  const { data, isLoading, isFetching } = usePatients(filters)

  useEffect(() => {
    const t = setTimeout(() => setFilters((f) => (f.search === (search || undefined) ? f : { ...f, search: search || undefined, page: 1 })), 300)
    return () => clearTimeout(t)
  }, [search])

  return (
    <>
      <PageHeader
        title="Patients"
        description="Every child registered at C-STAR. Student / therapy status comes from their enrollments."
        actions={
          can('patients.create') && (
            <Button onClick={() => navigate('/app/patients/new')}>
              <Plus className="size-4" /> Register child
            </Button>
          )
        }
      />

      <Card className="mb-4 grid gap-3 p-3 sm:grid-cols-4">
        <div className="relative sm:col-span-2">
          <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
          <Input autoFocus value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Name, patient ID or mobile number" className="pl-9" aria-label="Search patients" />
        </div>
        <Select value={filters.type ?? ''} onChange={(e) => setFilters({ ...filters, type: e.target.value || undefined, page: 1 })} aria-label="Filter by type">
          <option value="">All children</option>
          <option value="student">Regular Students</option>
          <option value="therapy">Therapy-only Patients</option>
          <option value="both">Student + Therapy</option>
          <option value="none">No current enrollment</option>
        </Select>
        <Select value={filters.status ?? ''} onChange={(e) => setFilters({ ...filters, status: e.target.value || undefined, page: 1 })} aria-label="Filter by status">
          <option value="">Any status</option>
          <option value="active">Active</option>
          <option value="on_hold">On hold</option>
          <option value="discharged">Discharged</option>
          <option value="inactive">Inactive</option>
        </Select>
      </Card>

      <Card className="overflow-hidden">
        {isLoading ? (
          <div className="p-6">
            <Spinner className="text-brand-600" />
          </div>
        ) : !data?.data.length ? (
          <p className="p-6 text-sm text-slate-500">{filters.search ? 'No child matches this search.' : 'No children registered yet.'}</p>
        ) : (
          <ul className={`divide-y divide-slate-100 ${isFetching ? 'opacity-60' : ''}`}>
            {data.data.map((p) => (
              <li key={p.id}>
                <Link to={`/app/patients/${p.id}`} className="flex items-center gap-3 px-4 py-3 hover:bg-slate-50">
                  <PatientAvatar id={p.id} name={p.name} hasPhoto={p.has_photo} />
                  <div className="min-w-0 flex-1">
                    <p className="truncate font-medium text-slate-900">{p.name}</p>
                    <p className="truncate text-sm text-slate-500">
                      {p.patient_code} · {p.age}
                      {p.primary_guardian && ` · ${p.primary_guardian.name} ${p.primary_guardian.phone}`}
                    </p>
                  </div>
                  <div className="hidden shrink-0 sm:block">
                    <PatientTypeBadge type={p.type} />
                  </div>
                </Link>
              </li>
            ))}
          </ul>
        )}
        {data && data.meta.last_page > 1 && (
          <div className="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-sm text-slate-500">
            <span>
              {data.meta.from}–{data.meta.to} of {data.meta.total}
            </span>
            <div className="flex gap-1">
              <Button variant="secondary" disabled={data.meta.current_page <= 1} onClick={() => setFilters({ ...filters, page: data.meta.current_page - 1 })} aria-label="Previous page">
                <ChevronLeft className="size-4" />
              </Button>
              <Button variant="secondary" disabled={data.meta.current_page >= data.meta.last_page} onClick={() => setFilters({ ...filters, page: data.meta.current_page + 1 })} aria-label="Next page">
                <ChevronRight className="size-4" />
              </Button>
            </div>
          </div>
        )}
      </Card>
    </>
  )
}
