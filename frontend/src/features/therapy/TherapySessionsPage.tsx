import { useState } from 'react'
import { Link, useSearchParams } from 'react-router'
import { Badge, Card, PageHeader } from '../../components/ui/Card'
import { Select } from '../../components/ui/Field'
import { Spinner } from '../../components/ui/Spinner'
import { useTherapists, useTherapySessions } from './api'

/** History of therapy session notes (training records are a separate module). */
export default function TherapySessionsPage() {
  const [params] = useSearchParams()
  const [therapistId, setTherapistId] = useState<number | undefined>()
  const [status, setStatus] = useState(params.get('status') ?? '')
  const { data: therapists } = useTherapists()
  const { data, isLoading } = useTherapySessions({ therapist_id: therapistId, status: status || undefined })

  return (
    <>
      <PageHeader title="Therapy sessions" description="Clinical notes written by therapists after each appointment." />
      <Card className="mb-4 flex flex-col gap-3 p-3 sm:flex-row">
        <Select value={status} onChange={(e) => setStatus(e.target.value)} className="sm:w-56" aria-label="Note status">
          <option value="">All notes</option>
          <option value="draft">Draft — not finalized</option>
          <option value="final">Finalized</option>
        </Select>
        <Select value={therapistId ?? ''} onChange={(e) => setTherapistId(Number(e.target.value) || undefined)} className="sm:w-72" aria-label="Therapist">
          <option value="">All therapists</option>
          {therapists?.map((t) => (
            <option key={t.id} value={t.id}>
              {t.name}
            </option>
          ))}
        </Select>
      </Card>
      {isLoading ? (
        <Spinner className="text-brand-600" />
      ) : !data?.data.length ? (
        <Card className="p-5 text-sm text-slate-500">No session notes yet.</Card>
      ) : (
        <div className="space-y-3">
          {data.data.map((s) => (
            <Card key={s.id} className="p-4">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                  <Link to={`/app/patients/${s.patient?.id}?tab=therapy`} className="font-medium text-slate-900 hover:text-brand-700">
                    {s.patient?.name}
                  </Link>
                  <p className="text-xs text-slate-500">
                    {s.date} · {s.service?.name} · {s.therapist?.name}
                    {s.duration_min && ` · ${s.duration_min} min`}
                  </p>
                </div>
                <Badge tone={s.status === 'final' ? 'green' : 'amber'}>{s.status}</Badge>
              </div>
              {s.observation && <p className="mt-2 text-sm text-slate-700">{s.observation}</p>}
              {s.parent_summary && <p className="mt-1 rounded bg-brand-50 px-2 py-1 text-xs text-brand-800">For parents: {s.parent_summary}</p>}
            </Card>
          ))}
        </div>
      )}
    </>
  )
}
