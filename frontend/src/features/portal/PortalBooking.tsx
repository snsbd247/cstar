import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { CalendarClock, CheckCircle2 } from 'lucide-react'
import { useState } from 'react'
import { api, errorMessage, validationErrors } from '../../api/client'
import { Button } from '../../components/ui/Button'
import { Alert, Card } from '../../components/ui/Card'
import { Select } from '../../components/ui/Field'
import { Spinner } from '../../components/ui/Spinner'
import { cn } from '../../utils/cn'
import { bnDate, bnNumber, bnTime, bnWeekday } from './api'

interface Options {
  enabled: boolean
  cancel_enabled: boolean
  days_ahead: number
  notice_hours: number
  late_cancel_hours: number
  programmes: { enrollment_id: number; service: string; service_bn: string | null; therapist: string; branch: string }[]
}

const useBookingOptions = (childId: number) =>
  useQuery({ queryKey: ['portal', 'booking', childId], queryFn: async () => (await api.get<{ data: Options }>(`/portal/children/${childId}/booking`)).data.data })

const isoDay = (offset: number) => {
  const d = new Date()
  d.setDate(d.getDate() + offset)
  return d.toLocaleDateString('en-CA')
}

/** Sprint 20: a therapy family picks a free slot with their own therapist; reception confirms it. */
export function BookingCard({ childId }: { childId: number }) {
  const qc = useQueryClient()
  const { data: options } = useBookingOptions(childId)
  const [open, setOpen] = useState(false)
  const [enrollment, setEnrollment] = useState<number | null>(null)
  const [date, setDate] = useState(isoDay(1))
  const [time, setTime] = useState<string | null>(null)
  const chosen = enrollment ?? options?.programmes[0]?.enrollment_id ?? null
  const { data: slots, isFetching } = useQuery({
    queryKey: ['portal', 'booking-slots', childId, chosen, date],
    queryFn: async () => (await api.get<{ data: { closed: string | null; slots: { start: string; end: string }[] } }>(`/portal/children/${childId}/booking/slots`, { params: { enrollment_id: chosen, date } })).data.data,
    enabled: open && !!chosen,
  })
  const book = useMutation({
    mutationFn: () => api.post(`/portal/children/${childId}/booking`, { enrollment_id: chosen, date, start_time: time }),
    onSuccess: () => (setTime(null), qc.invalidateQueries({ queryKey: ['portal'] })),
  })
  if (!options?.enabled || !options.programmes.length) return null
  const days = Array.from({ length: options.days_ahead + 1 }, (_, i) => isoDay(i))

  return (
    <Card className="p-4">
      {!open ? (
        <Button variant="secondary" className="w-full" onClick={() => (setOpen(true), book.reset())}>
          <CalendarClock className="size-4" /> খালি সময়ে সেশন বুক করুন
        </Button>
      ) : (
        <div className="space-y-3">
          <h2 className="font-semibold text-slate-900">সেশন বুক করুন</h2>
          {book.isSuccess ? (
            <div className="flex items-start gap-2 rounded-xl bg-brand-50 p-3 text-sm text-brand-800">
              <CheckCircle2 className="mt-0.5 size-4 shrink-0" /> বুকিং পাঠানো হয়েছে। রিসেপশন নিশ্চিত করলে জানানো হবে।
            </div>
          ) : (
            <>
              {options.programmes.length > 1 && (
                <Select value={chosen ?? ''} onChange={(e) => (setEnrollment(Number(e.target.value)), setTime(null))} aria-label="সেবা">
                  {options.programmes.map((p) => (
                    <option key={p.enrollment_id} value={p.enrollment_id}>
                      {p.service_bn || p.service} — {p.therapist}
                    </option>
                  ))}
                </Select>
              )}
              {options.programmes.length === 1 && (
                <p className="text-sm text-slate-600">
                  {options.programmes[0].service_bn || options.programmes[0].service} — {options.programmes[0].therapist}
                </p>
              )}
              <div className="-mx-1 flex gap-1.5 overflow-x-auto px-1 pb-1">
                {days.map((d) => (
                  <button
                    key={d}
                    onClick={() => (setDate(d), setTime(null))}
                    className={cn('shrink-0 rounded-xl border px-3 py-2 text-center text-xs', date === d ? 'border-brand-500 bg-brand-50 text-brand-800' : 'border-slate-200 text-slate-600')}
                  >
                    <span className="block">{bnWeekday(d).slice(0, 3)}</span>
                    <span className="block text-sm font-semibold">{bnDate(d, { day: 'numeric', month: 'short' })}</span>
                  </button>
                ))}
              </div>
              {isFetching ? (
                <Spinner className="text-brand-600" />
              ) : slots?.slots.length ? (
                <div className="grid grid-cols-3 gap-1.5">
                  {slots.slots.map((s) => (
                    <button
                      key={s.start}
                      onClick={() => setTime(s.start)}
                      className={cn('rounded-lg border py-2 text-sm', time === s.start ? 'border-brand-500 bg-brand-600 text-white' : 'border-slate-200 text-slate-700')}
                    >
                      {bnTime(s.start)}
                    </button>
                  ))}
                </div>
              ) : (
                <p className="text-sm text-slate-500">এই দিনে খালি সময় নেই। অন্য দিন দেখুন।</p>
              )}
              {book.isError && <Alert>{Object.values(validationErrors(book.error))[0] ?? errorMessage(book.error)}</Alert>}
              <Button className="w-full" disabled={!time} loading={book.isPending} onClick={() => book.mutate()}>
                {time ? `${bnWeekday(date)}, ${bnTime(time)}-এ বুক করুন` : 'একটি সময় বেছে নিন'}
              </Button>
              <p className="text-xs text-slate-500">
                আগামী {bnNumber(options.days_ahead)} দিনের মধ্যে, অন্তত {bnNumber(options.notice_hours)} ঘণ্টা আগে বুক করা যায়।
              </p>
            </>
          )}
          <button type="button" onClick={() => setOpen(false)} className="w-full text-center text-sm text-slate-500">
            বন্ধ করুন
          </button>
        </div>
      )}
    </Card>
  )
}

/** "বাতিল" on an upcoming appointment; warns when it is inside the late-cancellation window. */
export function CancelAppointment({ childId, appointment }: { childId: number; appointment: { id: number; date: string; start_time: string; status: string } }) {
  const qc = useQueryClient()
  const { data: options } = useBookingOptions(childId)
  const cancel = useMutation({
    mutationFn: () => api.post(`/portal/children/${childId}/appointments/${appointment.id}/cancel`, {}),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['portal'] }),
  })
  if (!options?.cancel_enabled || !['pending', 'confirmed'].includes(appointment.status)) return null
  const ask = () => {
    const late = (new Date(`${appointment.date}T${appointment.start_time}`).getTime() - Date.now()) / 36e5 < options.late_cancel_hours
    const warning = late ? `\n\n${bnNumber(options.late_cancel_hours)} ঘণ্টার কম আগে বাতিল করলে প্যাকেজ থেকে সেশন কাটা যেতে পারে।` : ''
    if (confirm(`অ্যাপয়েন্টমেন্টটি বাতিল করবেন?${warning}`)) cancel.mutate()
  }

  return (
    <button onClick={ask} disabled={cancel.isPending} className="text-xs text-red-600 hover:underline">
      {cancel.isError ? errorMessage(cancel.error) : 'বাতিল'}
    </button>
  )
}
