import { Card } from '../../../components/ui/Card'
import { useAuth } from '../../../contexts/useAuth'
import { useTrainingRecords } from '../../training/api'
import { MonthAttendance } from '../../training/components/MonthAttendance'
import { PlanPanel } from '../../training/components/PlanPanel'
import type { PatientDetail } from '../types'

/** Regular-training view of a child: ITP, attendance and daily records. Shown only for students. */
export function TrainingTab({ patient }: { patient: PatientDetail }) {
  const { can } = useAuth()
  const enrollment =
    patient.enrollments.find((e) => e.type === 'training' && ['active', 'on_hold', 'pending'].includes(e.status)) ??
    patient.enrollments.find((e) => e.type === 'training')
  const { data: records } = useTrainingRecords({ patient_id: patient.id })

  if (!enrollment) return <Card className="p-5 text-sm text-slate-500">This child is not a regular student (no training enrollment).</Card>

  return (
    <div className="grid gap-5 lg:grid-cols-2">
      <div className="space-y-5">
        <PlanPanel enrollmentId={enrollment.id} canEdit={can('plans.write')} />
        <MonthAttendance enrollmentId={enrollment.id} />
      </div>
      <Card className="h-fit p-5">
        <h3 className="font-semibold text-slate-900">Training records</h3>
        <p className="text-sm text-slate-500">
          {enrollment.training?.class.name} · Trainer {enrollment.training?.trainer.name}
        </p>
        <ul className="mt-4 space-y-4">
          {records?.data.length === 0 && <li className="text-sm text-slate-500">No records yet.</li>}
          {records?.data.map((r) => (
            <li key={r.id} className="border-l-2 border-brand-200 pl-3">
              <p className="text-xs text-slate-500">
                {r.date} · {r.trainer?.name} · {r.status}
                {r.performance ? ` · ${'★'.repeat(r.performance)}` : ''}
              </p>
              {r.observation && <p className="text-sm text-slate-800">{r.observation}</p>}
              {r.progress && <p className="text-sm text-slate-600">Progress: {r.progress}</p>}
              {r.next_plan && <p className="text-sm text-slate-600">Next: {r.next_plan}</p>}
              {r.parent_note && <p className="mt-1 rounded bg-brand-50 px-2 py-1 text-xs text-brand-800">For parents: {r.parent_note}</p>}
              {r.activities && r.activities.length > 0 && <p className="text-xs text-slate-500">{r.activities.map((a) => a.name).join(' · ')}</p>}
            </li>
          ))}
        </ul>
      </Card>
    </div>
  )
}
