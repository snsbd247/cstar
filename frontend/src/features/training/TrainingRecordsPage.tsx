import { useState } from 'react'
import { Link } from 'react-router'
import { Badge, Card, PageHeader } from '../../components/ui/Card'
import { Select } from '../../components/ui/Field'
import { Spinner } from '../../components/ui/Spinner'
import { useClasses, useTrainingRecords } from './api'

/** History of daily training records (therapy sessions are a separate module). */
export default function TrainingRecordsPage() {
  const [classId, setClassId] = useState<number | undefined>()
  const { data: classes } = useClasses()
  const { data, isLoading } = useTrainingRecords({ class_id: classId })

  return (
    <>
      <PageHeader title="Training sessions" description="Daily records written by trainers for each student." />
      <Card className="mb-4 p-3">
        <Select value={classId ?? ''} onChange={(e) => setClassId(Number(e.target.value) || undefined)} aria-label="Filter by class" className="sm:w-72">
          <option value="">All classes</option>
          {classes?.map((c) => (
            <option key={c.id} value={c.id}>
              {c.name}
            </option>
          ))}
        </Select>
      </Card>
      {isLoading ? (
        <Spinner className="text-brand-600" />
      ) : !data?.data.length ? (
        <Card className="p-5 text-sm text-slate-500">No training records yet.</Card>
      ) : (
        <div className="space-y-3">
          {data.data.map((r) => (
            <Card key={r.id} className="p-4">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                  <Link to={`/app/patients/${r.patient?.id}?tab=training`} className="font-medium text-slate-900 hover:text-brand-700">
                    {r.patient?.name}
                  </Link>
                  <p className="text-xs text-slate-500">
                    {r.date} · {r.class?.name} · {r.trainer?.name}
                  </p>
                </div>
                <div className="flex items-center gap-2">
                  {r.performance && <span className="text-amber-500">{'★'.repeat(r.performance)}</span>}
                  <Badge tone={r.status === 'final' ? 'green' : 'amber'}>{r.status}</Badge>
                </div>
              </div>
              {r.observation && <p className="mt-2 text-sm text-slate-700">{r.observation}</p>}
              {r.activities && r.activities.length > 0 && <p className="mt-1 text-xs text-slate-500">{r.activities.map((a) => a.name).join(' · ')}</p>}
            </Card>
          ))}
        </div>
      )}
    </>
  )
}
