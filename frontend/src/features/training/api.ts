import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '../../api/client'
import type { Paginated } from '../../types'
import type { AttendanceStatus, AttendanceSummary, IndividualPlan, RosterDay, TrainingClass, TrainingRecord } from './types'

export function useClasses(enabled = true) {
  return useQuery({ queryKey: ['classes'], queryFn: async () => (await api.get<{ data: TrainingClass[] }>('/classes')).data.data, enabled })
}

export function useClass(id: number | undefined) {
  return useQuery({ queryKey: ['class', id], queryFn: async () => (await api.get<{ data: TrainingClass }>(`/classes/${id}`)).data.data, enabled: !!id })
}

export function useSaveClass() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, ...body }: Record<string, unknown> & { id?: number }) =>
      (id ? await api.put(`/classes/${id}`, body) : await api.post('/classes', body)).data,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['classes'] })
      qc.invalidateQueries({ queryKey: ['class'] })
    },
  })
}

export function useRoster(classId: number | undefined) {
  return useQuery({
    queryKey: ['roster', classId],
    queryFn: async () =>
      (await api.get<{ data: { enrollment_id: number; status: string; start_date: string; trainer: { id: number; name: string } | null; patient: { id: number; name: string; patient_code: string; has_photo: boolean } }[] }>(`/classes/${classId}/roster`)).data.data,
    enabled: !!classId,
  })
}

export function useAttendanceDay(classId: number | undefined, date: string) {
  return useQuery({
    queryKey: ['attendance', classId, date],
    queryFn: async () => (await api.get<{ data: RosterDay }>(`/classes/${classId}/attendance`, { params: { date } })).data.data,
    enabled: !!classId,
  })
}

export function useMarkAttendance(classId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (body: { date: string; entries: { enrollment_id: number; status: AttendanceStatus; arrival_time?: string | null }[] }) =>
      api.post(`/classes/${classId}/attendance`, body),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['attendance', classId] })
      qc.invalidateQueries({ queryKey: ['records-day', classId] })
      qc.invalidateQueries({ queryKey: ['trainer-today'] })
      qc.invalidateQueries({ queryKey: ['class-month', classId] })
    },
  })
}

export function useClassMonth(classId: number | undefined, month: string) {
  return useQuery({
    queryKey: ['class-month', classId, month],
    queryFn: async () =>
      (await api.get<{ data: { month: string; dates: string[]; students: { enrollment_id: number; patient: { id: number; name: string }; days: Record<string, AttendanceStatus>; summary: AttendanceSummary }[] } }>(`/classes/${classId}/attendance/month`, { params: { month } })).data.data,
    enabled: !!classId,
  })
}

export function useStudentMonth(enrollmentId: number | undefined, month: string) {
  return useQuery({
    queryKey: ['student-month', enrollmentId, month],
    queryFn: async () =>
      (await api.get<{ data: { month: string; days: { date: string; status: AttendanceStatus; remarks: string | null }[]; summary: AttendanceSummary } }>(`/enrollments/${enrollmentId}/attendance`, { params: { month } })).data.data,
    enabled: !!enrollmentId,
  })
}

export interface DayRecordRow {
  enrollment_id: number
  attendance: AttendanceStatus
  patient: { id: number; name: string; patient_code: string; has_photo: boolean }
  record: TrainingRecord | null
}

export function useRecordsDay(classId: number | undefined, date: string) {
  return useQuery({
    queryKey: ['records-day', classId, date],
    queryFn: async () => (await api.get<{ data: DayRecordRow[] }>(`/classes/${classId}/records`, { params: { date } })).data.data,
    enabled: !!classId,
  })
}

export function useSaveRecord(classId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (body: Record<string, unknown>) => (await api.post<{ data: TrainingRecord }>(`/classes/${classId}/records`, body)).data.data,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['records-day', classId] })
      qc.invalidateQueries({ queryKey: ['trainer-today'] })
      qc.invalidateQueries({ queryKey: ['training-records'] })
      qc.invalidateQueries({ queryKey: ['plans'] })
    },
  })
}

export function useTrainingRecords(params: { patient_id?: number; class_id?: number; page?: number }) {
  return useQuery({
    queryKey: ['training-records', params],
    queryFn: async () => (await api.get<Paginated<TrainingRecord>>('/training-records', { params })).data,
  })
}

export function usePlans(enrollmentId: number | undefined) {
  return useQuery({
    queryKey: ['plans', enrollmentId],
    queryFn: async () => (await api.get<{ data: IndividualPlan[] }>(`/enrollments/${enrollmentId}/plans`)).data.data,
    enabled: !!enrollmentId,
  })
}

export function useSavePlan(enrollmentId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, ...body }: Record<string, unknown> & { id?: number }) =>
      (id ? await api.put(`/plans/${id}`, body) : await api.post(`/enrollments/${enrollmentId}/plans`, body)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['plans', enrollmentId] }),
  })
}

export function useActivityTypes() {
  return useQuery({
    queryKey: ['activity-types', 'training'],
    queryFn: async () => (await api.get<{ data: { id: number; name: string; name_bn: string | null }[] }>('/lookups/activity-types', { params: { for: 'training' } })).data.data,
    staleTime: Infinity,
  })
}

export interface TrainerToday {
  date: string
  trainer: { id: number; name: string }
  classes: { id: number; name: string; code: string; start_time: string; end_time: string; is_holiday: boolean; students: number; marked: number; present: number; absent: number; pending_records: number }[]
  other_classes: { id: number; name: string; code: string }[]
  totals: { students: number; present: number; absent: number; pending_records: number }
}

export function useTrainerToday() {
  return useQuery({ queryKey: ['trainer-today'], queryFn: async () => (await api.get<{ data: TrainerToday }>('/trainer/today')).data.data })
}

export interface StudentRow {
  enrollment_id: number
  status: string
  start_date: string
  patient: { id: number; name: string; patient_code: string; has_photo: boolean; age: string }
  class: { id: number; name: string; code: string }
  trainer: { id: number; name: string }
  month_attendance: AttendanceSummary
}

export function useStudents(classId?: number) {
  return useQuery({
    queryKey: ['students', classId],
    queryFn: async () => (await api.get<{ data: StudentRow[] }>('/students', { params: { class_id: classId } })).data.data,
  })
}

/** Active-and-inactive trainer list for pickers (class form, substitute trainer). */
export function useTrainers(enabled = true) {
  return useQuery({
    queryKey: ['trainers', 'options'],
    queryFn: async () => (await api.get<{ data: { id: number; name: string; status: string; branch: { id: number } | null }[] }>('/trainers')).data.data,
    enabled,
  })
}
