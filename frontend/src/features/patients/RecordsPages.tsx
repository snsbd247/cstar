import { useQuery } from '@tanstack/react-query'
import { Download, Search, X } from 'lucide-react'
import { useEffect, useState, type ReactNode } from 'react'
import { Link, useSearchParams } from 'react-router'
import { api, apiUrl } from '../../api/client'
import { Badge, Card, PageHeader } from '../../components/ui/Card'
import { Input, Select } from '../../components/ui/Field'
import { Modal } from '../../components/ui/Modal'
import { Pager } from '../../components/ui/Pager'
import { Spinner } from '../../components/ui/Spinner'
import { UrlTabs } from '../../components/ui/Tabs'
import { PatientPicker, type PickedPatient } from '../billing/components/PatientPicker'

type Meta = { current_page: number; last_page: number; total: number }
type Child = { id: number; name: string; patient_code: string }

function useDebounced(value: string, ms = 300) {
  const [debounced, setDebounced] = useState(value)
  useEffect(() => {
    const t = setTimeout(() => setDebounced(value.trim()), ms)
    return () => clearTimeout(t)
  }, [value, ms])

  return debounced
}

function SearchBox({ value, onChange, placeholder }: { value: string; onChange: (v: string) => void; placeholder: string }) {
  return (
    <div className="relative sm:w-80">
      <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
      <Input value={value} onChange={(e) => onChange(e.target.value)} placeholder={placeholder} className="pl-9" aria-label={placeholder} />
    </div>
  )
}

function ChildLink({ child, tab }: { child: Child; tab?: string }) {
  return (
    <Link to={`/app/patients/${child.id}${tab ? `?tab=${tab}` : ''}`} className="font-medium text-slate-900 hover:text-brand-700">
      {child.name} <span className="text-xs font-normal text-slate-500">{child.patient_code}</span>
    </Link>
  )
}

function ListCard({ loading, empty, children, meta, onPage }: { loading: boolean; empty: boolean; children: ReactNode; meta?: Meta; onPage: (p: number) => void }) {
  return (
    <Card className="overflow-hidden">
      {loading ? (
        <div className="p-6">
          <Spinner className="text-brand-600" />
        </div>
      ) : empty ? (
        <p className="p-6 text-sm text-slate-500">Nothing here yet.</p>
      ) : (
        children
      )}
      <Pager meta={meta} onPage={onPage} />
    </Card>
  )
}

interface GuardianRow {
  id: number
  name: string
  phone: string
  alt_phone: string | null
  email: string | null
  occupation: string | null
  has_portal_account: boolean
  children: (Child & { relationship: string; is_primary: boolean; can_access_portal: boolean })[]
}

/** Patients → Guardians: every parent/guardian of the children you can see, with their children and portal access. */
export function GuardiansPage() {
  const [search, setSearch] = useState('')
  const q = useDebounced(search)
  const [portal, setPortal] = useState('')
  const [page, setPage] = useState(1)
  const { data, isLoading } = useQuery({
    queryKey: ['records', 'guardians', q, portal, page],
    queryFn: async () => (await api.get<{ data: GuardianRow[]; meta: Meta }>('/records/guardians', { params: { q: q || undefined, portal: portal || undefined, page } })).data,
  })

  return (
    <>
      <PageHeader title="Guardians" description="Parents and guardians. Siblings share their guardians. Add or edit a guardian on the child’s Guardians tab." />
      <Card className="mb-4 flex flex-col gap-2 p-3 sm:flex-row">
        <SearchBox value={search} onChange={(v) => (setSearch(v), setPage(1))} placeholder="Name, mobile, child or patient ID" />
        <Select value={portal} onChange={(e) => (setPortal(e.target.value), setPage(1))} className="sm:w-56" aria-label="Portal account">
          <option value="">Any portal status</option>
          <option value="yes">Has a portal login</option>
          <option value="no">No portal login yet</option>
        </Select>
      </Card>
      <ListCard loading={isLoading} empty={!data?.data.length} meta={data?.meta} onPage={setPage}>
        <ul className="divide-y divide-slate-100">
          {data?.data.map((g) => (
            <li key={g.id} className="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-start sm:justify-between">
              <div>
                <p className="font-medium text-slate-900">
                  {g.name} {g.has_portal_account && <Badge tone="blue">portal</Badge>}
                </p>
                <p className="text-sm text-slate-600">
                  <a href={`tel:${g.phone}`} className="hover:underline">
                    {g.phone}
                  </a>
                  {g.alt_phone && ` · ${g.alt_phone}`}
                  {g.email && ` · ${g.email}`}
                </p>
                {g.occupation && <p className="text-xs text-slate-500">{g.occupation}</p>}
              </div>
              <ul className="space-y-0.5 text-sm sm:text-right">
                {g.children.map((c) => (
                  <li key={c.id}>
                    <ChildLink child={c} tab="guardians" />
                    <span className="text-xs text-slate-500">
                      {' '}
                      · {c.relationship}
                      {c.is_primary && ', primary'}
                    </span>
                  </li>
                ))}
              </ul>
            </li>
          ))}
        </ul>
      </ListCard>
    </>
  )
}

interface DocumentRow {
  id: number
  title: string
  category: string
  category_label: string
  original_name: string
  size: number
  visible_to_parent: boolean
  patient: Child
  uploaded_by: string | null
  created_at: string
}

/** Patients → Documents: uploaded files of all children. Clinical categories only for clinical roles; downloads are logged. */
export function DocumentsPage() {
  const [search, setSearch] = useState('')
  const q = useDebounced(search)
  const [category, setCategory] = useState('')
  const [page, setPage] = useState(1)
  const { data, isLoading } = useQuery({
    queryKey: ['records', 'documents', q, category, page],
    queryFn: async () =>
      (await api.get<{ data: DocumentRow[]; meta: Meta; categories: { value: string; label: string }[] }>('/records/documents', { params: { q: q || undefined, category: category || undefined, page } })).data,
  })

  return (
    <>
      <PageHeader title="Documents" description="Reports, prescriptions, forms and IDs uploaded for children. Files are private; every download is recorded." />
      <Card className="mb-4 flex flex-col gap-2 p-3 sm:flex-row">
        <SearchBox value={search} onChange={(v) => (setSearch(v), setPage(1))} placeholder="Title, child or patient ID" />
        <Select value={category} onChange={(e) => (setCategory(e.target.value), setPage(1))} className="sm:w-56" aria-label="Category">
          <option value="">All categories</option>
          {data?.categories.map((c) => (
            <option key={c.value} value={c.value}>
              {c.label}
            </option>
          ))}
        </Select>
      </Card>
      <ListCard loading={isLoading} empty={!data?.data.length} meta={data?.meta} onPage={setPage}>
        <ul className="divide-y divide-slate-100">
          {data?.data.map((d) => (
            <li key={d.id} className="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
              <div>
                <p className="font-medium text-slate-900">
                  {d.title} <Badge>{d.category_label}</Badge> {d.visible_to_parent && <Badge tone="blue">parent can see</Badge>}
                </p>
                <p className="text-xs text-slate-500">
                  <ChildLink child={d.patient} tab="documents" /> · {new Date(d.created_at).toLocaleDateString('en-GB', { dateStyle: 'medium' })}
                  {d.uploaded_by && ` · ${d.uploaded_by}`} · {(d.size / 1024).toFixed(0)} KB
                </p>
              </div>
              <a href={apiUrl(`/documents/${d.id}/download`)} className="inline-flex items-center gap-1 rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100">
                <Download className="size-4" /> Download
              </a>
            </li>
          ))}
        </ul>
      </ListCard>
    </>
  )
}

interface ConsentRow {
  id: number
  type: string
  type_label: string
  granted: boolean
  signed_on: string | null
  patient: Child
  guardian: string | null
}

/** Patients → Consents: what guardians agreed to, and which children still lack a treatment consent. */
export function ConsentsPage() {
  const [params] = useSearchParams()
  const missing = params.get('view') === 'missing'
  const [type, setType] = useState('')
  const [granted, setGranted] = useState('')
  const [page, setPage] = useState(1)
  const { data, isLoading } = useQuery({
    queryKey: ['records', 'consents', missing, type, granted, page],
    queryFn: async () =>
      (
        await api.get<{ data: (ConsentRow | { patient: Child; branch: string | null; registered: string | null })[]; meta: Meta; missing: number }>('/records/consents', {
          params: { view: missing ? 'missing' : undefined, type: type || undefined, granted: granted || undefined, page },
        })
      ).data,
  })

  return (
    <>
      <PageHeader title="Consents" description="Recorded at registration. To record a new consent, open the child’s profile." />
      <UrlTabs
        tabs={[
          ['all', 'All consents'],
          ['missing', `No treatment consent${data?.missing ? ` (${data.missing})` : ''}`],
        ]}
        param="view"
        fallback="all"
      />
      {!missing && (
        <Card className="mb-4 flex flex-col gap-2 p-3 sm:flex-row">
          <Select value={type} onChange={(e) => (setType(e.target.value), setPage(1))} className="sm:w-72" aria-label="Consent type">
            <option value="">All types</option>
            <option value="treatment">Treatment</option>
            <option value="photo_media">Photo / video on website & social media</option>
            <option value="data_sharing">Data sharing</option>
          </Select>
          <Select value={granted} onChange={(e) => (setGranted(e.target.value), setPage(1))} className="sm:w-48" aria-label="Answer">
            <option value="">Given or refused</option>
            <option value="1">Given</option>
            <option value="0">Refused</option>
          </Select>
        </Card>
      )}
      <ListCard loading={isLoading} empty={!data?.data.length} meta={data?.meta} onPage={setPage}>
        <ul className="divide-y divide-slate-100">
          {data?.data.map((row) =>
            'type' in row ? (
              <li key={row.id} className="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm">
                <div>
                  <ChildLink child={row.patient} />
                  <p className="text-xs text-slate-500">
                    {row.type_label}
                    {row.guardian && ` · by ${row.guardian}`}
                    {row.signed_on && ` · ${row.signed_on}`}
                  </p>
                </div>
                <Badge tone={row.granted ? 'green' : 'red'}>{row.granted ? 'Given' : 'Refused'}</Badge>
              </li>
            ) : (
              <li key={row.patient.id} className="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm">
                <ChildLink child={row.patient} />
                <span className="text-xs text-slate-500">
                  {row.branch} {row.registered && `· registered ${row.registered}`}
                </span>
              </li>
            ),
          )}
        </ul>
      </ListCard>
    </>
  )
}

interface TimelineRow {
  id: number
  event_type: string
  title: string
  description: string | null
  visibility: string
  actor: string | null
  occurred_at: string
  patient: Child | null
}

const eventGroups = [
  ['', 'Everything'],
  ['patient', 'Registrations'],
  ['enrollment', 'Enrollments & transfers'],
  ['appointment', 'Appointments'],
  ['therapy', 'Therapy sessions'],
  ['training', 'Training records'],
  ['assessment', 'Assessments'],
  ['plan', 'Plans'],
  ['invoice', 'Invoices'],
  ['payment', 'Payments'],
  ['package', 'Packages'],
  ['document', 'Documents'],
  ['guardian', 'Guardians'],
] as const

/** Patients → Patient Timeline: the story of every child, or one child, newest first. */
export function TimelinePage() {
  const [child, setChild] = useState<PickedPatient | null>(null)
  const [picking, setPicking] = useState(false)
  const [event, setEvent] = useState('')
  const [page, setPage] = useState(1)
  const { data, isLoading } = useQuery({
    queryKey: ['records', 'timeline', child?.id, event, page],
    queryFn: async () => (await api.get<{ data: TimelineRow[]; meta: Meta }>('/records/timeline', { params: { patient_id: child?.id, event: event || undefined, page } })).data,
  })

  return (
    <>
      <PageHeader title="Patient Timeline" description="Everything that happened to the children you can see — newest first. Pick a child for one child’s story." />
      <Card className="mb-4 flex flex-col gap-2 p-3 sm:flex-row">
        {child ? (
          <span className="flex items-center justify-between gap-2 rounded-lg border border-slate-300 px-3 py-2 text-sm sm:w-80">
            <span className="truncate">
              {child.name} <span className="text-slate-400">· {child.patient_code}</span>
            </span>
            <button onClick={() => (setChild(null), setPage(1))} aria-label="All children" className="text-slate-400 hover:text-slate-700">
              <X className="size-4" />
            </button>
          </span>
        ) : (
          <button onClick={() => setPicking(true)} className="rounded-lg border border-dashed border-slate-300 px-3 py-2 text-left text-sm text-slate-500 hover:bg-slate-50 sm:w-80">
            All children — pick one…
          </button>
        )}
        <Select value={event} onChange={(e) => (setEvent(e.target.value), setPage(1))} className="sm:w-60" aria-label="Kind of event">
          {eventGroups.map(([k, l]) => (
            <option key={k} value={k}>
              {l}
            </option>
          ))}
        </Select>
      </Card>
      <ListCard loading={isLoading} empty={!data?.data.length} meta={data?.meta} onPage={setPage}>
        <ol className="relative ml-6 border-l border-slate-200 py-2">
          {data?.data.map((e) => (
            <li key={e.id} className="relative py-2.5 pl-5 pr-4">
              <span className="absolute -left-[5px] top-4 size-2.5 rounded-full bg-brand-500 ring-4 ring-white" />
              <div className="flex flex-wrap items-baseline justify-between gap-2">
                <p className="text-sm font-medium text-slate-900">{e.title}</p>
                <span className="text-xs text-slate-500">{new Date(e.occurred_at).toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short' })}</span>
              </div>
              {e.description && <p className="text-sm text-slate-600">{e.description}</p>}
              <p className="text-xs text-slate-500">
                {!child && e.patient && (
                  <>
                    <ChildLink child={e.patient} tab="timeline" /> ·{' '}
                  </>
                )}
                {e.actor ?? 'System'}
                {e.visibility === 'parent' && ' · parents see this'}
              </p>
            </li>
          ))}
        </ol>
      </ListCard>
      {picking && (
        <Modal open title="Timeline of one child" onClose={() => setPicking(false)}>
          <PatientPicker onPick={(p) => (setChild(p), setPage(1), setPicking(false))} />
        </Modal>
      )}
    </>
  )
}
