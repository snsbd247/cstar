import { CalendarPlus, Repeat, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { Button } from '../../../components/ui/Button'
import { Alert, Card } from '../../../components/ui/Card'
import { Input, Select } from '../../../components/ui/Field'
import { useAuth } from '../../../contexts/useAuth'
import { todayISO } from '../../../utils/format'
import { useAppointments, useEnrollmentSlots, useTherapySessions } from '../../therapy/api'
import { AppointmentActions, StatusPill } from '../../therapy/components/AppointmentActions'
import { BookAppointmentModal } from '../../therapy/components/BookAppointmentModal'
import { PlanPanel } from '../../training/components/PlanPanel'
import { dayNames, weekOrder } from '../../training/schedule'
import type { Enrollment, PatientDetail } from '../types'

/** Therapy view of a child: per therapy enrollment the plan and weekly slots, plus appointments and session notes. */
export function TherapyTab({ patient }: { patient: PatientDetail }) {
  const { can } = useAuth()
  const therapy = patient.enrollments.filter((e) => e.type === 'therapy' && ['active', 'on_hold', 'pending'].includes(e.status))
  const [today] = useState(todayISO)
  const { data: upcoming } = useAppointments({ patient_id: patient.id, from: today })
  const { data: sessions } = useTherapySessions({ patient_id: patient.id })
  const [booking, setBooking] = useState(false)

  return (
    <div className="space-y-5">
      {can('appointments.manage') && (
        <div className="flex justify-end">
          <Button onClick={() => setBooking(true)}>
            <CalendarPlus className="size-4" /> New appointment
          </Button>
        </div>
      )}

      {therapy.length === 0 && <Card className="p-5 text-sm text-slate-500">No therapy enrollment. An assessment can still be booked as an appointment.</Card>}

      <div className="grid gap-5 lg:grid-cols-2">
        {therapy.map((e) => (
          <div key={e.id} className="space-y-3">
            <h3 className="font-semibold text-slate-900">
              {e.therapy?.service.name} <span className="font-normal text-slate-500">· {e.therapy?.therapist.name}</span>
            </h3>
            <WeeklySlots enrollment={e} canEdit={can('enrollments.manage')} />
            <PlanPanel enrollmentId={e.id} canEdit={can('plans.write')} title="Therapy plan" />
          </div>
        ))}
      </div>

      <div className="grid gap-5 lg:grid-cols-2">
        <Card className="h-fit p-5">
          <h3 className="font-semibold text-slate-900">Upcoming appointments</h3>
          <ul className="mt-3 divide-y divide-slate-100">
            {!upcoming?.filter((a) => !['cancelled', 'rescheduled'].includes(a.status)).length && <li className="py-2 text-sm text-slate-500">None booked.</li>}
            {upcoming
              ?.filter((a) => !['cancelled', 'rescheduled'].includes(a.status))
              .slice(0, 12)
              .map((a) => (
                <li key={a.id} className="py-2.5">
                  <div className="flex items-center justify-between gap-2">
                    <span className="text-sm text-slate-800">
                      <b>{new Date(`${a.date}T00:00`).toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' })}</b> {a.start_time} · {a.service?.name} ·{' '}
                      {a.therapist?.name}
                    </span>
                    <StatusPill status={a.status} />
                  </div>
                  {can('appointments.manage') && (
                    <div className="mt-1.5">
                      <AppointmentActions appointment={a} compact />
                    </div>
                  )}
                </li>
              ))}
          </ul>
        </Card>

        <Card className="h-fit p-5">
          <h3 className="font-semibold text-slate-900">Therapy sessions</h3>
          {!can('therapy_sessions.view') ? (
            <p className="mt-2 text-sm text-slate-500">Session notes are visible to clinical staff only.</p>
          ) : (
            <ul className="mt-3 space-y-4">
              {sessions?.data.length === 0 && <li className="text-sm text-slate-500">No sessions yet.</li>}
              {sessions?.data.map((s) => (
                <li key={s.id} className="border-l-2 border-sky-brand-200 pl-3">
                  <p className="text-xs text-slate-500">
                    {s.date} · {s.service?.name} · {s.therapist?.name} · {s.status}
                  </p>
                  {s.observation && <p className="text-sm text-slate-800">{s.observation}</p>}
                  {s.progress && <p className="text-sm text-slate-600">Progress: {s.progress}</p>}
                  {s.home_practice && <p className="text-sm text-slate-600">Home practice: {s.home_practice}</p>}
                  {s.parent_summary && <p className="mt-1 rounded bg-brand-50 px-2 py-1 text-xs text-brand-800">For parents: {s.parent_summary}</p>}
                </li>
              ))}
            </ul>
          )}
        </Card>
      </div>

      {booking && <BookAppointmentModal patient={{ id: patient.id, name: patient.name }} onClose={() => setBooking(false)} />}
    </div>
  )
}

/** Regular weekly times of a therapy enrollment and a button to book the coming weeks from them. */
function WeeklySlots({ enrollment, canEdit }: { enrollment: Enrollment; canEdit: boolean }) {
  const { slots, save, generate } = useEnrollmentSlots(enrollment.id)
  const [draft, setDraft] = useState<{ weekday: number; start_time: string }[] | null>(null)
  const [message, setMessage] = useState<string | null>(null)
  const rows = draft ?? slots.data ?? []

  return (
    <Card className="p-4">
      <div className="flex items-center justify-between">
        <p className="flex items-center gap-2 text-sm font-semibold text-slate-900">
          <Repeat className="size-4 text-sky-brand-600" /> Weekly slots
        </p>
        {canEdit && !draft && (
          <Button variant="ghost" className="min-h-8 px-2 text-xs" onClick={() => setDraft(slots.data ?? [])}>
            Edit
          </Button>
        )}
      </div>
      {message && <p className="mt-2 rounded bg-brand-50 px-2 py-1 text-xs text-brand-800">{message}</p>}
      {!draft ? (
        <p className="mt-1 text-sm text-slate-600">
          {rows.length ? weekOrder.filter((d) => rows.some((r) => r.weekday === d)).map((d) => `${dayNames[d]} ${rows.find((r) => r.weekday === d)?.start_time}`).join(' · ') : 'No regular slot set.'}
        </p>
      ) : (
        <div className="mt-2 space-y-2">
          {draft.map((r, i) => (
            <div key={i} className="flex gap-2">
              <Select value={r.weekday} onChange={(e) => setDraft((d) => d!.map((x, j) => (j === i ? { ...x, weekday: Number(e.target.value) } : x)))} aria-label="Day">
                {weekOrder.map((d) => (
                  <option key={d} value={d}>
                    {dayNames[d]}
                  </option>
                ))}
              </Select>
              <Input type="time" value={r.start_time} onChange={(e) => setDraft((d) => d!.map((x, j) => (j === i ? { ...x, start_time: e.target.value } : x)))} aria-label="Time" />
              <button onClick={() => setDraft((d) => d!.filter((_, j) => j !== i))} className="p-2 text-slate-400 hover:text-red-600" aria-label="Remove slot">
                <Trash2 className="size-4" />
              </button>
            </div>
          ))}
          {save.error && <Alert>Could not save the slots.</Alert>}
          <div className="flex flex-wrap gap-2">
            <Button variant="secondary" className="min-h-8 px-2.5 py-1 text-xs" onClick={() => setDraft((d) => [...d!, { weekday: 0, start_time: '16:00' }])}>
              Add day
            </Button>
            <Button className="min-h-8 px-2.5 py-1 text-xs" loading={save.isPending} onClick={async () => (await save.mutateAsync(draft), setDraft(null))}>
              Save slots
            </Button>
            <Button variant="ghost" className="min-h-8 px-2.5 py-1 text-xs" onClick={() => setDraft(null)}>
              Cancel
            </Button>
          </div>
        </div>
      )}
      {canEdit && !draft && rows.length > 0 && enrollment.status === 'active' && (
        <Button
          variant="secondary"
          className="mt-3 min-h-8 px-2.5 py-1 text-xs"
          loading={generate.isPending}
          onClick={async () => {
            const r = await generate.mutateAsync(4)
            setMessage(`${r.created} appointment(s) booked for the next 4 weeks.${r.skipped.length ? ` Skipped: ${r.skipped.join('; ')}` : ''}`)
          }}
        >
          Book next 4 weeks
        </Button>
      )}
    </Card>
  )
}
