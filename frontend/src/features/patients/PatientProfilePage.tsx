import { ArrowLeft, CalendarPlus, Camera, GraduationCap, Pencil, Phone, Wallet } from 'lucide-react'
import { useRef, useState } from 'react'
import type { PatientRecommendation } from '../assessments/api'
import { Link, useParams, useSearchParams } from 'react-router'
import { errorMessage } from '../../api/client'
import { Button } from '../../components/ui/Button'
import { Alert, Badge, Card } from '../../components/ui/Card'
import { FullPageSpinner } from '../../components/ui/Spinner'
import { useAuth } from '../../contexts/useAuth'
import { cn } from '../../utils/cn'
import { usePatient, useUploadPhoto } from './api'
import { PatientAvatar, PatientTypeBadge } from './components/badges'
import { AssessmentsTab } from './components/AssessmentsTab'
import { DocumentsTab } from './components/DocumentsTab'
import { EnrollmentFormModal } from './components/EnrollmentFormModal'
import { EnrollmentsTab } from './components/EnrollmentsTab'
import { GuardiansTab } from './components/GuardiansTab'
import { OverviewTab } from './components/OverviewTab'
import { TimelineTab } from './components/TimelineTab'
import { TherapyTab } from './components/TherapyTab'
import { TrainingTab } from './components/TrainingTab'
import { BookAppointmentModal } from '../therapy/components/BookAppointmentModal'

const tabs = [
  ['overview', 'Overview'],
  ['enrollments', 'Enrollments'],
  ['training', 'Training'],
  ['therapy', 'Therapy'],
  ['assessments', 'Assessments'],
  ['guardians', 'Guardians'],
  ['documents', 'Documents'],
  ['timeline', 'Timeline'],
] as const

export default function PatientProfilePage() {
  const { id } = useParams()
  const { data: patient, isLoading, error } = usePatient(id)
  const [params, setParams] = useSearchParams()
  const [enrolling, setEnrolling] = useState<false | true | PatientRecommendation>(false)
  const [booking, setBooking] = useState(false)
  const { can } = useAuth()
  const photoInput = useRef<HTMLInputElement>(null)
  const uploadPhoto = useUploadPhoto(Number(id))
  const tab = params.get('tab') ?? 'overview'

  if (isLoading) return <FullPageSpinner />
  if (error || !patient) return <Alert>{errorMessage(error)}</Alert>

  const open = patient.enrollments.filter((e) => ['pending', 'active', 'on_hold'].includes(e.status))

  return (
    <>
      <Link to="/app/patients" className="mb-3 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-800">
        <ArrowLeft className="size-4" /> Patients
      </Link>

      <Card className="p-5">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-start">
          <div className="relative w-fit">
            <PatientAvatar id={patient.id} name={patient.name} hasPhoto={patient.has_photo} size="lg" />
            {patient.can.update && (
              <>
                <button
                  onClick={() => photoInput.current?.click()}
                  className="absolute -bottom-1 -right-1 rounded-full border border-slate-200 bg-white p-1.5 text-slate-500 shadow-sm hover:text-slate-800"
                  aria-label="Change photo"
                >
                  <Camera className="size-3.5" />
                </button>
                <input
                  ref={photoInput}
                  type="file"
                  accept="image/jpeg,image/png,image/webp"
                  hidden
                  onChange={(e) => e.target.files?.[0] && uploadPhoto.mutate(e.target.files[0])}
                />
              </>
            )}
          </div>

          <div className="min-w-0 flex-1">
            <div className="flex flex-wrap items-center gap-2">
              <h1 className="text-xl font-semibold text-slate-900">{patient.name}</h1>
              <PatientTypeBadge type={patient.type} />
              {patient.status !== 'active' && <Badge tone="red">{patient.status.replace('_', ' ')}</Badge>}
            </div>
            {patient.name_bn && <p className="font-bn text-slate-500">{patient.name_bn}</p>}
            <p className="mt-1 text-sm text-slate-500">
              {patient.patient_code} · {patient.age} · <span className="capitalize">{patient.gender}</span> · {patient.home_branch?.name}
            </p>
            <a href={`tel:${patient.phone}`} className="mt-1 inline-flex items-center gap-1 text-sm text-sky-brand-600">
              <Phone className="size-3.5" /> {patient.phone}
            </a>
            {open.length > 0 && (
              <div className="mt-3 flex flex-wrap gap-2">
                {open.map((e) => (
                  <span key={e.id} className="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-2.5 py-1 text-xs text-slate-700">
                    <GraduationCap className={cn('size-3.5', e.type === 'training' ? 'text-brand-600' : 'text-sky-brand-600')} />
                    {e.type === 'training' ? `${e.training?.class.name} · ${e.training?.trainer.name}` : `${e.therapy?.service.name} · ${e.therapy?.therapist.name}`}
                  </span>
                ))}
              </div>
            )}
          </div>

          {/* Receptionist fast path: Profile → New Enrollment → Appointment → Payment (Plan §৩৭) */}
          <div className="grid grid-cols-2 gap-2 sm:flex sm:flex-col">
            {patient.can.enroll && (
              <Button onClick={() => setEnrolling(true)}>
                <GraduationCap className="size-4" /> New enrollment
              </Button>
            )}
            <Button variant="secondary" disabled={!can('appointments.manage')} onClick={() => setBooking(true)}>
              <CalendarPlus className="size-4" /> Appointment
            </Button>
            <Button variant="secondary" disabled title="Billing arrives in Sprint 10">
              <Wallet className="size-4" /> Payment
            </Button>
            {patient.can.update && (
              <Link to={`/app/patients/${patient.id}/edit`}>
                <Button variant="ghost" className="w-full">
                  <Pencil className="size-4" /> Edit
                </Button>
              </Link>
            )}
          </div>
        </div>
      </Card>

      <div className="mt-5 overflow-x-auto border-b border-slate-200">
        <nav className="flex min-w-max gap-1">
          {tabs.map(([key, label]) => (
            <button
              key={key}
              onClick={() => setParams({ tab: key }, { replace: true })}
              className={cn(
                'border-b-2 px-3 py-2.5 text-sm font-medium',
                tab === key ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-800',
              )}
            >
              {label}
              {key === 'enrollments' && <span className="ml-1.5 rounded bg-slate-100 px-1.5 text-xs text-slate-500">{patient.enrollments.length}</span>}
            </button>
          ))}
        </nav>
      </div>

      <div className="mt-5">
        {tab === 'overview' && <OverviewTab patient={patient} />}
        {tab === 'enrollments' && <EnrollmentsTab patient={patient} onNew={() => setEnrolling(true)} />}
        {tab === 'training' && <TrainingTab patient={patient} />}
        {tab === 'therapy' && <TherapyTab patient={patient} />}
        {tab === 'assessments' && <AssessmentsTab patient={patient} onEnroll={(r) => setEnrolling(r)} />}
        {tab === 'guardians' && <GuardiansTab patient={patient} />}
        {tab === 'documents' && <DocumentsTab patient={patient} />}
        {tab === 'timeline' && <TimelineTab patientId={patient.id} />}
      </div>

      {booking && <BookAppointmentModal patient={{ id: patient.id, name: patient.name }} onClose={() => setBooking(false)} onBooked={() => setParams({ tab: 'therapy' }, { replace: true })} />}

      {enrolling && (
        <EnrollmentFormModal
          patient={patient}
          recommendation={typeof enrolling === 'object' ? enrolling : undefined}
          onClose={() => setEnrolling(false)}
          onCreated={() => {
            setEnrolling(false)
            setParams({ tab: 'enrollments' }, { replace: true })
          }}
        />
      )}
    </>
  )
}
