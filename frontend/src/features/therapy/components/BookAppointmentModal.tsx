import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { api, errorMessage, validationErrors } from '../../../api/client'
import { Button } from '../../../components/ui/Button'
import { Alert } from '../../../components/ui/Card'
import { Field, Input, Select, Textarea } from '../../../components/ui/Field'
import { Modal } from '../../../components/ui/Modal'
import { useAuth } from '../../../contexts/useAuth'
import { todayISO } from '../../../utils/format'
import { usePatients } from '../../patients/api'
import { useAppointmentMutations, useAvailability, useTherapists } from '../api'
import { SlotGrid } from './AppointmentActions'

interface Props {
  onClose: () => void
  onBooked?: () => void
  patient?: { id: number; name: string }
  serviceId?: number
  therapistId?: number
  appointmentRequestId?: number
}

/**
 * Plan §১৭ booking: Branch → Service → Therapist → Date → Free time → Confirm.
 * Only therapists who provide the service are offered; only free slots can be picked.
 */
export function BookAppointmentModal({ onClose, onBooked, patient: fixedPatient, serviceId, therapistId, appointmentRequestId }: Props) {
  const { user } = useAuth()
  const { book } = useAppointmentMutations()
  const { data: therapists } = useTherapists()
  const { data: services } = useQuery({
    queryKey: ['bookable-services'],
    queryFn: async () => (await api.get<{ data: { id: number; name: string }[] }>('/lookups/bookable-services')).data.data,
    staleTime: Infinity,
  })

  const [search, setSearch] = useState('')
  const { data: found } = usePatients({ search: search.length >= 2 ? search : undefined })
  const [patient, setPatient] = useState(fixedPatient ?? null)
  const [branchId, setBranchId] = useState(user?.branches?.[0]?.id)
  const [service, setService] = useState<number | undefined>(serviceId)
  const [therapist, setTherapist] = useState<number | undefined>(therapistId)
  const [date, setDate] = useState(todayISO())
  const [time, setTime] = useState<string | null>(null)
  const [notes, setNotes] = useState('')
  const [error, setError] = useState<string | null>(null)

  const offered = therapists?.filter((t) => t.status === 'active' && (!service || t.services.some((s) => s.id === service))) ?? []
  const { data: availability, isLoading } = useAvailability({ therapist_id: therapist, branch_id: branchId, date, service_id: service })

  const submit = async () => {
    setError(null)
    try {
      await book.mutateAsync({
        patient_id: patient!.id, service_id: service, therapist_id: therapist, branch_id: branchId, date, start_time: time,
        notes: notes || null, appointment_request_id: appointmentRequestId,
      })
      onBooked?.()
      onClose()
    } catch (e) {
      setError(Object.values(validationErrors(e))[0] ?? errorMessage(e))
    }
  }

  return (
    <Modal open title="New appointment" onClose={onClose}>
      <div className="space-y-4">
        {error && <Alert>{error}</Alert>}

        {patient ? (
          <div className="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm">
            <span className="font-medium text-slate-900">{patient.name}</span>
            {!fixedPatient && (
              <button className="text-sky-brand-600" onClick={() => setPatient(null)}>
                Change
              </button>
            )}
          </div>
        ) : (
          <Field label="Child" htmlFor="ap_search" hint="Search by name, patient ID or mobile">
            <Input id="ap_search" value={search} onChange={(e) => setSearch(e.target.value)} autoFocus />
            {search.length >= 2 && (
              <ul className="mt-1 max-h-48 overflow-y-auto rounded-lg border border-slate-200">
                {found?.data.slice(0, 6).map((p) => (
                  <li key={p.id}>
                    <button className="w-full px-3 py-2 text-left text-sm hover:bg-slate-50" onClick={() => setPatient({ id: p.id, name: p.name })}>
                      {p.name} <span className="text-slate-500">· {p.patient_code}</span>
                    </button>
                  </li>
                ))}
              </ul>
            )}
          </Field>
        )}

        <div className="grid gap-3 sm:grid-cols-2">
          {(user?.branches?.length ?? 0) > 1 && (
            <Field label="Branch" htmlFor="ap_branch">
              <Select id="ap_branch" value={branchId} onChange={(e) => (setBranchId(Number(e.target.value)), setTime(null))}>
                {user?.branches?.map((b) => (
                  <option key={b.id} value={b.id}>
                    {b.name}
                  </option>
                ))}
              </Select>
            </Field>
          )}
          <Field label="Service" htmlFor="ap_service">
            <Select id="ap_service" value={service ?? ''} onChange={(e) => (setService(Number(e.target.value) || undefined), setTherapist(undefined), setTime(null))}>
              <option value="">Select…</option>
              {services?.map((s) => (
                <option key={s.id} value={s.id}>
                  {s.name}
                </option>
              ))}
            </Select>
          </Field>
          <Field label="Therapist" htmlFor="ap_therapist">
            <Select id="ap_therapist" value={therapist ?? ''} disabled={!service} onChange={(e) => (setTherapist(Number(e.target.value) || undefined), setTime(null))}>
              <option value="">{service ? 'Select…' : 'Choose a service first'}</option>
              {offered.map((t) => (
                <option key={t.id} value={t.id}>
                  {t.name}
                </option>
              ))}
            </Select>
          </Field>
          <Field label="Date" htmlFor="ap_date">
            <Input id="ap_date" type="date" min={todayISO()} value={date} onChange={(e) => (setDate(e.target.value), setTime(null))} />
          </Field>
        </div>

        {therapist && <SlotGrid loading={isLoading} closed={availability?.closed} slots={availability?.slots} value={time} onChange={setTime} />}

        <Field label="Note for the therapist" htmlFor="ap_notes">
          <Textarea id="ap_notes" rows={2} value={notes} onChange={(e) => setNotes(e.target.value)} />
        </Field>

        <div className="flex justify-end gap-2">
          <Button variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button disabled={!patient || !service || !therapist || !time} loading={book.isPending} onClick={submit}>
            Book appointment
          </Button>
        </div>
      </div>
    </Modal>
  )
}
