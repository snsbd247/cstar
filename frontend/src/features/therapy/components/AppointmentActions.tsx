import { useState } from 'react'
import { errorMessage, validationErrors } from '../../../api/client'
import { Button } from '../../../components/ui/Button'
import { Alert } from '../../../components/ui/Card'
import { Field, Input } from '../../../components/ui/Field'
import { Modal } from '../../../components/ui/Modal'
import { cn } from '../../../utils/cn'
import { todayISO } from '../../../utils/format'
import { useAppointmentMutations, useAvailability, statusStyle, type Appointment, type AppointmentStatus } from '../api'

export function StatusPill({ status }: { status: AppointmentStatus }) {
  return <span className={cn('inline-flex rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset', statusStyle[status].className)}>{statusStyle[status].label}</span>
}

/** Front-desk buttons for one appointment: confirm, check in, cancel, no-show, reschedule. */
export function AppointmentActions({ appointment: a, compact }: { appointment: Appointment; compact?: boolean }) {
  const { action } = useAppointmentMutations()
  const [dialog, setDialog] = useState<'cancel' | 'reschedule' | null>(null)
  const [error, setError] = useState<string | null>(null)
  const isToday = a.date <= todayISO()
  const btn = cn('min-h-8 px-2.5 py-1 text-xs', compact && 'min-h-7 px-2')

  const run = async (name: string) => {
    setError(null)
    try {
      await action.mutateAsync({ id: a.id, action: name })
    } catch (e) {
      setError(Object.values(validationErrors(e))[0] ?? errorMessage(e))
    }
  }

  if (!['pending', 'confirmed'].includes(a.status)) return null

  return (
    <div className="flex flex-wrap items-center gap-1">
      {error && <span className="w-full text-xs text-red-600">{error}</span>}
      {a.status === 'pending' && (
        <Button variant="secondary" className={btn} onClick={() => run('confirm')}>
          Confirm
        </Button>
      )}
      {isToday && (
        <Button className={btn} onClick={() => run('check-in')}>
          Check in
        </Button>
      )}
      <Button variant="secondary" className={btn} onClick={() => setDialog('reschedule')}>
        Reschedule
      </Button>
      {isToday && (
        <Button variant="ghost" className={btn} onClick={() => run('no-show')}>
          No show
        </Button>
      )}
      <Button variant="ghost" className={cn(btn, 'text-red-600')} onClick={() => setDialog('cancel')}>
        Cancel
      </Button>
      {dialog === 'cancel' && <CancelDialog appointment={a} onClose={() => setDialog(null)} />}
      {dialog === 'reschedule' && <RescheduleDialog appointment={a} onClose={() => setDialog(null)} />}
    </div>
  )
}

function CancelDialog({ appointment, onClose }: { appointment: Appointment; onClose: () => void }) {
  const { action } = useAppointmentMutations()
  const [reason, setReason] = useState('')
  const [error, setError] = useState<string | null>(null)

  return (
    <Modal open title="Cancel appointment" onClose={onClose}>
      <div className="space-y-4">
        {error && <Alert>{error}</Alert>}
        <p className="text-sm text-slate-600">
          {appointment.patient?.name} · {appointment.service?.name} · {appointment.date} {appointment.start_time}
        </p>
        <Field label="Reason" htmlFor="cancel_reason" hint="Cancelling less than 24 hours before counts as a late cancellation.">
          <Input id="cancel_reason" value={reason} onChange={(e) => setReason(e.target.value)} />
        </Field>
        <div className="flex justify-end gap-2">
          <Button variant="secondary" onClick={onClose}>
            Back
          </Button>
          <Button
            variant="danger"
            disabled={!reason.trim()}
            loading={action.isPending}
            onClick={async () => {
              try {
                await action.mutateAsync({ id: appointment.id, action: 'cancel', reason })
                onClose()
              } catch (e) {
                setError(Object.values(validationErrors(e))[0] ?? errorMessage(e))
              }
            }}
          >
            Cancel appointment
          </Button>
        </div>
      </div>
    </Modal>
  )
}

function RescheduleDialog({ appointment, onClose }: { appointment: Appointment; onClose: () => void }) {
  const { reschedule } = useAppointmentMutations()
  const [date, setDate] = useState(appointment.date >= todayISO() ? appointment.date : todayISO())
  const [time, setTime] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)
  const { data, isLoading } = useAvailability({ therapist_id: appointment.therapist?.id, branch_id: appointment.branch?.id, date, service_id: appointment.service?.id })

  return (
    <Modal open title="Reschedule" onClose={onClose}>
      <div className="space-y-4">
        {error && <Alert>{error}</Alert>}
        <p className="text-sm text-slate-600">
          {appointment.patient?.name} with {appointment.therapist?.name}
        </p>
        <Field label="New date" htmlFor="rs_date">
          <Input id="rs_date" type="date" min={todayISO()} value={date} onChange={(e) => (setDate(e.target.value), setTime(null))} />
        </Field>
        <SlotGrid loading={isLoading} closed={data?.closed} slots={data?.slots} value={time} onChange={setTime} />
        <div className="flex justify-end gap-2">
          <Button variant="secondary" onClick={onClose}>
            Back
          </Button>
          <Button
            disabled={!time}
            loading={reschedule.isPending}
            onClick={async () => {
              try {
                await reschedule.mutateAsync({ id: appointment.id, date, start_time: time! })
                onClose()
              } catch (e) {
                setError(Object.values(validationErrors(e))[0] ?? errorMessage(e))
              }
            }}
          >
            Move appointment
          </Button>
        </div>
      </div>
    </Modal>
  )
}

/** Time slot picker driven by /availability. */
export function SlotGrid({ loading, closed, slots, value, onChange }: {
  loading: boolean
  closed?: string | null
  slots?: { start: string; end: string; available: boolean }[]
  value: string | null
  onChange: (start: string) => void
}) {
  if (loading) return <p className="text-sm text-slate-500">Checking free times…</p>
  if (closed) return <Alert>{closed}</Alert>
  if (!slots) return null
  if (!slots.some((s) => s.available)) return <p className="text-sm text-amber-700">No free time on this day.</p>

  return (
    <div className="grid grid-cols-3 gap-2 sm:grid-cols-4" role="radiogroup" aria-label="Time">
      {slots.map((s) => (
        <button
          key={s.start}
          type="button"
          role="radio"
          aria-checked={value === s.start}
          disabled={!s.available}
          onClick={() => onChange(s.start)}
          className={cn(
            'min-h-10 rounded-lg text-sm font-medium transition',
            value === s.start ? 'bg-brand-600 text-white' : s.available ? 'bg-slate-100 text-slate-700 hover:bg-slate-200' : 'cursor-not-allowed bg-slate-50 text-slate-300 line-through',
          )}
        >
          {s.start}
        </button>
      ))}
    </div>
  )
}
