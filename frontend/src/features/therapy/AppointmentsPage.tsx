import { ChevronLeft, ChevronRight, Plus } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router'
import { Button } from '../../components/ui/Button'
import { Card, PageHeader } from '../../components/ui/Card'
import { Input, Select } from '../../components/ui/Field'
import { Spinner } from '../../components/ui/Spinner'
import { useAuth } from '../../contexts/useAuth'
import { todayISO } from '../../utils/format'
import { useAppointments, useTherapists } from './api'
import { AppointmentActions, StatusPill } from './components/AppointmentActions'
import { BookAppointmentModal } from './components/BookAppointmentModal'

function shift(date: string, days: number) {
  const d = new Date(`${date}T00:00:00`)
  d.setDate(d.getDate() + days)
  return d.toLocaleDateString('en-CA')
}

/** Day board for the front desk: every therapist's appointments for one date. */
export default function AppointmentsPage() {
  const { can } = useAuth()
  const [date, setDate] = useState(todayISO())
  const [therapistId, setTherapistId] = useState<number | undefined>()
  const [booking, setBooking] = useState(false)
  const { data: therapists } = useTherapists()
  const { data: appointments, isLoading } = useAppointments({ date, therapist_id: therapistId })
  const columns = (therapists ?? []).filter((t) => t.status === 'active' && (!therapistId || t.id === therapistId))

  return (
    <>
      <PageHeader
        title="Appointments"
        description="Therapy appointments by therapist. Students' class attendance is separate."
        actions={
          can('appointments.manage') && (
            <Button onClick={() => setBooking(true)}>
              <Plus className="size-4" /> New appointment
            </Button>
          )
        }
      />
      <Card className="mb-4 flex flex-wrap items-center gap-2 p-3">
        <Button variant="secondary" onClick={() => setDate(shift(date, -1))} aria-label="Previous day">
          <ChevronLeft className="size-4" />
        </Button>
        <Input type="date" value={date} onChange={(e) => setDate(e.target.value)} className="w-44" aria-label="Date" />
        <Button variant="secondary" onClick={() => setDate(shift(date, 1))} aria-label="Next day">
          <ChevronRight className="size-4" />
        </Button>
        <Button variant="ghost" onClick={() => setDate(todayISO())}>
          Today
        </Button>
        <Select value={therapistId ?? ''} onChange={(e) => setTherapistId(Number(e.target.value) || undefined)} className="ml-auto sm:w-64" aria-label="Therapist">
          <option value="">All therapists</option>
          {therapists?.map((t) => (
            <option key={t.id} value={t.id}>
              {t.name}
            </option>
          ))}
        </Select>
      </Card>

      {isLoading ? (
        <Spinner className="text-brand-600" />
      ) : (
        <div className="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
          {columns.map((t) => {
            const list = appointments?.filter((a) => a.therapist?.id === t.id) ?? []
            return (
              <Card key={t.id} className="overflow-hidden">
                <div className="border-b border-slate-100 px-4 py-3">
                  <p className="font-semibold text-slate-900">{t.name}</p>
                  <p className="text-xs text-slate-500">{list.filter((a) => !['cancelled', 'rescheduled'].includes(a.status)).length} appointments</p>
                </div>
                {list.length === 0 ? (
                  <p className="px-4 py-4 text-sm text-slate-400">Free day.</p>
                ) : (
                  <ul className="divide-y divide-slate-100">
                    {list.map((a) => (
                      <li key={a.id} className={['cancelled', 'rescheduled'].includes(a.status) ? 'px-4 py-3 opacity-50' : 'px-4 py-3'}>
                        <div className="flex items-start justify-between gap-2">
                          <div>
                            <p className="text-sm font-semibold text-slate-900">
                              {a.start_time}–{a.end_time}
                            </p>
                            <Link to={`/app/patients/${a.patient?.id}?tab=therapy`} className="text-sm text-slate-800 hover:text-brand-700">
                              {a.patient?.name}
                            </Link>
                            <p className="text-xs text-slate-500">
                              {a.service?.name} · {a.type}
                              {a.source === 'recurring' && ' · weekly'}
                            </p>
                          </div>
                          <StatusPill status={a.status} />
                        </div>
                        {a.cancel_reason && <p className="mt-1 text-xs text-slate-500">Reason: {a.cancel_reason}{a.is_late_cancellation && ' (late)'}</p>}
                        {can('appointments.manage') && (
                          <div className="mt-2">
                            <AppointmentActions appointment={a} compact />
                          </div>
                        )}
                      </li>
                    ))}
                  </ul>
                )}
              </Card>
            )
          })}
        </div>
      )}
      {booking && <BookAppointmentModal onClose={() => setBooking(false)} />}
    </>
  )
}
