import { ArrowLeft, ChevronRight, ClipboardPlus } from 'lucide-react'
import { useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router'
import { Badge, Card } from '../../components/ui/Card'
import { Input } from '../../components/ui/Field'
import { Spinner } from '../../components/ui/Spinner'
import { todayISO } from '../../utils/format'
import { PatientAvatar } from '../patients/components/badges'
import { useAppointments, useMyTherapyPatients, useTherapistToday, useTherapySessions } from '../therapy/api'
import { SessionNote } from '../therapy/components/SessionNoteForm'
import { PlanPanel } from '../training/components/PlanPanel'
import { useAssessments } from '../assessments/api'
import { AppointmentRow } from './TherapistApp'

export function TherapistSchedulePage() {
  const [date, setDate] = useState(todayISO)
  const { data: me } = useTherapistToday()
  const { data: appointments, isLoading } = useAppointments({ date, therapist_id: me?.therapist.id }, !!me)

  return (
    <div className="space-y-4">
      <h1 className="text-xl font-semibold text-slate-900">Schedule</h1>
      <Input type="date" value={date} onChange={(e) => setDate(e.target.value)} aria-label="Date" />
      {isLoading || !me ? (
        <Spinner className="text-brand-600" />
      ) : !appointments?.length ? (
        <Card className="p-5 text-sm text-slate-500">No appointments on this day.</Card>
      ) : (
        <div className="space-y-2">
          {appointments.map((a) => (
            <AppointmentRow key={a.id} appointment={a} />
          ))}
        </div>
      )}
    </div>
  )
}

export function TherapistPatientsPage() {
  const { data, isLoading } = useMyTherapyPatients()

  return (
    <div className="space-y-4">
      <h1 className="text-xl font-semibold text-slate-900">My patients</h1>
      {isLoading ? (
        <Spinner className="text-brand-600" />
      ) : !data?.length ? (
        <Card className="p-5 text-sm text-slate-500">No therapy patients assigned to you.</Card>
      ) : (
        <Card className="overflow-hidden">
          <ul className="divide-y divide-slate-100">
            {data.map((p) => (
              <li key={p.enrollment_id}>
                <Link to={`/therapist/patients/${p.enrollment_id}`} className="flex items-center gap-3 px-4 py-3 hover:bg-slate-50">
                  <PatientAvatar id={p.patient.id} name={p.patient.name} hasPhoto={p.patient.has_photo} size="sm" />
                  <span className="min-w-0 flex-1">
                    <span className="block truncate font-medium text-slate-900">{p.patient.name}</span>
                    <span className="text-xs text-slate-500">
                      {p.service.name} · {p.patient.age}
                    </span>
                  </span>
                  {p.status !== 'active' && <Badge tone="amber">{p.status.replace('_', ' ')}</Badge>}
                  <ChevronRight className="size-4 text-slate-300" />
                </Link>
              </li>
            ))}
          </ul>
        </Card>
      )}
    </div>
  )
}

export function TherapistPatientPage() {
  const { enrollmentId } = useParams()
  const id = Number(enrollmentId)
  const { data: patients } = useMyTherapyPatients()
  const entry = patients?.find((p) => p.enrollment_id === id)
  const { data: sessions } = useTherapySessions({ patient_id: entry?.patient.id })
  const { data: assessments } = useAssessments({ patient_id: entry?.patient.id }, !!entry)

  if (!entry) return <Spinner className="text-brand-600" />

  return (
    <div className="space-y-4">
      <Link to="/therapist/patients" className="inline-flex items-center gap-1 text-sm text-slate-500">
        <ArrowLeft className="size-4" /> My patients
      </Link>
      <div className="flex items-center gap-3">
        <PatientAvatar id={entry.patient.id} name={entry.patient.name} hasPhoto={entry.patient.has_photo} size="lg" />
        <div>
          <h1 className="text-xl font-semibold text-slate-900">{entry.patient.name}</h1>
          <p className="text-sm text-slate-500">
            {entry.patient.patient_code} · {entry.patient.age} · {entry.service.name}
          </p>
        </div>
      </div>
      <PlanPanel enrollmentId={id} canEdit title="Therapy plan" />
      <Card className="p-5">
        <div className="flex items-center justify-between gap-2">
          <h3 className="font-semibold text-slate-900">Assessments</h3>
          <Link to={`/therapist/assessments/new?patient=${entry.patient.id}`} className="inline-flex items-center gap-1 text-sm font-medium text-brand-700">
            <ClipboardPlus className="size-4" /> New
          </Link>
        </div>
        <ul className="mt-3 divide-y divide-slate-100">
          {assessments?.data.length === 0 && <li className="text-sm text-slate-500">No assessments yet.</li>}
          {assessments?.data.map((a) => (
            <li key={a.id}>
              <Link to={`/therapist/assessments/${a.id}`} className="flex items-center justify-between gap-2 py-2.5">
                <span className="text-sm text-slate-800">
                  {a.type?.name} <span className="text-xs text-slate-500">· {a.date}</span>
                </span>
                <Badge tone={a.status === 'final' ? 'green' : 'amber'}>{a.status}</Badge>
              </Link>
            </li>
          ))}
        </ul>
      </Card>
      <Card className="p-5">
        <h3 className="font-semibold text-slate-900">Session history</h3>
        <ul className="mt-3 space-y-4">
          {sessions?.data.length === 0 && <li className="text-sm text-slate-500">No sessions yet.</li>}
          {sessions?.data.map((s) => (
            <li key={s.id} className="border-l-2 border-sky-brand-200 pl-3">
              <p className="text-xs text-slate-500">
                {s.date} · {s.service?.name} · {s.therapist?.name} · {s.status}
              </p>
              {s.observation && <p className="text-sm text-slate-800">{s.observation}</p>}
              {s.next_session_plan && <p className="text-sm text-slate-600">Next: {s.next_session_plan}</p>}
            </li>
          ))}
        </ul>
      </Card>
    </div>
  )
}

export function TherapistSessionsPage() {
  const { data: me } = useTherapistToday()
  const { data, isLoading } = useTherapySessions({ therapist_id: me?.therapist.id })

  return (
    <div className="space-y-4">
      <h1 className="text-xl font-semibold text-slate-900">My session notes</h1>
      {isLoading || !me ? (
        <Spinner className="text-brand-600" />
      ) : (
        <div className="space-y-2">
          {data?.data.map((s) => (
            <Link key={s.id} to={`/therapist/session/${s.appointment_id}`}>
              <Card className="mb-2 p-4 hover:border-brand-200">
                <div className="flex items-center justify-between gap-2">
                  <p className="font-medium text-slate-900">{s.patient?.name}</p>
                  <Badge tone={s.status === 'final' ? 'green' : 'amber'}>{s.status}</Badge>
                </div>
                <p className="text-xs text-slate-500">
                  {s.date} · {s.service?.name}
                </p>
                {s.observation && <p className="mt-1 line-clamp-2 text-sm text-slate-700">{s.observation}</p>}
              </Card>
            </Link>
          ))}
        </div>
      )}
    </div>
  )
}

export function TherapistSessionPage() {
  const { appointmentId } = useParams()
  const navigate = useNavigate()

  return (
    <div className="space-y-4">
      <button onClick={() => navigate(-1)} className="inline-flex items-center gap-1 text-sm text-slate-500">
        <ArrowLeft className="size-4" /> Back
      </button>
      <SessionNote appointmentId={Number(appointmentId)} onDone={() => navigate('/therapist')} />
    </div>
  )
}
