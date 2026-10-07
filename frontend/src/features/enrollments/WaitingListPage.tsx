import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus } from 'lucide-react'
import { useState } from 'react'
import { Link, useSearchParams } from 'react-router'
import { api, errorMessage, validationErrors } from '../../api/client'
import { Button } from '../../components/ui/Button'
import { Alert, Badge, Card, PageHeader } from '../../components/ui/Card'
import { Field, Select, Textarea } from '../../components/ui/Field'
import { Modal } from '../../components/ui/Modal'
import { Spinner } from '../../components/ui/Spinner'
import { UrlTabs } from '../../components/ui/Tabs'
import { useAuth } from '../../contexts/useAuth'
import { PatientPicker, type PickedPatient } from '../billing/components/PatientPicker'
import { useBranches } from '../branches/api'
import { useClasses } from '../training/api'

interface Entry {
  id: number
  type: 'training' | 'therapy'
  status: 'waiting' | 'offered' | 'enrolled' | 'removed'
  priority: 'normal' | 'high'
  preferred_time: string
  notes: string | null
  position: number | null
  waiting_days: number
  patient: { id: number; name: string; patient_code: string; phone: string }
  branch: string
  wants: string
  created_at: string
  by: string | null
  resolution: string | null
  offered_at: string | null
}

const timeLabel: Record<string, string> = { morning: 'Morning', afternoon: 'Afternoon', evening: 'Evening', any: 'Any time' }

/** Enrollments → Waiting List (Sprint 20): who waits for a full class or a busy therapist, first come first served. */
export default function WaitingListPage() {
  const { can } = useAuth()
  const qc = useQueryClient()
  const [params] = useSearchParams()
  const status = params.get('view') ?? 'waiting'
  const [type, setType] = useState('')
  const [adding, setAdding] = useState(false)
  const { data, isLoading } = useQuery({
    queryKey: ['waiting-list', status, type],
    queryFn: async () => (await api.get<{ data: Entry[] }>('/waiting-list', { params: { status, type: type || undefined } })).data.data,
  })
  const act = useMutation({
    mutationFn: ({ id, ...body }: { id: number; action?: string; priority?: string; reason?: string }) => api.put(`/waiting-list/${id}`, body),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['waiting-list'] }),
  })

  return (
    <>
      <PageHeader
        title="Waiting List"
        description="When a class seat or a therapist’s slot frees up, the front desk is told who is first. Enrolling a waiting child closes their entry."
        actions={
          can('enrollments.manage') && (
            <Button onClick={() => setAdding(true)}>
              <Plus className="size-4" /> Add to waiting list
            </Button>
          )
        }
      />
      <UrlTabs
        tabs={[
          ['waiting', 'Waiting'],
          ['offered', 'Place offered'],
          ['enrolled', 'Enrolled'],
          ['removed', 'Removed'],
        ]}
        param="view"
        fallback="waiting"
      />
      <Card className="mb-4 p-3">
        <Select value={type} onChange={(e) => setType(e.target.value)} className="sm:w-48" aria-label="Programme">
          <option value="">Training and therapy</option>
          <option value="training">Training</option>
          <option value="therapy">Therapy</option>
        </Select>
      </Card>
      {act.isError && <Alert>{errorMessage(act.error)}</Alert>}
      <Card className="overflow-hidden">
        {isLoading ? (
          <Spinner className="m-5 text-brand-600" />
        ) : !data?.length ? (
          <p className="p-5 text-sm text-slate-500">Nobody here.</p>
        ) : (
          <ul className="divide-y divide-slate-100">
            {data.map((e) => (
              <li key={e.id} className="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm">
                <div className="flex items-start gap-3">
                  {e.position && <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-slate-100 font-semibold text-slate-700">{e.position}</span>}
                  <div>
                    <p>
                      <Link to={`/app/patients/${e.patient.id}?tab=enrollments`} className="font-medium text-slate-900 hover:text-brand-700">
                        {e.patient.name}
                      </Link>{' '}
                      <span className="text-xs text-slate-500">
                        {e.patient.patient_code} · {e.patient.phone}
                      </span>{' '}
                      {e.priority === 'high' && <Badge tone="red">priority</Badge>}
                    </p>
                    <p className="text-slate-700">
                      {e.wants} <span className="text-xs text-slate-500">· {e.branch} · {timeLabel[e.preferred_time]}</span>
                    </p>
                    <p className="text-xs text-slate-500">
                      waiting {e.waiting_days} day(s){e.by && ` · added by ${e.by}`}
                      {e.notes && ` · ${e.notes}`}
                      {e.resolution && ` · ${e.resolution}`}
                    </p>
                  </div>
                </div>
                {can('enrollments.manage') && (
                  <span className="flex flex-wrap gap-1">
                    {e.status === 'waiting' && (
                      <Button variant="secondary" className="min-h-8 px-3 text-xs" onClick={() => act.mutate({ id: e.id, action: 'offer' })}>
                        Offer place
                      </Button>
                    )}
                    {['waiting', 'offered'].includes(e.status) && (
                      <>
                        <Link to={`/app/patients/${e.patient.id}?tab=enrollments`} className="inline-flex min-h-8 items-center rounded-lg bg-brand-600 px-3 text-xs font-medium text-white hover:bg-brand-700">
                          Enroll
                        </Link>
                        <Button variant="ghost" className="min-h-8 px-2 text-xs" onClick={() => act.mutate({ id: e.id, priority: e.priority === 'high' ? 'normal' : 'high' })}>
                          {e.priority === 'high' ? 'Normal priority' : 'High priority'}
                        </Button>
                        <Button
                          variant="ghost"
                          className="min-h-8 px-2 text-xs text-red-600"
                          onClick={() => {
                            const reason = prompt('Why remove from the waiting list?')
                            if (reason) act.mutate({ id: e.id, action: 'remove', reason })
                          }}
                        >
                          Remove
                        </Button>
                      </>
                    )}
                    {['offered', 'removed'].includes(e.status) && (
                      <Button variant="ghost" className="min-h-8 px-2 text-xs" onClick={() => act.mutate({ id: e.id, action: 'wait' })}>
                        Back to waiting
                      </Button>
                    )}
                  </span>
                )}
              </li>
            ))}
          </ul>
        )}
      </Card>
      {adding && <AddModal onClose={() => setAdding(false)} />}
    </>
  )
}

function AddModal({ onClose }: { onClose: () => void }) {
  const qc = useQueryClient()
  const { user } = useAuth()
  const { data: branches } = useBranches()
  const { data: classes } = useClasses()
  const { data: services } = useQuery({
    queryKey: ['service-catalog'],
    queryFn: async () => (await api.get<{ data: { id: number; name: string; category: string; is_active: boolean }[] }>('/service-catalog')).data.data,
  })
  const [patient, setPatient] = useState<PickedPatient | null>(null)
  const [v, setV] = useState({ branch_id: String(user?.branches?.[0]?.id ?? ''), type: 'training', service_id: '', training_group_id: '', preferred_time: 'any', priority: 'normal', notes: '' })
  const [errors, setErrors] = useState<Record<string, string>>({})
  const save = useMutation({
    mutationFn: () =>
      api.post('/waiting-list', {
        ...v, patient_id: patient?.id, branch_id: Number(v.branch_id),
        service_id: v.type === 'therapy' ? Number(v.service_id) || null : null,
        training_group_id: v.type === 'training' ? Number(v.training_group_id) || null : null,
      }),
    onSuccess: () => (qc.invalidateQueries({ queryKey: ['waiting-list'] }), onClose()),
    onError: (e) => setErrors(validationErrors(e)),
  })

  return (
    <Modal open title="Add to the waiting list" onClose={onClose}>
      {!patient ? (
        <PatientPicker onPick={(p) => (setPatient(p), setV((x) => ({ ...x, branch_id: String(p.home_branch_id ?? x.branch_id) })))} />
      ) : (
        <div className="space-y-3">
          {(errors.patient_id || (save.isError && !Object.keys(errors).length)) && <Alert>{errors.patient_id ?? errorMessage(save.error)}</Alert>}
          <p className="text-sm">
            <b>{patient.name}</b> <span className="text-slate-500">{patient.patient_code}</span>{' '}
            <button onClick={() => setPatient(null)} className="text-xs text-brand-700 hover:underline">
              change
            </button>
          </p>
          <div className="grid gap-3 sm:grid-cols-2">
            <Field label="Branch" htmlFor="wl_branch">
              <Select id="wl_branch" value={v.branch_id} onChange={(e) => setV({ ...v, branch_id: e.target.value })}>
                {branches?.map((b) => (
                  <option key={b.id} value={b.id}>
                    {b.name}
                  </option>
                ))}
              </Select>
            </Field>
            <Field label="Waiting for" htmlFor="wl_type">
              <Select id="wl_type" value={v.type} onChange={(e) => setV({ ...v, type: e.target.value })}>
                <option value="training">Regular training</option>
                <option value="therapy">Therapy</option>
              </Select>
            </Field>
            {v.type === 'training' ? (
              <Field label="Class" htmlFor="wl_class" hint="Leave as “any” if any class will do">
                <Select id="wl_class" value={v.training_group_id} onChange={(e) => setV({ ...v, training_group_id: e.target.value })}>
                  <option value="">Any class</option>
                  {classes?.map((c) => (
                    <option key={c.id} value={c.id}>
                      {c.name}
                    </option>
                  ))}
                </Select>
              </Field>
            ) : (
              <Field label="Service" htmlFor="wl_service" error={errors.service_id}>
                <Select id="wl_service" value={v.service_id} onChange={(e) => setV({ ...v, service_id: e.target.value })}>
                  <option value="">Choose…</option>
                  {services
                    ?.filter((s) => s.category === 'therapy' && s.is_active)
                    .map((s) => (
                      <option key={s.id} value={s.id}>
                        {s.name}
                      </option>
                    ))}
                </Select>
              </Field>
            )}
            <Field label="Preferred time" htmlFor="wl_time">
              <Select id="wl_time" value={v.preferred_time} onChange={(e) => setV({ ...v, preferred_time: e.target.value })}>
                {Object.entries(timeLabel).map(([k, l]) => (
                  <option key={k} value={k}>
                    {l}
                  </option>
                ))}
              </Select>
            </Field>
          </div>
          <label className="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" className="size-4 accent-brand-600" checked={v.priority === 'high'} onChange={(e) => setV({ ...v, priority: e.target.checked ? 'high' : 'normal' })} /> High priority
            (moves ahead of normal entries)
          </label>
          <Field label="Notes" htmlFor="wl_notes">
            <Textarea id="wl_notes" rows={2} value={v.notes} onChange={(e) => setV({ ...v, notes: e.target.value })} />
          </Field>
          <div className="flex justify-end gap-2">
            <Button variant="ghost" onClick={onClose}>
              Cancel
            </Button>
            <Button loading={save.isPending} onClick={() => save.mutate()}>
              Add
            </Button>
          </div>
        </div>
      )}
    </Modal>
  )
}
