import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '../../api/client'
import type { Paginated } from '../../types'

export type AppointmentStatus = 'pending' | 'confirmed' | 'checked_in' | 'completed' | 'cancelled' | 'no_show' | 'rescheduled'

export interface Appointment {
  id: number
  appointment_code: string
  date: string
  start_time: string
  end_time: string
  type: 'assessment' | 'therapy' | 'consultation' | 'follow_up'
  status: AppointmentStatus
  source: string
  notes: string | null
  cancel_reason: string | null
  is_late_cancellation: boolean
  enrollment_id: number | null
  patient?: { id: number; name: string; patient_code: string; phone: string; has_photo: boolean }
  service?: { id: number; name: string }
  therapist?: { id: number; name: string }
  branch?: { id: number; name: string }
  session?: { id: number; status: 'draft' | 'final' } | null
  checked_in_at?: string | null
}

export interface TherapySession {
  id: number
  appointment_id: number
  enrollment_id: number | null
  date: string
  start_time: string | null
  end_time: string | null
  duration_min: number | null
  goals_worked: string | null
  observation: string | null
  patient_response: string | null
  progress: string | null
  challenges: string | null
  home_practice: string | null
  next_session_plan: string | null
  therapist_notes: string | null
  parent_summary: string | null
  status: 'draft' | 'final'
  updated_at?: string
  patient?: { id: number; name: string; patient_code: string }
  therapist?: { id: number; name: string }
  service?: { id: number; name: string }
  activities?: { id: number; name: string }[]
  goal_scores?: { goal_id: number; score: number; note: string | null }[]
}

export interface TherapistRow {
  id: number
  name: string
  designation: string | null
  phone: string | null
  email: string | null
  qualification: string | null
  experience_years: number | null
  status: 'active' | 'inactive'
  is_supervisor?: boolean
  employee_code: string | null
  user_id: number | null
  therapist_type: string
  type_label: string
  primary_branch: { id: number; name: string } | null
  services: { id: number; name: string }[]
  login: string | null
  schedules: { branch_id: number; branch: string | null; weekday: number; day: string; start_time: string; end_time: string; slot_minutes: number }[]
  upcoming_leaves: { id: number; start_date: string; end_date: string; reason: string | null }[]
}

export const statusStyle: Record<AppointmentStatus, { label: string; className: string }> = {
  pending: { label: 'Pending', className: 'bg-amber-50 text-amber-800 ring-amber-600/20' },
  confirmed: { label: 'Confirmed', className: 'bg-sky-brand-50 text-sky-brand-700 ring-sky-brand-600/20' },
  checked_in: { label: 'Checked in', className: 'bg-violet-50 text-violet-700 ring-violet-600/20' },
  completed: { label: 'Completed', className: 'bg-brand-50 text-brand-700 ring-brand-600/20' },
  cancelled: { label: 'Cancelled', className: 'bg-slate-100 text-slate-500 ring-slate-500/20' },
  no_show: { label: 'No show', className: 'bg-red-50 text-red-700 ring-red-600/20' },
  rescheduled: { label: 'Rescheduled', className: 'bg-slate-100 text-slate-500 ring-slate-500/20' },
}

export function useTherapists() {
  return useQuery({ queryKey: ['therapists'], queryFn: async () => (await api.get<{ data: TherapistRow[] }>('/therapists')).data.data })
}

function useInvalidate() {
  const qc = useQueryClient()
  return () => {
    for (const key of ['appointments', 'therapist-today', 'therapy-sessions', 'availability', 'appointment-session', 'patient', 'timeline', 'therapists']) {
      qc.invalidateQueries({ queryKey: [key] })
    }
  }
}

export function useAppointments(params: Record<string, string | number | boolean | undefined>, enabled = true) {
  return useQuery({
    queryKey: ['appointments', params],
    queryFn: async () => (await api.get<Paginated<Appointment>>('/appointments', { params })).data.data,
    enabled,
  })
}

export function useAvailability(params: { therapist_id?: number; branch_id?: number; date?: string; service_id?: number }) {
  return useQuery({
    queryKey: ['availability', params],
    queryFn: async () => (await api.get<{ data: { closed: string | null; slots: { start: string; end: string; available: boolean }[] } }>('/availability', { params })).data.data,
    enabled: !!params.therapist_id && !!params.branch_id && !!params.date,
  })
}

export function useAppointmentMutations() {
  const invalidate = useInvalidate()
  return {
    book: useMutation({ mutationFn: async (body: Record<string, unknown>) => (await api.post<{ data: Appointment }>('/appointments', body)).data.data, onSuccess: invalidate }),
    action: useMutation({
      mutationFn: ({ id, action, reason }: { id: number; action: string; reason?: string }) => api.post(`/appointments/${id}/${action}`, { reason }),
      onSuccess: invalidate,
    }),
    reschedule: useMutation({
      mutationFn: ({ id, ...body }: { id: number; date: string; start_time: string }) => api.post(`/appointments/${id}/reschedule`, body),
      onSuccess: invalidate,
    }),
  }
}

export interface SessionBundle {
  appointment: Appointment
  session: TherapySession | null
  previous: { date: string; next_session_plan: string | null; home_practice: string | null; practice_feedback?: PracticeLog[] } | null
  can_write: boolean
}

export function useAppointmentSession(appointmentId: number | undefined) {
  return useQuery({
    queryKey: ['appointment-session', appointmentId],
    queryFn: async () => (await api.get<{ data: SessionBundle }>(`/appointments/${appointmentId}/session`)).data.data,
    enabled: !!appointmentId,
  })
}

export function useSaveSession(appointmentId: number) {
  const invalidate = useInvalidate()
  return useMutation({
    mutationFn: async (body: Record<string, unknown>) => (await api.post<{ data: TherapySession }>(`/appointments/${appointmentId}/session`, body)).data.data,
    onSuccess: invalidate,
  })
}

export function useTherapySessions(params: { patient_id?: number; therapist_id?: number; status?: string; page?: number }) {
  return useQuery({
    queryKey: ['therapy-sessions', params],
    queryFn: async () => (await api.get<Paginated<TherapySession>>('/therapy-sessions', { params })).data,
  })
}

export interface TherapistToday {
  date: string
  therapist: { id: number; name: string }
  appointments: Appointment[]
  notes_pending: Appointment[]
  totals: { appointments: number; checked_in: number; completed: number; notes_pending: number }
}

export function useTherapistToday() {
  return useQuery({ queryKey: ['therapist-today'], queryFn: async () => (await api.get<{ data: TherapistToday }>('/therapist/today')).data.data })
}

export function useMyTherapyPatients() {
  return useQuery({
    queryKey: ['therapist-patients'],
    queryFn: async () =>
      (await api.get<{ data: { enrollment_id: number; status: string; service: { id: number; name: string }; patient: { id: number; name: string; patient_code: string; age: string; has_photo: boolean } }[] }>('/therapist/patients')).data.data,
  })
}

export function useEnrollmentSlots(enrollmentId: number) {
  const qc = useQueryClient()
  const invalidate = useInvalidate()
  return {
    slots: useQuery({
      queryKey: ['slots', enrollmentId],
      queryFn: async () => (await api.get<{ data: { weekday: number; start_time: string }[] }>(`/enrollments/${enrollmentId}/slots`)).data.data,
    }),
    save: useMutation({
      mutationFn: (slots: { weekday: number; start_time: string }[]) => api.put(`/enrollments/${enrollmentId}/slots`, { slots }),
      onSuccess: () => qc.invalidateQueries({ queryKey: ['slots', enrollmentId] }),
    }),
    generate: useMutation({
      mutationFn: async (weeks: number) => (await api.post<{ data: { created: number; skipped: string[] } }>(`/enrollments/${enrollmentId}/generate-appointments`, { weeks })).data.data,
      onSuccess: invalidate,
    }),
  }
}

export function useTherapyActivityTypes() {
  return useQuery({
    queryKey: ['activity-types', 'therapy'],
    queryFn: async () => (await api.get<{ data: { id: number; name: string }[] }>('/lookups/activity-types', { params: { for: 'therapy' } })).data.data,
    staleTime: Infinity,
  })
}

/** Sprint 20: one day of the family's home-practice feedback from the portal. */
export interface PracticeLog {
  date: string
  status: 'done' | 'partly' | 'not_done'
  comment: string | null
}
