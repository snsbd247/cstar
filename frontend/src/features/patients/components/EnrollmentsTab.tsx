import { ArrowRightLeft, Dumbbell, HeartPulse, History, Plus } from 'lucide-react'
import { useState } from 'react'
import { errorMessage, validationErrors } from '../../../api/client'
import { Button } from '../../../components/ui/Button'
import { Alert, Card } from '../../../components/ui/Card'
import { Field, Input, Select, Textarea } from '../../../components/ui/Field'
import { Modal } from '../../../components/ui/Modal'
import { cn } from '../../../utils/cn'
import { todayISO } from '../../../utils/format'
import { useEnrollmentMutations, useEnrollmentOptions } from '../api'
import { endReasons, type Enrollment, type PatientDetail } from '../types'
import { EnrollmentStatusBadge } from './badges'

type Action = 'activate' | 'hold' | 'resume' | 'complete' | 'discontinue'

const actionsFor: Record<Enrollment['status'], Action[]> = {
  pending: ['activate', 'discontinue'],
  active: ['hold', 'complete', 'discontinue'],
  on_hold: ['resume', 'complete', 'discontinue'],
  completed: [],
  discontinued: [],
}
const actionLabel: Record<Action, string> = { activate: 'Start', hold: 'Put on hold', resume: 'Resume', complete: 'Complete', discontinue: 'Discontinue' }

export function EnrollmentsTab({ patient, onNew }: { patient: PatientDetail; onNew: () => void }) {
  const [acting, setActing] = useState<{ enrollment: Enrollment; action: Action } | null>(null)
  const [transferring, setTransferring] = useState<Enrollment | null>(null)
  const training = patient.enrollments.filter((e) => e.type === 'training')
  const therapy = patient.enrollments.filter((e) => e.type === 'therapy')

  return (
    <div className="space-y-6">
      {patient.can.enroll && (
        <div className="flex justify-end">
          <Button onClick={onNew}>
            <Plus className="size-4" /> New enrollment
          </Button>
        </div>
      )}

      {[
        ['Regular Training', 'Class + trainer. Attendance and training records come from here.', training, Dumbbell, 'text-brand-600'] as const,
        ['Therapy', 'Service + therapist. Appointments and therapy sessions come from here.', therapy, HeartPulse, 'text-sky-brand-600'] as const,
      ].map(([title, hint, list, Icon, color]) => (
        <section key={title}>
          <h2 className="flex items-center gap-2 font-semibold text-slate-900">
            <Icon className={cn('size-4', color)} /> {title}
          </h2>
          <p className="mb-3 text-sm text-slate-500">{hint}</p>
          {list.length === 0 ? (
            <Card className="p-4 text-sm text-slate-500">No {title.toLowerCase()} enrollment.</Card>
          ) : (
            <div className="grid gap-3 md:grid-cols-2">
              {list.map((e) => (
                <EnrollmentCard
                  key={e.id}
                  enrollment={e}
                  canManage={patient.can.enroll}
                  onAction={(action) => setActing({ enrollment: e, action })}
                  onTransfer={() => setTransferring(e)}
                />
              ))}
            </div>
          )}
        </section>
      ))}

      {acting && <StatusModal patientId={patient.id} {...acting} onClose={() => setActing(null)} />}
      {transferring && <TransferModal patientId={patient.id} enrollment={transferring} onClose={() => setTransferring(null)} />}
    </div>
  )
}

function EnrollmentCard({ enrollment: e, canManage, onAction, onTransfer }: { enrollment: Enrollment; canManage: boolean; onAction: (a: Action) => void; onTransfer: () => void }) {
  const [showHistory, setShowHistory] = useState(false)
  const open = ['pending', 'active', 'on_hold'].includes(e.status)

  return (
    <Card className={cn('p-4', !open && 'opacity-75')}>
      <div className="flex items-start justify-between gap-2">
        <div>
          <p className="font-medium text-slate-900">{e.training ? e.training.class.name : e.therapy?.service.name}</p>
          <p className="text-sm text-slate-600">{e.training ? `Trainer: ${e.training.trainer.name}` : `Therapist: ${e.therapy?.therapist.name}`}</p>
        </div>
        <EnrollmentStatusBadge status={e.status} />
      </div>
      <dl className="mt-3 grid grid-cols-2 gap-x-3 gap-y-1 text-xs text-slate-500">
        <dt>Code</dt>
        <dd className="text-slate-700">{e.enrollment_code}</dd>
        <dt>Branch</dt>
        <dd className="text-slate-700">{e.branch?.name}</dd>
        <dt>Period</dt>
        <dd className="text-slate-700">
          {e.start_date} → {e.end_date ?? 'ongoing'}
        </dd>
        {e.therapy && (
          <>
            <dt>Plan</dt>
            <dd className="text-slate-700">
              {e.therapy.sessions_per_week ?? '—'}×/week · {e.therapy.session_duration_min ?? '—'} min · {e.therapy.billing_mode.replace('_', ' ')}
            </dd>
          </>
        )}
        {e.training?.monthly_fee && (
          <>
            <dt>Monthly fee</dt>
            <dd className="text-slate-700">৳{Number(e.training.monthly_fee).toLocaleString()}</dd>
          </>
        )}
        {e.end_reason && (
          <>
            <dt>Ended because</dt>
            <dd className="text-slate-700">{endReasons[e.end_reason] ?? e.end_reason}</dd>
          </>
        )}
      </dl>

      <div className="mt-3 flex flex-wrap items-center gap-1 border-t border-slate-100 pt-3">
        {canManage &&
          actionsFor[e.status].map((a) => (
            <Button key={a} variant={a === 'discontinue' ? 'ghost' : 'secondary'} className={cn('min-h-8 px-2.5 py-1 text-xs', a === 'discontinue' && 'text-red-600')} onClick={() => onAction(a)}>
              {actionLabel[a]}
            </Button>
          ))}
        {canManage && open && (
          <Button variant="secondary" className="min-h-8 px-2.5 py-1 text-xs" onClick={onTransfer}>
            <ArrowRightLeft className="size-3.5" /> Transfer
          </Button>
        )}
        {!!e.assignments?.length && (
          <button onClick={() => setShowHistory((v) => !v)} className="ml-auto inline-flex items-center gap-1 text-xs text-slate-500 hover:text-slate-800">
            <History className="size-3.5" /> History
          </button>
        )}
      </div>

      {showHistory && (
        <ol className="mt-2 space-y-1 text-xs text-slate-600">
          {e.assignments?.map((a, i) => (
            <li key={i}>
              {a.from_date} → {a.to_date ?? 'now'}: {[a.class, a.trainer, a.therapist].filter(Boolean).join(' · ')}
              {a.reason && <span className="text-slate-400"> ({a.reason})</span>}
            </li>
          ))}
        </ol>
      )}
    </Card>
  )
}

function StatusModal({ patientId, enrollment, action, onClose }: { patientId: number; enrollment: Enrollment; action: Action; onClose: () => void }) {
  const { changeStatus } = useEnrollmentMutations(patientId)
  const ending = action === 'complete' || action === 'discontinue'
  const [endReason, setEndReason] = useState(action === 'complete' ? 'goals_achieved' : '')
  const [today] = useState(todayISO)
  const [endDate, setEndDate] = useState(today)
  const [note, setNote] = useState('')
  const [error, setError] = useState<string | null>(null)

  const submit = async () => {
    setError(null)
    try {
      await changeStatus.mutateAsync({ id: enrollment.id, action, ...(ending ? { end_reason: endReason || null, end_date: endDate, end_note: note || null } : {}) })
      onClose()
    } catch (e) {
      setError(Object.values(validationErrors(e))[0] ?? errorMessage(e))
    }
  }

  return (
    <Modal open title={`${actionLabel[action]} — ${enrollment.training?.class.name ?? enrollment.therapy?.service.name}`} onClose={onClose}>
      <div className="space-y-4">
        {error && <Alert>{error}</Alert>}
        {ending ? (
          <>
            <Field label="Reason" htmlFor="end_reason">
              <Select id="end_reason" value={endReason} onChange={(e) => setEndReason(e.target.value)}>
                <option value="">Select…</option>
                {Object.entries(endReasons).map(([k, v]) => (
                  <option key={k} value={k}>
                    {v}
                  </option>
                ))}
              </Select>
            </Field>
            <Field label="End date" htmlFor="end_date">
              <Input id="end_date" type="date" value={endDate} max={today} onChange={(e) => setEndDate(e.target.value)} />
            </Field>
            <Field label="Note" htmlFor="end_note">
              <Textarea id="end_note" rows={2} value={note} onChange={(e) => setNote(e.target.value)} />
            </Field>
            <p className="text-xs text-slate-500">The trainer/therapist will no longer see this child unless another enrollment links them.</p>
          </>
        ) : (
          <p className="text-sm text-slate-600">Confirm: {actionLabel[action].toLowerCase()} this enrollment?</p>
        )}
        <div className="flex justify-end gap-2">
          <Button variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button variant={action === 'discontinue' ? 'danger' : 'primary'} loading={changeStatus.isPending} onClick={submit}>
            {actionLabel[action]}
          </Button>
        </div>
      </div>
    </Modal>
  )
}

function TransferModal({ patientId, enrollment, onClose }: { patientId: number; enrollment: Enrollment; onClose: () => void }) {
  const { transfer } = useEnrollmentMutations(patientId)
  const { data: options } = useEnrollmentOptions(enrollment.branch?.id)
  const [classId, setClassId] = useState(String(enrollment.training?.class.id ?? ''))
  const [trainerId, setTrainerId] = useState('')
  const [therapistId, setTherapistId] = useState('')
  const [reason, setReason] = useState('')
  const [error, setError] = useState<string | null>(null)
  const therapists = options?.therapists.filter((t) => enrollment.therapy && t.service_ids.includes(enrollment.therapy.service.id)) ?? []

  const submit = async () => {
    setError(null)
    try {
      await transfer.mutateAsync({
        id: enrollment.id,
        reason,
        ...(enrollment.training ? { training_group_id: Number(classId) || null, trainer_id: Number(trainerId) || null } : { therapist_id: Number(therapistId) || null }),
      })
      onClose()
    } catch (e) {
      setError(Object.values(validationErrors(e))[0] ?? errorMessage(e))
    }
  }

  return (
    <Modal open title="Transfer enrollment" onClose={onClose}>
      <div className="space-y-4">
        {error && <Alert>{error}</Alert>}
        {enrollment.training ? (
          <>
            <Field label="Class">
              <Select value={classId} onChange={(e) => setClassId(e.target.value)} aria-label="Class">
                {options?.classes.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.name} ({c.occupied}/{c.max_students ?? '∞'})
                  </option>
                ))}
              </Select>
            </Field>
            <Field label="Trainer" hint="Leave as default to use the class trainer">
              <Select value={trainerId} onChange={(e) => setTrainerId(e.target.value)} aria-label="Trainer">
                <option value="">Default</option>
                {options?.trainers.map((t) => (
                  <option key={t.id} value={t.id}>
                    {t.name}
                  </option>
                ))}
              </Select>
            </Field>
          </>
        ) : (
          <Field label={`New therapist for ${enrollment.therapy?.service.name}`}>
            <Select value={therapistId} onChange={(e) => setTherapistId(e.target.value)} aria-label="Therapist">
              <option value="">Select…</option>
              {therapists
                .filter((t) => t.id !== enrollment.therapy?.therapist.id)
                .map((t) => (
                  <option key={t.id} value={t.id}>
                    {t.name}
                  </option>
                ))}
            </Select>
          </Field>
        )}
        <Field label="Reason">
          <Textarea rows={2} value={reason} onChange={(e) => setReason(e.target.value)} aria-label="Reason" />
        </Field>
        <div className="flex justify-end gap-2">
          <Button variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button loading={transfer.isPending} disabled={!reason.trim()} onClick={submit}>
            Transfer
          </Button>
        </div>
      </div>
    </Modal>
  )
}
