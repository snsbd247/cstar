import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowDown, ArrowUp, CalendarPlus, Lock, Plus, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { Link, useSearchParams } from 'react-router'
import { api, errorMessage, validationErrors } from '../../api/client'
import { Button } from '../../components/ui/Button'
import { Alert, Badge, Card, PageHeader } from '../../components/ui/Card'
import { Field, Input } from '../../components/ui/Field'
import { Pager } from '../../components/ui/Pager'
import { Spinner } from '../../components/ui/Spinner'
import { UrlTabs } from '../../components/ui/Tabs'
import { useAuth } from '../../contexts/useAuth'
import { cn } from '../../utils/cn'
import { todayISO } from '../../utils/format'
import { useAppointments } from '../therapy/api'
import { BookAppointmentModal } from '../therapy/components/BookAppointmentModal'
import { priorityTone } from './api'

interface Section {
  key: string
  label: string
  used?: boolean
}

interface TypeRow {
  id: number
  name: string
  name_bn: string | null
  is_active: boolean
  sections: Section[]
  assessments: number
}

const useTypes = () => useQuery({ queryKey: ['assessment-types-admin'], queryFn: async () => (await api.get<{ data: TypeRow[] }>('/assessment-types')).data.data })

/**
 * Assessments → Assessment Types (list, on/off, names) and Assessment Templates (the findings sections each
 * report has). Only Super Admin edits; sections already holding findings can be renamed but not removed.
 */
export function AssessmentTypesPage({ templates }: { templates?: boolean }) {
  const { can } = useAuth()
  const { data, isLoading } = useTypes()
  const [editing, setEditing] = useState<TypeRow | 'new' | null>(null)
  const editable = can('settings.manage')

  return (
    <>
      <PageHeader
        title={templates ? 'Assessment Templates' : 'Assessment Types'}
        description={
          templates
            ? 'The findings sections therapists fill in for each kind of assessment, in report order.'
            : 'Kinds of assessment therapists can write. Turn a type off to stop new ones; old reports stay.'
        }
        actions={
          editable && (
            <Button onClick={() => setEditing('new')}>
              <Plus className="size-4" /> New type
            </Button>
          )
        }
      />
      {isLoading ? (
        <Spinner className="text-brand-600" />
      ) : (
        <div className={cn('grid gap-3', templates ? 'lg:grid-cols-2' : '')}>
          {data?.map((t) => (
            <Card key={t.id} className={cn('p-4', !t.is_active && 'opacity-60')}>
              <div className="flex flex-wrap items-start justify-between gap-2">
                <div>
                  <p className="font-semibold text-slate-900">
                    {t.name} {!t.is_active && <Badge>off</Badge>}
                  </p>
                  <p className="text-xs text-slate-500">
                    {t.name_bn} · {t.sections.length} sections · {t.assessments} assessment(s) written
                  </p>
                </div>
                {editable && (
                  <Button variant="secondary" className="min-h-8 px-3 py-1 text-xs" onClick={() => setEditing(t)}>
                    Edit
                  </Button>
                )}
              </div>
              {templates && (
                <ol className="mt-3 list-decimal space-y-0.5 pl-5 text-sm text-slate-700">
                  {t.sections.map((s) => (
                    <li key={s.key}>
                      {s.label} {s.used && <Lock className="inline size-3 text-slate-400" aria-label="Has findings in saved reports" />}
                    </li>
                  ))}
                </ol>
              )}
            </Card>
          ))}
        </div>
      )}
      {editing && <TypeEditor type={editing === 'new' ? undefined : editing} onClose={() => setEditing(null)} />}
    </>
  )
}

function TypeEditor({ type, onClose }: { type?: TypeRow; onClose: () => void }) {
  const qc = useQueryClient()
  const [name, setName] = useState(type?.name ?? '')
  const [nameBn, setNameBn] = useState(type?.name_bn ?? '')
  const [active, setActive] = useState(type?.is_active ?? true)
  const [sections, setSections] = useState<Section[]>(type?.sections ?? [{ key: '', label: '' }])
  const [errors, setErrors] = useState<Record<string, string>>({})
  const save = useMutation({
    mutationFn: () => {
      const body = { name, name_bn: nameBn || null, is_active: active, sections: sections.filter((s) => s.label.trim()).map(({ key, label }) => ({ key: key || null, label })) }
      return type ? api.put(`/assessment-types/${type.id}`, body) : api.post('/assessment-types', body)
    },
    onSuccess: () => (qc.invalidateQueries({ queryKey: ['assessment-types-admin'] }), onClose()),
    onError: (e) => setErrors(validationErrors(e)),
  })
  const move = (i: number, dir: -1 | 1) => {
    const next = [...sections]
    ;[next[i], next[i + dir]] = [next[i + dir], next[i]]
    setSections(next)
  }

  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/40 p-4" role="dialog" aria-modal="true" aria-label="Edit assessment type">
      <Card className="mt-10 w-full max-w-xl space-y-3 p-5">
        <h2 className="text-lg font-semibold text-slate-900">{type ? `Edit ${type.name}` : 'New assessment type'}</h2>
        {(errors.sections || (save.isError && !Object.keys(errors).length)) && <Alert>{errors.sections ?? errorMessage(save.error)}</Alert>}
        <div className="grid gap-3 sm:grid-cols-2">
          <Field label="Name" htmlFor="at_name" error={errors.name}>
            <Input id="at_name" value={name} onChange={(e) => setName(e.target.value)} />
          </Field>
          <Field label="Name in Bangla" htmlFor="at_bn">
            <Input id="at_bn" value={nameBn} onChange={(e) => setNameBn(e.target.value)} />
          </Field>
        </div>
        <label className="flex items-center gap-2 text-sm text-slate-700">
          <input type="checkbox" className="size-4 accent-brand-600" checked={active} onChange={(e) => setActive(e.target.checked)} /> Therapists can choose this type
        </label>
        <div>
          <p className="mb-1 text-sm font-medium text-slate-700">Findings sections (report order)</p>
          <ul className="space-y-1.5">
            {sections.map((s, i) => (
              <li key={i} className="flex items-center gap-1.5">
                <Input value={s.label} onChange={(e) => setSections(sections.map((x, j) => (j === i ? { ...x, label: e.target.value } : x)))} aria-label={`Section ${i + 1}`} placeholder="e.g. Receptive language" />
                <Button variant="ghost" className="min-h-8 px-2" disabled={i === 0} onClick={() => move(i, -1)} aria-label="Move up">
                  <ArrowUp className="size-4" />
                </Button>
                <Button variant="ghost" className="min-h-8 px-2" disabled={i === sections.length - 1} onClick={() => move(i, 1)} aria-label="Move down">
                  <ArrowDown className="size-4" />
                </Button>
                {s.used ? (
                  <span className="px-2" title="Saved reports have findings in this section — rename it instead of removing">
                    <Lock className="size-4 text-slate-400" />
                  </span>
                ) : (
                  <Button variant="ghost" className="min-h-8 px-2 text-red-600" onClick={() => setSections(sections.filter((_, j) => j !== i))} aria-label="Remove section">
                    <Trash2 className="size-4" />
                  </Button>
                )}
              </li>
            ))}
          </ul>
          <Button variant="secondary" className="mt-2 min-h-8 px-3 py-1 text-xs" onClick={() => setSections([...sections, { key: '', label: '' }])}>
            <Plus className="size-3.5" /> Add section
          </Button>
        </div>
        <div className="flex justify-end gap-2">
          <Button variant="ghost" onClick={onClose}>
            Cancel
          </Button>
          <Button loading={save.isPending} onClick={() => save.mutate()}>
            Save
          </Button>
        </div>
      </Card>
    </div>
  )
}

interface RecommendationRow {
  id: number
  programme: string
  frequency: string | null
  priority: string | null
  note: string | null
  enrolled: boolean
  assessment: { id: number; code: string; date: string; therapist: string | null }
  patient: { id: number; name: string; patient_code: string }
}

/** Assessments → Recommendations: programmes therapists recommended that the front desk has not enrolled yet. */
export function RecommendationsPage() {
  const [params] = useSearchParams()
  const status = params.get('view') === 'enrolled' ? 'enrolled' : 'pending'
  const [page, setPage] = useState(1)
  const { data, isLoading } = useQuery({
    queryKey: ['assessment-recommendations', status, page],
    queryFn: async () => (await api.get<{ data: RecommendationRow[]; meta: { current_page: number; last_page: number; total: number } }>('/assessment-recommendations', { params: { status, page } })).data,
  })

  return (
    <>
      <PageHeader title="Recommendations" description="From final assessments. Enroll from the child’s Assessments tab — the form opens already filled in." />
      <UrlTabs
        tabs={[
          ['pending', 'Waiting for enrollment'],
          ['enrolled', 'Enrolled'],
        ]}
        param="view"
        fallback="pending"
      />
      <Card className="overflow-hidden">
        {isLoading ? (
          <Spinner className="m-5 text-brand-600" />
        ) : !data?.data.length ? (
          <p className="p-5 text-sm text-slate-500">{status === 'pending' ? 'Every recommendation has been acted on.' : 'None yet.'}</p>
        ) : (
          <ul className="divide-y divide-slate-100">
            {data.data.map((r) => (
              <li key={r.id} className="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm">
                <div>
                  <p>
                    <span className="font-medium text-slate-900">{r.patient.name}</span> <span className="text-xs text-slate-500">{r.patient.patient_code}</span>
                  </p>
                  <p className="text-slate-700">
                    {r.programme}
                    {r.frequency && <span className="text-slate-500"> · {r.frequency}</span>} {r.priority && <Badge tone={priorityTone[r.priority as keyof typeof priorityTone] ?? 'gray'}>{r.priority}</Badge>}
                  </p>
                  <p className="text-xs text-slate-500">
                    <Link to={`/app/assessments/${r.assessment.id}`} className="hover:underline">
                      {r.assessment.code}
                    </Link>{' '}
                    · {r.assessment.date} · {r.assessment.therapist}
                    {r.note && ` · ${r.note}`}
                  </p>
                </div>
                {!r.enrolled && (
                  <Link to={`/app/patients/${r.patient.id}?tab=assessments`} className="rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700">
                    Enroll →
                  </Link>
                )}
              </li>
            ))}
          </ul>
        )}
        <Pager meta={data?.meta} onPage={setPage} />
      </Card>
    </>
  )
}

/**
 * Assessments → New Assessment. The assessing therapist writes the report in the Therapist app (only they may
 * sign it); the front desk books the assessment appointment here.
 */
export function NewAssessmentPage() {
  const { user, can } = useAuth()
  const [booking, setBooking] = useState(false)
  const { data: upcoming } = useAppointments({ type: 'assessment', from: todayISO(), per_page: 20 })
  const isTherapist = user?.roles?.includes('therapist')

  return (
    <>
      <PageHeader title="New Assessment" description="Every new child starts with an assessment. Book it, then the therapist writes the report after seeing the child." />
      <div className="grid gap-4 lg:grid-cols-2">
        <Card className="space-y-3 p-5">
          <h2 className="font-semibold text-slate-900">1. Book an assessment appointment</h2>
          <p className="text-sm text-slate-600">Pick the child, an assessment service, the therapist and a free time.</p>
          {can('appointments.manage') ? (
            <Button onClick={() => setBooking(true)}>
              <CalendarPlus className="size-4" /> Book assessment
            </Button>
          ) : (
            <p className="text-sm text-slate-500">You can’t book appointments — ask the front desk.</p>
          )}
        </Card>
        <Card className="space-y-3 p-5">
          <h2 className="font-semibold text-slate-900">2. The therapist writes the report</h2>
          <p className="text-sm text-slate-600">
            In the Therapist app: Assessments → New, or “Write assessment” on the appointment. It stays a draft until the therapist finalizes it; then recommendations reach the front desk.
          </p>
          {isTherapist && (
            <Link to="/therapist/assessments/new" className="inline-block text-sm font-medium text-brand-700 hover:underline">
              Open the Therapist app →
            </Link>
          )}
        </Card>
      </div>
      <Card className="mt-4 overflow-hidden">
        <h2 className="border-b border-slate-100 px-4 py-3 font-semibold text-slate-900">Upcoming assessment appointments</h2>
        <ul className="divide-y divide-slate-100">
          {upcoming?.map((a) => (
            <li key={a.id} className="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5 text-sm">
              <span>
                <b>{a.date}</b> {a.start_time} · {a.patient?.name} <span className="text-slate-500">· {a.service?.name}</span>
              </span>
              <span className="text-slate-500">{a.therapist?.name}</span>
            </li>
          ))}
          {upcoming?.length === 0 && <li className="px-4 py-3 text-sm text-slate-500">None booked.</li>}
        </ul>
      </Card>
      {booking && <BookAppointmentModal onClose={() => setBooking(false)} />}
    </>
  )
}
