import { ArrowLeft } from 'lucide-react'
import { useState } from 'react'
import { Link, useParams, useSearchParams } from 'react-router'
import { errorMessage } from '../../api/client'
import { Alert, Badge, Card, PageHeader } from '../../components/ui/Card'
import { Select } from '../../components/ui/Field'
import { Spinner } from '../../components/ui/Spinner'
import { useAssessment, useAssessments } from './api'
import { AssessmentView } from './components/AssessmentView'

/** Admin list of assessments for children the user can see. Therapists write them in their own app. */
export default function AssessmentsPage() {
  const [params] = useSearchParams()
  const [status, setStatus] = useState(params.get('status') ?? '')
  const { data, isLoading } = useAssessments({ status: status || undefined })

  return (
    <>
      <PageHeader title="Assessments" description="Written by therapists. Final assessments feed recommendations to the front desk." />
      <Card className="mb-4 p-3">
        <Select value={status} onChange={(e) => setStatus(e.target.value)} className="sm:w-56" aria-label="Status">
          <option value="">All</option>
          <option value="draft">Draft</option>
          <option value="final">Final</option>
        </Select>
      </Card>
      {isLoading ? (
        <Spinner className="text-brand-600" />
      ) : !data?.data.length ? (
        <Card className="p-5 text-sm text-slate-500">No assessments yet.</Card>
      ) : (
        <Card className="divide-y divide-slate-100">
          {data.data.map((a) => (
            <Link key={a.id} to={`/app/assessments/${a.id}`} className="flex flex-wrap items-center justify-between gap-2 px-4 py-3 hover:bg-slate-50">
              <div>
                <p className="font-medium text-slate-900">{a.patient?.name}</p>
                <p className="text-xs text-slate-500">
                  {a.date} · {a.type?.name} · {a.therapist?.name} · {a.assessment_code}
                </p>
              </div>
              <div className="flex gap-2">
                {a.shared_with_parent && <Badge tone="blue">shared</Badge>}
                <Badge tone={a.status === 'final' ? 'green' : 'amber'}>{a.status}</Badge>
              </div>
            </Link>
          ))}
        </Card>
      )}
    </>
  )
}

export function AssessmentDetailPage() {
  const { id } = useParams()
  const { data, isLoading, error } = useAssessment(Number(id))

  return (
    <>
      <Link to="/app/assessments" className="mb-3 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-800">
        <ArrowLeft className="size-4" /> Assessments
      </Link>
      {isLoading ? <Spinner className="text-brand-600" /> : error || !data ? <Alert>{errorMessage(error)}</Alert> : <AssessmentView assessment={data} />}
      {data?.patient && (
        <Link to={`/app/patients/${data.patient.id}?tab=assessments`} className="mt-3 inline-block text-sm text-sky-brand-600">
          Open {data.patient.name}'s profile →
        </Link>
      )}
    </>
  )
}
