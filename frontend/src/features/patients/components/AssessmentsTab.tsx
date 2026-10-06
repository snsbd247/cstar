import { FileDown, GraduationCap } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router'
import { Button } from '../../../components/ui/Button'
import { Badge, Card } from '../../../components/ui/Card'
import { Input } from '../../../components/ui/Field'
import { Spinner } from '../../../components/ui/Spinner'
import { useAuth } from '../../../contexts/useAuth'
import { priorityTone, progressReportUrl, useAssessments, usePatientRecommendations, type PatientRecommendation } from '../../assessments/api'
import type { PatientDetail } from '../types'

/**
 * Journey step Assessment → Enrollment (Plan §৩): reception sees what the therapist recommended
 * (no clinical findings) and enrolls in one click. Clinical staff also see the assessments themselves.
 */
export function AssessmentsTab({ patient, onEnroll }: { patient: PatientDetail; onEnroll: (r: PatientRecommendation) => void }) {
  const { can } = useAuth()
  const canViewClinical = can('assessments.view')
  const { data: recs, isLoading } = usePatientRecommendations(patient.id, can('enrollments.view'))
  const { data: assessments } = useAssessments({ patient_id: patient.id }, canViewClinical)
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')

  return (
    <div className="space-y-5">
      <Card className="p-5">
        <h3 className="font-semibold text-slate-900">Recommendations</h3>
        <p className="text-sm text-slate-500">From final assessments. Enroll to start the programme.</p>
        {isLoading ? (
          <Spinner className="mt-3 text-brand-600" />
        ) : !recs?.length ? (
          <p className="mt-3 text-sm text-slate-500">No recommendations yet.</p>
        ) : (
          <ul className="mt-3 divide-y divide-slate-100">
            {recs.map((r) => (
              <li key={r.id} className="flex flex-wrap items-center justify-between gap-3 py-3">
                <div>
                  <p className="flex flex-wrap items-center gap-2 font-medium text-slate-900">
                    {r.enrollment_type === 'training' ? 'Regular Training' : r.service?.name}
                    <Badge tone={priorityTone[r.priority]}>{r.priority}</Badge>
                  </p>
                  <p className="text-xs text-slate-500">
                    {[r.frequency, r.note].filter(Boolean).join(' · ')}
                    {(r.frequency || r.note) && ' — '}
                    {r.assessment.type}, {r.assessment.date}, {r.assessment.therapist.name}
                  </p>
                </div>
                {r.enrollment ? (
                  <Badge tone="green">Enrolled {r.enrollment.enrollment_code}</Badge>
                ) : (
                  patient.can.enroll && (
                    <Button onClick={() => onEnroll(r)}>
                      <GraduationCap className="size-4" /> Enroll
                    </Button>
                  )
                )}
              </li>
            ))}
          </ul>
        )}
      </Card>

      {canViewClinical && (
        <Card className="p-5">
          <h3 className="font-semibold text-slate-900">Assessments</h3>
          {!assessments?.data.length ? (
            <p className="mt-3 text-sm text-slate-500">No assessments yet. Book an assessment appointment — the therapist writes it in their app.</p>
          ) : (
            <ul className="mt-3 divide-y divide-slate-100">
              {assessments.data.map((a) => (
                <li key={a.id}>
                  <Link to={`/app/assessments/${a.id}`} className="flex flex-wrap items-center justify-between gap-2 py-3 hover:bg-slate-50">
                    <div>
                      <p className="font-medium text-slate-900">{a.type?.name}</p>
                      <p className="text-xs text-slate-500">
                        {a.date} · {a.therapist?.name} · {a.assessment_code}
                      </p>
                      {a.summary && <p className="mt-1 line-clamp-2 text-sm text-slate-600">{a.summary}</p>}
                    </div>
                    <Badge tone={a.status === 'final' ? 'green' : 'amber'}>{a.status}</Badge>
                  </Link>
                </li>
              ))}
            </ul>
          )}
        </Card>
      )}

      {can('plans.view') && (
        <Card className="p-5">
          <h3 className="font-semibold text-slate-900">Progress report (PDF)</h3>
          <p className="text-sm text-slate-500">Programmes, plan goals and sessions for a period — default last 3 months.</p>
          <div className="mt-3 flex flex-col gap-2 sm:flex-row sm:items-end">
            <label className="text-xs text-slate-500">
              From
              <Input type="date" value={from} onChange={(e) => setFrom(e.target.value)} />
            </label>
            <label className="text-xs text-slate-500">
              To
              <Input type="date" value={to} onChange={(e) => setTo(e.target.value)} />
            </label>
            <a href={progressReportUrl(patient.id, from, to)} target="_blank" rel="noreferrer">
              <Button variant="secondary">
                <FileDown className="size-4" /> Download
              </Button>
            </a>
          </div>
        </Card>
      )}
    </div>
  )
}
