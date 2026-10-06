import { Dumbbell, HeartPulse } from 'lucide-react'
import { useEffect, useState } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { errorMessage, validationErrors } from '../../../api/client'
import { Button } from '../../../components/ui/Button'
import { Alert } from '../../../components/ui/Card'
import { Field, Input, Select, Textarea } from '../../../components/ui/Field'
import { Modal } from '../../../components/ui/Modal'
import { useAuth } from '../../../contexts/useAuth'
import { cn } from '../../../utils/cn'
import { todayISO } from '../../../utils/format'
import { useEnrollmentMutations, useEnrollmentOptions } from '../api'
import type { PatientRecommendation } from '../../assessments/api'
import type { EnrollmentType, PatientDetail } from '../types'

interface FormValues {
  branch_id: string
  start_date: string
  status: 'active' | 'pending'
  notes: string
  training_group_id: string
  trainer_id: string
  monthly_fee: string
  service_id: string
  therapist_id: string
  sessions_per_week: string
  session_duration_min: string
  billing_mode: string
}

/**
 * One form, two shapes:
 *  Regular Training → class + trainer (no therapist)
 *  Therapy          → service + therapist (no class, no trainer)
 */
export function EnrollmentFormModal({
  patient,
  recommendation,
  onClose,
  onCreated,
}: {
  patient: PatientDetail
  /** Enrolling from an assessment recommendation pre-fills the form and links the two. */
  recommendation?: PatientRecommendation
  onClose: () => void
  onCreated: () => void
}) {
  const { user } = useAuth()
  const [type, setType] = useState<EnrollmentType>(recommendation?.enrollment_type ?? 'training')
  const [error, setError] = useState<string | null>(null)
  const { create } = useEnrollmentMutations(patient.id)
  const { register, handleSubmit, control, setValue, setError: setFieldError, formState } = useForm<FormValues>({
    defaultValues: {
      branch_id: String(patient.home_branch?.id ?? user?.branches?.[0]?.id ?? ''),
      start_date: todayISO(),
      status: 'active',
      billing_mode: 'per_session',
      service_id: recommendation?.service ? String(recommendation.service.id) : '',
      notes: recommendation ? `Recommended by ${recommendation.assessment.therapist.name} (${recommendation.assessment.code})${recommendation.frequency ? ` — ${recommendation.frequency}` : ''}` : '',
    } as FormValues,
  })
  const errors = formState.errors
  const branchId = Number(useWatch({ control, name: 'branch_id' })) || undefined
  const { data: options, isLoading } = useEnrollmentOptions(branchId)
  const classId = Number(useWatch({ control, name: 'training_group_id' }))
  const serviceId = Number(useWatch({ control, name: 'service_id' }))
  const selectedClass = options?.classes.find((c) => c.id === classId)
  const therapists = options?.therapists.filter((t) => t.service_ids.includes(serviceId)) ?? []

  // The service list loads after mount, so apply the recommended service once its option exists.
  const recommendedServiceId = recommendation?.service?.id
  useEffect(() => {
    if (recommendedServiceId && options?.services.some((s) => s.id === recommendedServiceId)) {
      setValue('service_id', String(recommendedServiceId))
    }
  }, [options, recommendedServiceId, setValue])

  const onSubmit = async (v: FormValues) => {
    setError(null)
    const common = {
      patient_id: patient.id,
      branch_id: Number(v.branch_id),
      type,
      start_date: v.start_date,
      status: v.status,
      notes: v.notes || null,
      ...(recommendation && type === recommendation.enrollment_type && { source_assessment_id: recommendation.assessment.id, recommendation_id: recommendation.id }),
    }
    const specific =
      type === 'training'
        ? { training_group_id: Number(v.training_group_id) || null, trainer_id: Number(v.trainer_id) || null, monthly_fee: v.monthly_fee || null }
        : {
            service_id: Number(v.service_id) || null,
            therapist_id: Number(v.therapist_id) || null,
            sessions_per_week: Number(v.sessions_per_week) || null,
            session_duration_min: Number(v.session_duration_min) || null,
            billing_mode: v.billing_mode,
          }
    try {
      await create.mutateAsync({ ...common, ...specific })
      onCreated()
    } catch (e) {
      const fields = validationErrors(e)
      Object.entries(fields).forEach(([name, message]) => setFieldError(name as keyof FormValues, { message }))
      setError(fields.type ?? (Object.keys(fields).length ? null : errorMessage(e)))
    }
  }

  return (
    <Modal open title={`New enrollment — ${patient.name}`} onClose={onClose}>
      <form onSubmit={handleSubmit(onSubmit)} className="space-y-4" noValidate>
        <div className="grid grid-cols-2 gap-2" role="radiogroup" aria-label="Enrollment type">
          {(
            [
              ['training', 'Regular Training', 'Class + trainer', Dumbbell],
              ['therapy', 'Therapy', 'Service + therapist', HeartPulse],
            ] as const
          ).map(([value, label, hint, Icon]) => (
            <button
              key={value}
              type="button"
              role="radio"
              aria-checked={type === value}
              onClick={() => setType(value)}
              className={cn(
                'rounded-xl border p-3 text-left transition',
                type === value ? 'border-brand-500 bg-brand-50 ring-2 ring-brand-500/20' : 'border-slate-200 hover:border-slate-300',
              )}
            >
              <Icon className={cn('size-5', type === value ? 'text-brand-600' : 'text-slate-400')} />
              <p className="mt-1 text-sm font-semibold text-slate-900">{label}</p>
              <p className="text-xs text-slate-500">{hint}</p>
            </button>
          ))}
        </div>

        {error && <Alert>{error}</Alert>}

        <div className="grid grid-cols-2 gap-3">
          <Field label="Branch" htmlFor="branch_id" error={errors.branch_id?.message}>
            <Select id="branch_id" {...register('branch_id')}>
              {user?.branches?.map((b) => (
                <option key={b.id} value={b.id}>
                  {b.name}
                </option>
              ))}
            </Select>
          </Field>
          <Field label="Start date" htmlFor="start_date" error={errors.start_date?.message}>
            <Input id="start_date" type="date" {...register('start_date')} />
          </Field>
        </div>

        {isLoading && <p className="text-sm text-slate-500">Loading options…</p>}

        {type === 'training' ? (
          <>
            <Field label="Class" htmlFor="training_group_id" error={errors.training_group_id?.message}>
              <Select
                id="training_group_id"
                {...register('training_group_id', {
                  onChange: (e) => {
                    const lead = options?.classes.find((c) => c.id === Number(e.target.value))?.lead_trainer
                    setValue('trainer_id', lead ? String(lead.id) : '')
                  },
                })}
              >
                <option value="">Select class…</option>
                {options?.classes.map((c) => {
                  const full = !!c.max_students && c.occupied >= c.max_students
                  return (
                    <option key={c.id} value={c.id} disabled={full}>
                      {c.name} ({c.occupied}/{c.max_students ?? '∞'}){full ? ' — full' : ''}
                    </option>
                  )
                })}
              </Select>
            </Field>
            {options && options.classes.length === 0 && <p className="text-sm text-amber-700">No active class in this branch yet.</p>}
            <div className="grid grid-cols-2 gap-3">
              <Field label="Trainer" htmlFor="trainer_id" hint={selectedClass?.lead_trainer ? 'Defaults to the class trainer' : undefined} error={errors.trainer_id?.message}>
                <Select id="trainer_id" {...register('trainer_id')}>
                  <option value="">Class trainer</option>
                  {options?.trainers.map((t) => (
                    <option key={t.id} value={t.id}>
                      {t.name}
                    </option>
                  ))}
                </Select>
              </Field>
              <Field label="Monthly fee (৳)" htmlFor="monthly_fee" error={errors.monthly_fee?.message}>
                <Input id="monthly_fee" type="number" min={0} inputMode="numeric" {...register('monthly_fee')} />
              </Field>
            </div>
          </>
        ) : (
          <>
            <Field label="Therapy service" htmlFor="service_id" error={errors.service_id?.message}>
              <Select
                id="service_id"
                {...register('service_id', {
                  onChange: (e) => {
                    setValue('therapist_id', '')
                    const s = options?.services.find((x) => x.id === Number(e.target.value))
                    setValue('session_duration_min', s?.default_duration_min ? String(s.default_duration_min) : '')
                  },
                })}
              >
                <option value="">Select therapy…</option>
                {options?.services.map((s) => (
                  <option key={s.id} value={s.id}>
                    {s.name}
                  </option>
                ))}
              </Select>
            </Field>
            <Field label="Therapist" htmlFor="therapist_id" error={errors.therapist_id?.message}>
              <Select id="therapist_id" disabled={!serviceId} {...register('therapist_id')}>
                <option value="">{serviceId ? (therapists.length ? 'Select therapist…' : 'No therapist provides this service') : 'Choose a service first'}</option>
                {therapists.map((t) => (
                  <option key={t.id} value={t.id}>
                    {t.name} — {t.type}
                  </option>
                ))}
              </Select>
            </Field>
            <div className="grid grid-cols-3 gap-3">
              <Field label="Sessions/week" htmlFor="sessions_per_week" error={errors.sessions_per_week?.message}>
                <Input id="sessions_per_week" type="number" min={1} max={7} {...register('sessions_per_week')} />
              </Field>
              <Field label="Minutes" htmlFor="session_duration_min" error={errors.session_duration_min?.message}>
                <Input id="session_duration_min" type="number" min={15} max={240} {...register('session_duration_min')} />
              </Field>
              <Field label="Billing" htmlFor="billing_mode">
                <Select id="billing_mode" {...register('billing_mode')}>
                  <option value="per_session">Per session</option>
                  <option value="package">Package</option>
                  <option value="monthly">Monthly</option>
                </Select>
              </Field>
            </div>
          </>
        )}

        <div className="grid grid-cols-2 gap-3">
          <Field label="Status" htmlFor="status">
            <Select id="status" {...register('status')}>
              <option value="active">Active (starts now)</option>
              <option value="pending">Pending (waiting to start)</option>
            </Select>
          </Field>
        </div>
        <Field label="Notes" htmlFor="notes">
          <Textarea id="notes" rows={2} {...register('notes')} />
        </Field>

        <div className="flex justify-end gap-2 pt-1">
          <Button type="button" variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" loading={formState.isSubmitting}>
            Enroll
          </Button>
        </div>
      </form>
    </Modal>
  )
}
