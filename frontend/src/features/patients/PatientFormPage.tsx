import { AlertTriangle, ArrowLeft, UserCheck } from 'lucide-react'
import { useEffect, useState, type ReactNode } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { Link, useNavigate, useParams, useSearchParams } from 'react-router'
import { api, errorMessage, validationErrors } from '../../api/client'
import { Button } from '../../components/ui/Button'
import { Alert, Card, PageHeader } from '../../components/ui/Card'
import { Field, Input, Select, Textarea } from '../../components/ui/Field'
import { FullPageSpinner } from '../../components/ui/Spinner'
import { useAuth } from '../../contexts/useAuth'
import { todayISO } from '../../utils/format'
import { checkDuplicates, lookupGuardian, useDiagnoses, usePatient, useSavePatient } from './api'
import { clinicalFields, relationships, type ClinicalField, type PatientDetail } from './types'

type Duplicate = Awaited<ReturnType<typeof checkDuplicates>>[number]
type GuardianMatch = Awaited<ReturnType<typeof lookupGuardian>>[number]

interface FormValues {
  home_branch_id: string
  name: string
  name_bn: string
  date_of_birth: string
  gender: string
  registration_date: string
  father_name: string
  mother_name: string
  phone: string
  alt_phone: string
  email: string
  address: string
  emergency_contact_name: string
  emergency_contact_phone: string
  emergency_contact_relation: string
  referral_source: string
  referred_by: string
  notes: string
  diagnosis_ids: string[]
  clinical: Record<ClinicalField, string>
  guardian: { id?: number; name: string; phone: string; relationship: string; occupation: string }
  consents: { treatment: boolean; photo_media: boolean }
}

const blank = (v: string | null | undefined) => v ?? ''

function defaults(patient: PatientDetail | undefined, branchId: string): FormValues {
  return {
    home_branch_id: patient ? String(patient.home_branch?.id ?? '') : branchId,
    name: blank(patient?.name),
    name_bn: blank(patient?.name_bn),
    date_of_birth: blank(patient?.date_of_birth),
    gender: patient?.gender ?? '',
    registration_date: patient?.registration_date ?? todayISO(),
    father_name: blank(patient?.father_name),
    mother_name: blank(patient?.mother_name),
    phone: blank(patient?.phone),
    alt_phone: blank(patient?.alt_phone),
    email: blank(patient?.email),
    address: blank(patient?.address),
    emergency_contact_name: blank(patient?.emergency_contact_name),
    emergency_contact_phone: blank(patient?.emergency_contact_phone),
    emergency_contact_relation: blank(patient?.emergency_contact_relation),
    referral_source: blank(patient?.referral_source),
    referred_by: blank(patient?.referred_by),
    notes: blank(patient?.notes),
    diagnosis_ids: patient?.diagnoses?.map((d) => String(d.id)) ?? [],
    clinical: Object.fromEntries(clinicalFields.map(([k]) => [k, blank(patient?.clinical_profile?.[k])])) as Record<ClinicalField, string>,
    guardian: { name: '', phone: '', relationship: 'mother', occupation: '' },
    consents: { treatment: true, photo_media: false },
  }
}

/** Register a new child (/app/patients/new) or edit one (/app/patients/:id/edit). */
export default function PatientFormPage() {
  const { id } = useParams()
  const { data: patient, isLoading } = usePatient(id)
  if (id && (isLoading || !patient)) return <FullPageSpinner />
  return <PatientForm key={patient?.id ?? 'new'} patient={patient} />
}

function PatientForm({ patient }: { patient?: PatientDetail }) {
  const { user } = useAuth()
  const navigate = useNavigate()
  const save = useSavePatient()
  const { data: diagnoses } = useDiagnoses()
  const branches = user?.branches ?? []
  const editing = !!patient
  const showClinical = !editing || patient.can.view_clinical
  const [error, setError] = useState<string | null>(null)
  const [duplicates, setDuplicates] = useState<Duplicate[]>([])
  const [guardianMatches, setGuardianMatches] = useState<GuardianMatch[]>([])
  const [existingGuardian, setExistingGuardian] = useState<GuardianMatch | null>(null)

  // Coming from an online appointment request: prefill and link the request after saving.
  const [params] = useSearchParams()
  const requestId = !editing ? params.get('request_id') : null
  const initial = defaults(patient, branches.length === 1 ? String(branches[0].id) : '')
  if (requestId) {
    initial.name = params.get('name') ?? ''
    initial.phone = params.get('phone') ?? ''
    initial.guardian = { ...initial.guardian, name: params.get('guardian_name') ?? '', phone: params.get('phone') ?? '' }
  }

  const { register, handleSubmit, control, getValues, setError: setFieldError, formState } = useForm<FormValues>({ defaultValues: initial })
  // Errors for nested fields ("guardian.phone") live in nested objects.
  const err = (name: string) =>
    (name.split('.').reduce<unknown>((node, key) => (node as Record<string, unknown> | undefined)?.[key], formState.errors) as { message?: string } | undefined)?.message

  const phone = useWatch({ control, name: 'phone' })
  const dob = useWatch({ control, name: 'date_of_birth' })

  // Warn before registering the same child twice (same family phone + birth date).
  useEffect(() => {
    if (!/^01[3-9]\d{8}$/.test(phone) || !dob) return
    const t = setTimeout(() => {
      checkDuplicates({ phone, date_of_birth: dob, name: getValues('name'), except_id: patient?.id })
        .then(setDuplicates)
        .catch(() => setDuplicates([]))
    }, 400)
    return () => clearTimeout(t)
  }, [phone, dob, getValues, patient?.id])

  const onGuardianPhoneBlur = async () => {
    const guardianPhone = getValues('guardian.phone')
    if (editing || !/^01[3-9]\d{8}$/.test(guardianPhone)) return
    setGuardianMatches(await lookupGuardian(guardianPhone).catch(() => []))
  }

  const onSubmit = async (values: FormValues) => {
    setError(null)
    const payload: Record<string, unknown> = {
      ...values,
      home_branch_id: Number(values.home_branch_id),
      diagnosis_ids: (values.diagnosis_ids || []).map(Number),
      referral_source: values.referral_source || null,
    }
    if (editing) {
      delete payload.guardian
      delete payload.consents
      delete payload.registration_date
      if (!showClinical) {
        delete payload.clinical
        delete payload.diagnosis_ids
      }
    } else {
      payload.guardian = existingGuardian
        ? { id: existingGuardian.id, relationship: values.guardian.relationship }
        : values.guardian
    }

    try {
      const saved = await save.mutateAsync({ ...payload, id: patient?.id })
      if (requestId) {
        await api.put(`/appointment-requests/${requestId}`, { status: 'converted', patient_id: saved.id }).catch(() => undefined)
      }
      navigate(`/app/patients/${saved.id}`, { replace: true })
    } catch (e) {
      const fields = validationErrors(e)
      Object.entries(fields).forEach(([name, message]) => setFieldError(name as keyof FormValues, { message }))
      setError(Object.keys(fields).length ? 'Please correct the highlighted fields.' : errorMessage(e))
      window.scrollTo({ top: 0, behavior: 'smooth' })
    }
  }

  return (
    <>
      <Link to={patient ? `/app/patients/${patient.id}` : '/app/patients'} className="mb-3 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-800">
        <ArrowLeft className="size-4" /> Back
      </Link>
      <PageHeader title={editing ? `Edit ${patient.name}` : 'Register a child'} description={editing ? patient.patient_code : 'Patient ID is created automatically (CSTAR-YYYY-NNNNN).'} />

      <form onSubmit={handleSubmit(onSubmit)} className="space-y-5" noValidate>
        {error && <Alert>{error}</Alert>}

        {duplicates.length > 0 && (
          <div className="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
            <p className="flex items-center gap-2 font-medium">
              <AlertTriangle className="size-4" /> This child may already be registered
            </p>
            <ul className="mt-2 space-y-1">
              {duplicates.map((d) => (
                <li key={d.id}>
                  <Link to={`/app/patients/${d.id}`} className="font-medium underline">
                    {d.name} · {d.patient_code}
                  </Link>{' '}
                  — born {d.date_of_birth}
                  {d.branch && `, ${d.branch}`}
                </li>
              ))}
            </ul>
          </div>
        )}

        <Section title="Child">
          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="Full name" htmlFor="name" error={err('name')}>
              <Input id="name" {...register('name', { required: 'Required' })} />
            </Field>
            <Field label="Name (Bangla)" htmlFor="name_bn" error={err('name_bn')}>
              <Input id="name_bn" className="font-bn" {...register('name_bn')} />
            </Field>
            <Field label="Date of birth" htmlFor="date_of_birth" error={err('date_of_birth')}>
              <Input id="date_of_birth" type="date" max={todayISO()} {...register('date_of_birth', { required: 'Required' })} />
            </Field>
            <Field label="Gender" htmlFor="gender" error={err('gender')}>
              <Select id="gender" {...register('gender', { required: 'Required' })}>
                <option value="">Select…</option>
                <option value="male">Male</option>
                <option value="female">Female</option>
                <option value="other">Other</option>
              </Select>
            </Field>
            <Field label="Branch" htmlFor="home_branch_id" error={err('home_branch_id')}>
              <Select id="home_branch_id" {...register('home_branch_id', { required: 'Required' })}>
                <option value="">Select…</option>
                {branches.map((b) => (
                  <option key={b.id} value={b.id}>
                    {b.name}
                  </option>
                ))}
              </Select>
            </Field>
            {!editing && (
              <Field label="Registration date" htmlFor="registration_date" error={err('registration_date')}>
                <Input id="registration_date" type="date" {...register('registration_date')} />
              </Field>
            )}
          </div>
        </Section>

        <Section title="Family & contact">
          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="Father's name" htmlFor="father_name">
              <Input id="father_name" {...register('father_name')} />
            </Field>
            <Field label="Mother's name" htmlFor="mother_name">
              <Input id="mother_name" {...register('mother_name')} />
            </Field>
            <Field label="Family mobile" htmlFor="phone" hint="01XXXXXXXXX — main contact" error={err('phone')}>
              <Input id="phone" inputMode="tel" {...register('phone', { required: 'Required' })} />
            </Field>
            <Field label="Alternative mobile" htmlFor="alt_phone" error={err('alt_phone')}>
              <Input id="alt_phone" inputMode="tel" {...register('alt_phone')} />
            </Field>
            <Field label="Email" htmlFor="email" error={err('email')}>
              <Input id="email" type="email" {...register('email')} />
            </Field>
            <Field label="Address" htmlFor="address">
              <Input id="address" {...register('address')} />
            </Field>
            <Field label="Emergency contact name" htmlFor="emergency_contact_name">
              <Input id="emergency_contact_name" {...register('emergency_contact_name')} />
            </Field>
            <div className="grid grid-cols-2 gap-3">
              <Field label="Emergency mobile" htmlFor="emergency_contact_phone" error={err('emergency_contact_phone')}>
                <Input id="emergency_contact_phone" inputMode="tel" {...register('emergency_contact_phone')} />
              </Field>
              <Field label="Relation" htmlFor="emergency_contact_relation">
                <Input id="emergency_contact_relation" {...register('emergency_contact_relation')} />
              </Field>
            </div>
          </div>
        </Section>

        {!editing && (
          <Section title="Primary guardian" description="Parent or guardian responsible for the child. They can get a parent-portal login later.">
            {existingGuardian ? (
              <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-brand-200 bg-brand-50 p-3 text-sm">
                <span className="flex items-center gap-2 text-brand-800">
                  <UserCheck className="size-4" /> Using existing guardian <b>{existingGuardian.name}</b> ({existingGuardian.phone})
                </span>
                <Button type="button" variant="ghost" onClick={() => setExistingGuardian(null)}>
                  Change
                </Button>
              </div>
            ) : (
              <div className="grid gap-4 sm:grid-cols-2">
                <Field label="Guardian mobile" htmlFor="guardian.phone" error={err('guardian.phone')}>
                  <Input id="guardian.phone" inputMode="tel" {...register('guardian.phone', { onBlur: onGuardianPhoneBlur })} />
                </Field>
                <Field label="Guardian name" htmlFor="guardian.name" error={err('guardian.name')}>
                  <Input id="guardian.name" {...register('guardian.name')} />
                </Field>
                <Field label="Occupation" htmlFor="guardian.occupation">
                  <Input id="guardian.occupation" {...register('guardian.occupation')} />
                </Field>
                {guardianMatches.length > 0 && (
                  <div className="rounded-lg border border-sky-brand-100 bg-sky-brand-50 p-3 text-sm sm:col-span-2">
                    <p className="font-medium text-sky-brand-700">This mobile already belongs to a guardian (sibling?)</p>
                    {guardianMatches.map((g) => (
                      <div key={g.id} className="mt-2 flex flex-wrap items-center justify-between gap-2">
                        <span>
                          {g.name} — children: {g.children.map((c) => c.name).join(', ') || 'none'}
                        </span>
                        <Button type="button" variant="secondary" onClick={() => setExistingGuardian(g)}>
                          Use this guardian
                        </Button>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            )}
            <div className="mt-4 max-w-xs">
              <Field label="Relationship to child" htmlFor="guardian.relationship" error={err('guardian.relationship')}>
                <Select id="guardian.relationship" {...register('guardian.relationship')} className="capitalize">
                  {relationships.map((r) => (
                    <option key={r} value={r}>
                      {r}
                    </option>
                  ))}
                </Select>
              </Field>
            </div>
          </Section>
        )}

        {showClinical && (
          <Section title="Clinical intake" description="Confidential. Visible only to clinical staff and administrators after registration.">
            <Field label="Diagnosis / condition">
              <div className="grid gap-2 rounded-lg border border-slate-200 p-3 sm:grid-cols-2">
                {diagnoses?.map((d) => (
                  <label key={d.id} className="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" value={d.id} className="size-4" {...register('diagnosis_ids')} /> {d.name}
                  </label>
                ))}
              </div>
            </Field>
            <div className="mt-4 grid gap-4 sm:grid-cols-2">
              {clinicalFields.map(([key, label]) => (
                <Field key={key} label={label} htmlFor={`clinical.${key}`}>
                  <Textarea id={`clinical.${key}`} rows={2} {...register(`clinical.${key}`)} />
                </Field>
              ))}
            </div>
          </Section>
        )}

        <Section title="Referral & notes">
          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="How did they hear about C-STAR?" htmlFor="referral_source">
              <Select id="referral_source" {...register('referral_source')}>
                <option value="">—</option>
                <option value="doctor">Doctor referral</option>
                <option value="website">Website</option>
                <option value="facebook">Facebook</option>
                <option value="parent">Another parent</option>
                <option value="school">School</option>
                <option value="other">Other</option>
              </Select>
            </Field>
            <Field label="Referred by" htmlFor="referred_by">
              <Input id="referred_by" {...register('referred_by')} />
            </Field>
            <div className="sm:col-span-2">
              <Field label="Notes" htmlFor="notes">
                <Textarea id="notes" {...register('notes')} />
              </Field>
            </div>
          </div>
        </Section>

        {!editing && (
          <Section title="Consent">
            <div className="space-y-2 text-sm text-slate-700">
              <label className="flex items-start gap-2">
                <input type="checkbox" className="mt-0.5 size-4" {...register('consents.treatment')} /> Guardian has signed the treatment consent
              </label>
              <label className="flex items-start gap-2">
                <input type="checkbox" className="mt-0.5 size-4" {...register('consents.photo_media')} /> Guardian allows the child's photo/video on the website and social media
              </label>
            </div>
          </Section>
        )}

        <div className="sticky bottom-0 -mx-4 flex justify-end gap-2 border-t border-slate-200 bg-white/95 px-4 py-3 backdrop-blur sm:static sm:mx-0 sm:border-0 sm:bg-transparent sm:p-0">
          <Button type="button" variant="secondary" onClick={() => navigate(-1)}>
            Cancel
          </Button>
          <Button type="submit" loading={formState.isSubmitting}>
            {editing ? 'Save changes' : 'Register child'}
          </Button>
        </div>
      </form>
    </>
  )
}

function Section({ title, description, children }: { title: string; description?: string; children: ReactNode }) {
  return (
    <Card className="p-5">
      <h2 className="font-semibold text-slate-900">{title}</h2>
      {description && <p className="mt-0.5 text-sm text-slate-500">{description}</p>}
      <div className="mt-4">{children}</div>
    </Card>
  )
}
