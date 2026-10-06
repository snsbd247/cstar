import { ArrowLeft, ChevronRight } from 'lucide-react'
import { useEffect } from 'react'
import { Link, useNavigate, useParams, useSearchParams } from 'react-router'
import { Badge, Card } from '../../components/ui/Card'
import { Spinner } from '../../components/ui/Spinner'
import { useAssessment, useAssessments } from '../assessments/api'
import { AssessmentForm } from '../assessments/components/AssessmentForm'
import { AssessmentView } from '../assessments/components/AssessmentView'

export function TherapistAssessmentsPage() {
  const { data, isLoading } = useAssessments({ mine: 1 })

  return (
    <div className="space-y-4">
      <h1 className="text-xl font-semibold text-slate-900">My assessments</h1>
      <p className="text-sm text-slate-500">Start one from an assessment appointment on your Today screen, or from a patient's page.</p>
      {isLoading ? (
        <Spinner className="text-brand-600" />
      ) : !data?.data.length ? (
        <Card className="p-5 text-sm text-slate-500">No assessments yet.</Card>
      ) : (
        <Card className="divide-y divide-slate-100">
          {data.data.map((a) => (
            <Link key={a.id} to={`/therapist/assessments/${a.id}`} className="flex items-center gap-3 px-4 py-3 hover:bg-slate-50">
              <div className="min-w-0 flex-1">
                <p className="font-medium text-slate-900">{a.patient?.name}</p>
                <p className="truncate text-xs text-slate-500">
                  {a.date} · {a.type?.name}
                </p>
              </div>
              <Badge tone={a.status === 'final' ? 'green' : 'amber'}>{a.status}</Badge>
              <ChevronRight className="size-4 text-slate-300" />
            </Link>
          ))}
        </Card>
      )}
    </div>
  )
}

/** /therapist/assessments/:id — edit a draft, or view (and share) a final one. */
export function TherapistAssessmentPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const { data } = useAssessment(Number(id))

  return (
    <div className="space-y-4">
      <button onClick={() => navigate(-1)} className="inline-flex items-center gap-1 text-sm text-slate-500">
        <ArrowLeft className="size-4" /> Back
      </button>
      {!data ? (
        <Spinner className="text-brand-600" />
      ) : data.can_edit ? (
        <>
          <p className="font-semibold text-slate-900">
            {data.patient?.name} <span className="text-sm font-normal text-slate-500">· {data.assessment_code}</span>
          </p>
          <AssessmentForm key={data.id} patientId={data.patient!.id} assessment={data} />
        </>
      ) : (
        <AssessmentView assessment={data} canShare={data.status === 'final'} />
      )}
    </div>
  )
}

/** /therapist/assessments/new?patient=&appointment= — reuses the appointment's assessment if one exists. */
export function TherapistNewAssessmentPage() {
  const [params] = useSearchParams()
  const navigate = useNavigate()
  const patientId = Number(params.get('patient'))
  const appointmentId = Number(params.get('appointment')) || undefined
  const { data: existing, isLoading } = useAssessments({ appointment_id: appointmentId }, !!appointmentId)
  const found = existing?.data[0]

  useEffect(() => {
    if (found) navigate(`/therapist/assessments/${found.id}`, { replace: true })
  }, [found, navigate])

  if ((appointmentId && isLoading) || found) return <Spinner className="text-brand-600" />

  return (
    <div className="space-y-4">
      <Link to="/therapist/assessments" className="inline-flex items-center gap-1 text-sm text-slate-500">
        <ArrowLeft className="size-4" /> Assessments
      </Link>
      <h1 className="text-xl font-semibold text-slate-900">New assessment</h1>
      <AssessmentForm patientId={patientId} appointmentId={appointmentId} onSaved={(a) => navigate(`/therapist/assessments/${a.id}`, { replace: true })} />
    </div>
  )
}
