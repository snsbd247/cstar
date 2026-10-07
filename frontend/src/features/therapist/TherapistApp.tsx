import { CalendarOff, ClipboardList, NotebookPen } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router'
import { Alert, Card } from '../../components/ui/Card'
import { Spinner } from '../../components/ui/Spinner'
import { useAuth } from '../../contexts/useAuth'
import { longDate } from '../../utils/format'
import { useAppointmentMutations, useTherapistToday, type Appointment } from '../therapy/api'
import { StatusPill } from '../therapy/components/AppointmentActions'
import { CheckInCard } from '../staff/StaffAttendance'

/** Plan §১৪/§৩৭: Login → Today's appointments → Patient → Session note → Save. */
export function TherapistToday() {
  const { user } = useAuth()
  const [today] = useState(() => longDate(new Date()))
  const { data, isLoading, error } = useTherapistToday()

  return (
    <div className="space-y-4">
      <div>
        <p className="text-sm text-slate-500">{today}</p>
        <h1 className="text-xl font-semibold text-slate-900">Good day, {data?.therapist.name ?? user?.name}</h1>
      </div>
      <CheckInCard />
      {error && <Alert>This login is not linked to a therapist profile yet. Ask the branch admin.</Alert>}
      {isLoading && <Spinner className="text-brand-600" />}

      {data && (
        <>
          <div className="grid grid-cols-4 gap-2">
            {[
              ['Today', data.totals.appointments, 'text-slate-900'],
              ['Checked in', data.totals.checked_in, 'text-violet-700'],
              ['Done', data.totals.completed, 'text-brand-700'],
              ['Notes due', data.totals.notes_pending, 'text-amber-600'],
            ].map(([label, value, color]) => (
              <Card key={label as string} className="p-3 text-center">
                <p className={`text-2xl font-semibold ${color}`}>{value}</p>
                <p className="text-[11px] text-slate-500">{label}</p>
              </Card>
            ))}
          </div>

          <h2 className="pt-2 font-semibold text-slate-900">Today's appointments</h2>
          {data.appointments.length === 0 ? (
            <Card className="flex items-center gap-3 p-5 text-sm text-slate-500">
              <CalendarOff className="size-5" /> No appointments today.
            </Card>
          ) : (
            <div className="space-y-2">
              {data.appointments.map((a) => (
                <AppointmentRow key={a.id} appointment={a} />
              ))}
            </div>
          )}

          {data.notes_pending.length > 0 && (
            <>
              <h2 className="pt-2 font-semibold text-slate-900">Notes still to write</h2>
              <div className="space-y-2">
                {data.notes_pending.map((a) => (
                  <AppointmentRow key={a.id} appointment={a} showDate />
                ))}
              </div>
            </>
          )}
        </>
      )}
    </div>
  )
}

export function AppointmentRow({ appointment: a, showDate }: { appointment: Appointment; showDate?: boolean }) {
  const { action } = useAppointmentMutations()
  const done = a.session?.status === 'final'
  const inactive = ['cancelled', 'no_show', 'rescheduled'].includes(a.status)

  return (
    <Card className={inactive ? 'p-4 opacity-60' : 'p-4'}>
      <div className="flex items-start justify-between gap-2">
        <div>
          <p className="text-sm font-semibold text-slate-900">
            {showDate && `${a.date} · `}
            {a.start_time}–{a.end_time}
          </p>
          <p className="font-medium text-slate-800">{a.patient?.name}</p>
          <p className="text-xs text-slate-500">
            {a.service?.name} · {a.type}
          </p>
        </div>
        <StatusPill status={a.status} />
      </div>
      {!inactive && (
        <div className="mt-3 grid grid-cols-2 gap-2">
          {a.type === 'assessment' ? (
            <Link
              to={`/therapist/assessments/new?patient=${a.patient?.id}&appointment=${a.id}`}
              className={`flex min-h-11 items-center justify-center gap-2 rounded-lg text-sm font-semibold ${a.status === 'completed' ? 'border border-slate-300 text-slate-700' : 'bg-sky-brand-600 text-white'}`}
            >
              <ClipboardList className="size-4" /> {a.status === 'completed' ? 'View assessment' : 'Write assessment'}
            </Link>
          ) : (
            <Link
              to={`/therapist/session/${a.id}`}
              className={`flex min-h-11 items-center justify-center gap-2 rounded-lg text-sm font-semibold ${done ? 'border border-slate-300 text-slate-700' : 'bg-brand-600 text-white'}`}
            >
              <NotebookPen className="size-4" /> {done ? 'View note' : a.session ? 'Continue note' : 'Start session'}
            </Link>
          )}
          {['pending', 'confirmed'].includes(a.status) && (
            <button
              onClick={() => confirm(`Mark ${a.patient?.name} as no-show?`) && action.mutate({ id: a.id, action: 'no-show' })}
              className="min-h-11 rounded-lg border border-slate-300 text-sm font-medium text-slate-600"
            >
              No show
            </button>
          )}
        </div>
      )}
    </Card>
  )
}
