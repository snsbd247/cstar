import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { CalendarClock, CalendarPlus, Phone, UserPlus } from 'lucide-react'
import { useState } from 'react'
import { Link, useNavigate } from 'react-router'
import { api, errorMessage } from '../../api/client'
import { Button } from '../../components/ui/Button'
import { Alert, Badge, Card, PageHeader } from '../../components/ui/Card'
import { Spinner } from '../../components/ui/Spinner'
import { cn } from '../../utils/cn'
import { BookAppointmentModal } from '../therapy/components/BookAppointmentModal'

interface AppointmentRequest {
  id: number
  reference: string
  parent_name: string
  child_name: string
  child_age_years: number | null
  phone: string
  email: string | null
  preferred_date: string | null
  preferred_time_label: string | null
  message: string | null
  status: 'new' | 'contacted' | 'converted' | 'rejected' | 'spam'
  internal_note: string | null
  created_at: string
  branch: string | null
  service: string | null
  preferred_therapist: string | null
  handled_by: string | null
  patient: { id: number; patient_code: string; name: string } | null
  appointment_id: number | null
  service_id: number | null
  preferred_therapist_id: number | null
}

const statuses = ['new', 'contacted', 'converted', 'rejected', 'spam'] as const
const tone = { new: 'amber', contacted: 'blue', converted: 'green', rejected: 'gray', spam: 'red' } as const

/** Website appointment requests (Plan §১৬): call back → register the child → mark converted. */
export default function OnlineRequestsPage() {
  const [status, setStatus] = useState<string>('new')
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [error, setError] = useState<string | null>(null)
  const [booking, setBooking] = useState<AppointmentRequest | null>(null)
  const { data, isLoading } = useQuery({
    queryKey: ['appointment-requests', status],
    queryFn: async () =>
      (await api.get<{ data: AppointmentRequest[]; counts: Record<string, number> }>('/appointment-requests', { params: { status: status || undefined } })).data,
  })
  const update = useMutation({
    mutationFn: ({ id, ...body }: { id: number; status: string; internal_note?: string | null }) => api.put(`/appointment-requests/${id}`, body),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['appointment-requests'] }),
    onError: (e) => setError(errorMessage(e)),
  })

  const register = (r: AppointmentRequest) => {
    const params = new URLSearchParams({ request_id: String(r.id), name: r.child_name, phone: r.phone, guardian_name: r.parent_name })
    navigate(`/app/patients/new?${params}`)
  }

  return (
    <>
      <PageHeader title="Online requests" description="Appointment requests from the website. Call the family, then register the child." />

      <div className="mb-4 flex gap-1 overflow-x-auto">
        {[...statuses, ''].map((s) => (
          <button
            key={s || 'all'}
            onClick={() => setStatus(s)}
            className={cn('whitespace-nowrap rounded-full px-3.5 py-1.5 text-sm font-medium capitalize', status === s ? 'bg-brand-600 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50')}
          >
            {s || 'All'}
            {s && data?.counts?.[s] ? <span className="ml-1.5 opacity-70">{data.counts[s]}</span> : null}
          </button>
        ))}
      </div>

      {error && (
        <div className="mb-4">
          <Alert>{error}</Alert>
        </div>
      )}

      {isLoading ? (
        <Spinner className="text-brand-600" />
      ) : !data?.data.length ? (
        <Card className="p-6 text-sm text-slate-500">No {status || ''} requests.</Card>
      ) : (
        <div className="grid gap-3 lg:grid-cols-2">
          {data.data.map((r) => (
            <Card key={r.id} className="p-4">
              <div className="flex items-start justify-between gap-2">
                <div>
                  <p className="font-medium text-slate-900">
                    {r.child_name}
                    {r.child_age_years !== null && <span className="text-slate-500"> · {r.child_age_years}y</span>}
                  </p>
                  <p className="text-sm text-slate-600">Parent: {r.parent_name}</p>
                </div>
                <Badge tone={tone[r.status]}>{r.status}</Badge>
              </div>
              <a href={`tel:${r.phone}`} className="mt-2 inline-flex items-center gap-1 text-sm font-medium text-sky-brand-600">
                <Phone className="size-3.5" /> {r.phone}
              </a>
              <dl className="mt-2 grid grid-cols-[90px_1fr] gap-x-2 gap-y-0.5 text-xs text-slate-500">
                <dt>Reference</dt>
                <dd className="font-mono text-slate-700">{r.reference}</dd>
                <dt>Received</dt>
                <dd className="text-slate-700">{new Date(r.created_at).toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Dhaka' })}</dd>
                <dt>Branch</dt>
                <dd className="text-slate-700">{r.branch}</dd>
                <dt>Service</dt>
                <dd className="text-slate-700">{r.service ?? 'Not sure'}</dd>
                {r.preferred_therapist && (
                  <>
                    <dt>Therapist</dt>
                    <dd className="text-slate-700">{r.preferred_therapist}</dd>
                  </>
                )}
                {(r.preferred_date || r.preferred_time_label) && (
                  <>
                    <dt>Prefers</dt>
                    <dd className="flex items-center gap-1 text-slate-700">
                      <CalendarClock className="size-3.5" /> {[r.preferred_date, r.preferred_time_label].filter(Boolean).join(' · ')}
                    </dd>
                  </>
                )}
              </dl>
              {r.message && <p className="mt-2 rounded-lg bg-slate-50 p-2 text-sm text-slate-700">{r.message}</p>}
              {r.patient && (
                <p className="mt-2 text-sm">
                  Registered as{' '}
                  <Link to={`/app/patients/${r.patient.id}`} className="font-medium text-brand-700 underline">
                    {r.patient.name} ({r.patient.patient_code})
                  </Link>
                </p>
              )}
              {r.handled_by && <p className="mt-1 text-xs text-slate-400">Handled by {r.handled_by}</p>}

              {r.status === 'converted' && r.patient && !r.appointment_id && (
                <Button className="mt-3 min-h-8 px-2.5 py-1 text-xs" onClick={() => setBooking(r)}>
                  <CalendarPlus className="size-3.5" /> Book appointment
                </Button>
              )}
              {r.appointment_id && <p className="mt-1 text-xs font-medium text-brand-700">Appointment booked</p>}
              {r.status !== 'converted' && (
                <div className="mt-3 flex flex-wrap gap-1 border-t border-slate-100 pt-3">
                  <Button className="min-h-8 px-2.5 py-1 text-xs" onClick={() => register(r)}>
                    <UserPlus className="size-3.5" /> Register child
                  </Button>
                  {r.status === 'new' && (
                    <Button variant="secondary" className="min-h-8 px-2.5 py-1 text-xs" onClick={() => update.mutate({ id: r.id, status: 'contacted' })}>
                      Mark contacted
                    </Button>
                  )}
                  {r.status !== 'rejected' && (
                    <Button variant="ghost" className="min-h-8 px-2.5 py-1 text-xs" onClick={() => update.mutate({ id: r.id, status: 'rejected' })}>
                      Not proceeding
                    </Button>
                  )}
                  {r.status !== 'spam' && (
                    <Button variant="ghost" className="min-h-8 px-2.5 py-1 text-xs text-red-600" onClick={() => update.mutate({ id: r.id, status: 'spam' })}>
                      Spam
                    </Button>
                  )}
                </div>
              )}
            </Card>
          ))}
        </div>
      )}
      {booking?.patient && (
        <BookAppointmentModal
          patient={{ id: booking.patient.id, name: booking.patient.name }}
          serviceId={booking.service_id ?? undefined}
          therapistId={booking.preferred_therapist_id ?? undefined}
          appointmentRequestId={booking.id}
          onClose={() => setBooking(null)}
          onBooked={() => queryClient.invalidateQueries({ queryKey: ['appointment-requests'] })}
        />
      )}
    </>
  )
}
