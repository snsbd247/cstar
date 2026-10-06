import { Lock } from 'lucide-react'
import type { ReactNode } from 'react'
import { Badge, Card } from '../../../components/ui/Card'
import { clinicalFields, type PatientDetail } from '../types'

export function OverviewTab({ patient }: { patient: PatientDetail }) {
  return (
    <div className="grid gap-5 lg:grid-cols-2">
      <Card className="p-5">
        <h2 className="mb-3 font-semibold text-slate-900">Personal & family</h2>
        <dl className="space-y-2 text-sm">
          <Row label="Date of birth">{patient.date_of_birth}</Row>
          <Row label="Father">{patient.father_name}</Row>
          <Row label="Mother">{patient.mother_name}</Row>
          <Row label="Mobile">{patient.phone}</Row>
          <Row label="Alt. mobile">{patient.alt_phone}</Row>
          <Row label="Email">{patient.email}</Row>
          <Row label="Address">{patient.address}</Row>
          <Row label="Emergency">
            {patient.emergency_contact_name &&
              `${patient.emergency_contact_name} (${patient.emergency_contact_relation ?? '—'}) ${patient.emergency_contact_phone ?? ''}`}
          </Row>
          <Row label="Registered">{patient.registration_date}</Row>
          <Row label="Referral">{[patient.referral_source, patient.referred_by].filter(Boolean).join(' — ')}</Row>
          <Row label="Consent">
            <span className="flex flex-wrap gap-1">
              {patient.consents.length === 0 && '—'}
              {patient.consents.map((c) => (
                <Badge key={c.type} tone={c.granted ? 'green' : 'gray'}>
                  {c.type.replace('_', ' ')}: {c.granted ? 'yes' : 'no'}
                </Badge>
              ))}
            </span>
          </Row>
          <Row label="Notes">{patient.notes}</Row>
        </dl>
      </Card>

      <Card className="p-5">
        <h2 className="mb-3 flex items-center gap-2 font-semibold text-slate-900">
          Clinical profile <Lock className="size-3.5 text-slate-400" />
        </h2>
        {patient.can.view_clinical ? (
          <dl className="space-y-3 text-sm">
            <div>
              <dt className="text-slate-500">Diagnosis</dt>
              <dd className="mt-1 flex flex-wrap gap-1">
                {patient.diagnoses?.length ? patient.diagnoses.map((d) => <Badge key={d.id} tone="blue">{d.name}</Badge>) : '—'}
              </dd>
            </div>
            {clinicalFields.map(([key, label]) => (
              <div key={key}>
                <dt className="text-slate-500">{label}</dt>
                <dd className="mt-0.5 whitespace-pre-line text-slate-800">{patient.clinical_profile?.[key] || '—'}</dd>
              </div>
            ))}
          </dl>
        ) : (
          <p className="text-sm text-slate-500">Clinical information is only visible to clinical staff and administrators.</p>
        )}
      </Card>
    </div>
  )
}

function Row({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div className="grid grid-cols-[110px_1fr] gap-2">
      <dt className="text-slate-500">{label}</dt>
      <dd className="text-slate-800">{children || '—'}</dd>
    </div>
  )
}
