import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { CalendarOff, Clock, Pencil, Plus, Trash2 } from 'lucide-react'
import { useState, type FormEvent } from 'react'
import { api, errorMessage, validationErrors } from '../../api/client'
import { Button } from '../../components/ui/Button'
import { Alert, Badge, Card, PageHeader } from '../../components/ui/Card'
import { Field, Input, Select } from '../../components/ui/Field'
import { Modal } from '../../components/ui/Modal'
import { Spinner } from '../../components/ui/Spinner'
import { useAuth } from '../../contexts/useAuth'
import type { User } from '../../types'
import { dayNames, weekOrder } from '../training/schedule'
import { useTherapists, type TherapistRow } from './api'

const types: [string, string][] = [
  ['slt', 'Speech & Language Therapist'],
  ['ot', 'Occupational Therapist'],
  ['aba', 'ABA Therapist'],
  ['opt', 'OPT Therapist'],
  ['special_educator', 'Special Educator'],
  ['other', 'Other Specialist'],
]

/** Therapists only — trainers are separate staff (TRAINER ≠ THERAPIST). */
export default function TherapistsPage() {
  const { can } = useAuth()
  const { data, isLoading } = useTherapists()
  const [editing, setEditing] = useState<TherapistRow | 'new' | null>(null)
  const [scheduleFor, setScheduleFor] = useState<TherapistRow | null>(null)
  const [leaveFor, setLeaveFor] = useState<TherapistRow | null>(null)
  const manage = can('therapists.manage')

  return (
    <>
      <PageHeader
        title="Therapists"
        description="One-to-one clinical therapy. Working hours and leave decide which appointment slots are free."
        actions={
          manage && (
            <Button onClick={() => setEditing('new')}>
              <Plus className="size-4" /> New therapist
            </Button>
          )
        }
      />
      {isLoading ? (
        <Spinner className="text-brand-600" />
      ) : (
        <div className="grid gap-4 lg:grid-cols-2">
          {data?.map((t) => (
            <Card key={t.id} className="p-5">
              <div className="flex items-start justify-between gap-2">
                <div>
                  <p className="font-semibold text-slate-900">{t.name}</p>
                  <p className="text-sm text-slate-500">{t.designation ?? t.type_label}</p>
                </div>
                <div className="flex gap-1">
                  <Badge tone={t.status === 'active' ? 'green' : 'gray'}>{t.status}</Badge>
                  {t.login ? <Badge tone="blue">Login</Badge> : <Badge>No login</Badge>}
                </div>
              </div>
              <div className="mt-2 flex flex-wrap gap-1">
                {t.services.map((s) => (
                  <Badge key={s.id} tone="blue">
                    {s.name}
                  </Badge>
                ))}
              </div>
              <div className="mt-3 space-y-1 text-sm text-slate-600">
                {t.schedules.length === 0 ? (
                  <p className="text-amber-700">No working hours yet — cannot be booked.</p>
                ) : (
                  weekOrder
                    .filter((d) => t.schedules.some((s) => s.weekday === d))
                    .map((d) => (
                      <p key={d} className="flex items-center gap-2">
                        <Clock className="size-3.5 text-slate-400" />
                        <span className="w-10 font-medium">{dayNames[d]}</span>
                        {t.schedules
                          .filter((s) => s.weekday === d)
                          .map((s) => `${s.start_time}–${s.end_time}`)
                          .join(', ')}
                      </p>
                    ))
                )}
                {t.upcoming_leaves.map((l) => (
                  <p key={l.id} className="flex items-center gap-2 text-red-700">
                    <CalendarOff className="size-3.5" /> Leave {l.start_date} → {l.end_date} {l.reason && `(${l.reason})`}
                  </p>
                ))}
              </div>
              {manage && (
                <div className="mt-4 flex flex-wrap gap-2 border-t border-slate-100 pt-3">
                  <Button variant="secondary" className="min-h-8 px-2.5 py-1 text-xs" onClick={() => setEditing(t)}>
                    <Pencil className="size-3.5" /> Edit
                  </Button>
                  <Button variant="secondary" className="min-h-8 px-2.5 py-1 text-xs" onClick={() => setScheduleFor(t)}>
                    <Clock className="size-3.5" /> Working hours
                  </Button>
                  <Button variant="secondary" className="min-h-8 px-2.5 py-1 text-xs" onClick={() => setLeaveFor(t)}>
                    <CalendarOff className="size-3.5" /> Leave
                  </Button>
                </div>
              )}
            </Card>
          ))}
        </div>
      )}
      {editing && <TherapistForm therapist={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />}
      {scheduleFor && <ScheduleForm therapist={scheduleFor} onClose={() => setScheduleFor(null)} />}
      {leaveFor && <LeaveForm therapist={leaveFor} onClose={() => setLeaveFor(null)} />}
    </>
  )
}

function useRefresh() {
  const qc = useQueryClient()
  return () => qc.invalidateQueries({ queryKey: ['therapists'] })
}

function TherapistForm({ therapist, onClose }: { therapist: TherapistRow | null; onClose: () => void }) {
  const { user } = useAuth()
  const refresh = useRefresh()
  const { data: services } = useQuery({
    queryKey: ['bookable-services'],
    queryFn: async () => (await api.get<{ data: { id: number; name: string }[] }>('/lookups/bookable-services')).data.data,
  })
  const { data: logins } = useQuery({
    queryKey: ['users', 'therapist-role'],
    queryFn: async () => (await api.get<{ data: User[] }>('/users', { params: { role: 'therapist', per_page: 100 } })).data.data,
  })
  const [serviceIds, setServiceIds] = useState<number[]>(therapist?.services.map((s) => s.id) ?? [])
  const [errors, setErrors] = useState<Record<string, string>>({})
  const save = useMutation({
    mutationFn: (body: Record<string, unknown>) => (therapist ? api.put(`/therapists/${therapist.id}`, body) : api.post('/therapists', body)),
    onSuccess: () => (refresh(), onClose()),
    onError: (e) => setErrors(Object.keys(validationErrors(e)).length ? validationErrors(e) : { form: errorMessage(e) }),
  })

  const onSubmit = (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault()
    const f = Object.fromEntries(new FormData(e.currentTarget)) as Record<string, string>
    save.mutate({ ...f, primary_branch_id: Number(f.primary_branch_id), experience_years: Number(f.experience_years) || null, user_id: Number(f.user_id) || null, service_ids: serviceIds })
  }

  return (
    <Modal open title={therapist ? `Edit ${therapist.name}` : 'New therapist'} onClose={onClose}>
      <form onSubmit={onSubmit} className="space-y-4" noValidate>
        {errors.form && <Alert>{errors.form}</Alert>}
        <Field label="Name" htmlFor="th_name" error={errors.name}>
          <Input id="th_name" name="name" defaultValue={therapist?.name} />
        </Field>
        <div className="grid gap-3 sm:grid-cols-2">
          <Field label="Type" htmlFor="th_type">
            <Select id="th_type" name="therapist_type" defaultValue={therapist?.therapist_type ?? 'slt'}>
              {types.map(([v, l]) => (
                <option key={v} value={v}>
                  {l}
                </option>
              ))}
            </Select>
          </Field>
          <Field label="Designation" htmlFor="th_desig">
            <Input id="th_desig" name="designation" defaultValue={therapist?.designation ?? ''} />
          </Field>
          <Field label="Main branch" htmlFor="th_branch">
            <Select id="th_branch" name="primary_branch_id" defaultValue={therapist?.primary_branch?.id ?? user?.branches?.[0]?.id}>
              {user?.branches?.map((b) => (
                <option key={b.id} value={b.id}>
                  {b.name}
                </option>
              ))}
            </Select>
          </Field>
          <Field label="Status" htmlFor="th_status">
            <Select id="th_status" name="status" defaultValue={therapist?.status ?? 'active'}>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </Select>
          </Field>
          <Field label="Mobile" htmlFor="th_phone">
            <Input id="th_phone" name="phone" defaultValue={therapist?.phone ?? ''} />
          </Field>
          <Field label="Email" htmlFor="th_email" error={errors.email}>
            <Input id="th_email" name="email" defaultValue={therapist?.email ?? ''} />
          </Field>
          <Field label="Qualification" htmlFor="th_q">
            <Input id="th_q" name="qualification" defaultValue={therapist?.qualification ?? ''} />
          </Field>
          <Field label="Experience (years)" htmlFor="th_exp">
            <Input id="th_exp" name="experience_years" type="number" defaultValue={therapist?.experience_years ?? ''} />
          </Field>
          <Field label="Login account" htmlFor="th_user" hint="A user with the Therapist role" error={errors.user_id}>
            <Select id="th_user" name="user_id" defaultValue={therapist?.user_id ?? ''}>
              <option value="">No login</option>
              {logins?.map((u) => (
                <option key={u.id} value={u.id}>
                  {u.name} ({u.email ?? u.phone})
                </option>
              ))}
            </Select>
          </Field>
        </div>
        <Field label="Services provided" error={errors.service_ids}>
          <div className="grid gap-2 rounded-lg border border-slate-200 p-3 sm:grid-cols-2">
            {services?.map((s) => (
              <label key={s.id} className="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" className="size-4" checked={serviceIds.includes(s.id)} onChange={(e) => setServiceIds((l) => (e.target.checked ? [...l, s.id] : l.filter((x) => x !== s.id)))} />
                {s.name}
              </label>
            ))}
          </div>
        </Field>
        <div className="flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" loading={save.isPending}>
            Save
          </Button>
        </div>
      </form>
    </Modal>
  )
}

function ScheduleForm({ therapist, onClose }: { therapist: TherapistRow; onClose: () => void }) {
  const { user } = useAuth()
  const refresh = useRefresh()
  const [rows, setRows] = useState(
    therapist.schedules.length
      ? therapist.schedules.map((s) => ({ branch_id: s.branch_id, weekday: s.weekday, start_time: s.start_time, end_time: s.end_time, slot_minutes: s.slot_minutes }))
      : [{ branch_id: user?.branches?.[0]?.id ?? 0, weekday: 0, start_time: '15:00', end_time: '19:00', slot_minutes: 45 }],
  )
  const [error, setError] = useState<string | null>(null)
  const save = useMutation({
    mutationFn: () => api.put(`/therapists/${therapist.id}/schedule`, { schedules: rows }),
    onSuccess: () => (refresh(), onClose()),
    onError: (e) => setError(Object.values(validationErrors(e))[0] ?? errorMessage(e)),
  })
  const set = (i: number, patch: Partial<(typeof rows)[number]>) => setRows((r) => r.map((row, j) => (j === i ? { ...row, ...patch } : row)))

  return (
    <Modal open title={`Working hours — ${therapist.name}`} onClose={onClose}>
      <div className="space-y-3">
        {error && <Alert>{error}</Alert>}
        {rows.map((r, i) => (
          <div key={i} className="grid grid-cols-[1fr_1fr_1fr_auto] items-end gap-2 rounded-lg border border-slate-100 p-2 sm:grid-cols-[1.2fr_1fr_1fr_1fr_auto]">
            <Select value={r.weekday} onChange={(e) => set(i, { weekday: Number(e.target.value) })} aria-label="Day">
              {weekOrder.map((d) => (
                <option key={d} value={d}>
                  {dayNames[d]}
                </option>
              ))}
            </Select>
            <Input type="time" value={r.start_time} onChange={(e) => set(i, { start_time: e.target.value })} aria-label="From" />
            <Input type="time" value={r.end_time} onChange={(e) => set(i, { end_time: e.target.value })} aria-label="To" />
            <Select className="hidden sm:block" value={r.slot_minutes} onChange={(e) => set(i, { slot_minutes: Number(e.target.value) })} aria-label="Slot length">
              {[30, 45, 60, 90].map((m) => (
                <option key={m} value={m}>
                  {m} min
                </option>
              ))}
            </Select>
            <button type="button" onClick={() => setRows((l) => l.filter((_, j) => j !== i))} className="p-2 text-slate-400 hover:text-red-600" aria-label="Remove">
              <Trash2 className="size-4" />
            </button>
          </div>
        ))}
        <Button variant="secondary" className="w-full" onClick={() => setRows((l) => [...l, { ...(l[l.length - 1] ?? rows[0]), weekday: ((l[l.length - 1]?.weekday ?? 0) + 1) % 7 }])}>
          <Plus className="size-4" /> Add day
        </Button>
        <div className="flex justify-end gap-2 pt-1">
          <Button variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button onClick={() => save.mutate()} loading={save.isPending}>
            Save hours
          </Button>
        </div>
      </div>
    </Modal>
  )
}

function LeaveForm({ therapist, onClose }: { therapist: TherapistRow; onClose: () => void }) {
  const refresh = useRefresh()
  const [result, setResult] = useState<number | null>(null)
  const [error, setError] = useState<string | null>(null)
  const add = useMutation({
    mutationFn: async (body: Record<string, string>) => (await api.post<{ data: { appointments_to_reschedule: number } }>(`/therapists/${therapist.id}/leaves`, body)).data.data,
    onSuccess: (d) => (refresh(), setResult(d.appointments_to_reschedule)),
    onError: (e) => setError(Object.values(validationErrors(e))[0] ?? errorMessage(e)),
  })
  const remove = useMutation({ mutationFn: (id: number) => api.delete(`/therapist-leaves/${id}`), onSuccess: refresh })

  return (
    <Modal open title={`Leave — ${therapist.name}`} onClose={onClose}>
      <div className="space-y-4">
        {error && <Alert>{error}</Alert>}
        {result !== null && (
          <Alert tone={result ? 'red' : 'green'}>{result ? `${result} booked appointment(s) fall in this leave — reschedule them from Appointments.` : 'Leave added. No appointments affected.'}</Alert>
        )}
        <ul className="space-y-1 text-sm">
          {therapist.upcoming_leaves.map((l) => (
            <li key={l.id} className="flex items-center justify-between rounded bg-slate-50 px-3 py-2">
              {l.start_date} → {l.end_date} {l.reason}
              <button onClick={() => remove.mutate(l.id)} className="text-slate-400 hover:text-red-600" aria-label="Remove leave">
                <Trash2 className="size-4" />
              </button>
            </li>
          ))}
        </ul>
        <form
          className="grid gap-3 sm:grid-cols-2"
          onSubmit={(e) => {
            e.preventDefault()
            setError(null)
            add.mutate(Object.fromEntries(new FormData(e.currentTarget)) as Record<string, string>)
          }}
        >
          <Field label="From" htmlFor="lv_from">
            <Input id="lv_from" name="start_date" type="date" required />
          </Field>
          <Field label="To" htmlFor="lv_to">
            <Input id="lv_to" name="end_date" type="date" required />
          </Field>
          <div className="sm:col-span-2">
            <Field label="Reason" htmlFor="lv_reason">
              <Input id="lv_reason" name="reason" />
            </Field>
          </div>
          <div className="flex justify-end gap-2 sm:col-span-2">
            <Button type="button" variant="secondary" onClick={onClose}>
              Close
            </Button>
            <Button type="submit" loading={add.isPending}>
              Add leave
            </Button>
          </div>
        </form>
      </div>
    </Modal>
  )
}
