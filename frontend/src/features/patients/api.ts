import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '../../api/client'
import type { Paginated } from '../../types'
import type { Enrollment, EnrollmentOptions, Guardian, PatientDetail, PatientDocument, PatientListItem, TimelineEvent } from './types'

export interface PatientFilters {
  search?: string
  type?: string
  status?: string
  page?: number
}

export function usePatients(filters: PatientFilters) {
  return useQuery({
    queryKey: ['patients', filters],
    queryFn: async () => (await api.get<Paginated<PatientListItem>>('/patients', { params: filters })).data,
    placeholderData: keepPreviousData,
  })
}

export function usePatient(id: number | string | undefined) {
  return useQuery({
    queryKey: ['patient', Number(id)],
    queryFn: async () => (await api.get<{ data: PatientDetail }>(`/patients/${id}`)).data.data,
    enabled: !!id,
  })
}

/** Invalidate everything that shows this child after a change. */
export function useRefreshPatient() {
  const queryClient = useQueryClient()
  return (id: number) => {
    queryClient.invalidateQueries({ queryKey: ['patient', id] })
    queryClient.invalidateQueries({ queryKey: ['patients'] })
    queryClient.invalidateQueries({ queryKey: ['timeline', id] })
  }
}

export function useSavePatient() {
  const refresh = useRefreshPatient()
  return useMutation({
    mutationFn: async ({ id, ...input }: Record<string, unknown> & { id?: number }) =>
      (id ? await api.put<{ data: PatientDetail }>(`/patients/${id}`, input) : await api.post<{ data: PatientDetail }>('/patients', input)).data.data,
    onSuccess: (patient) => refresh(patient.id),
  })
}

export function useUploadPhoto(patientId: number) {
  const refresh = useRefreshPatient()
  return useMutation({
    mutationFn: async (file: File) => {
      const body = new FormData()
      body.append('photo', file)
      await api.post(`/patients/${patientId}/photo`, body)
    },
    onSuccess: () => refresh(patientId),
  })
}

export async function checkDuplicates(params: { phone?: string; date_of_birth?: string; name?: string; except_id?: number }) {
  return (await api.get<{ data: { id: number; patient_code: string; name: string; date_of_birth: string; branch: string | null }[] }>('/patients/check-duplicates', { params })).data.data
}

export async function lookupGuardian(phone: string) {
  return (await api.get<{ data: { id: number; name: string; phone: string; children: { patient_code: string; name: string }[] }[] }>('/guardians/lookup', { params: { phone } })).data.data
}

export function useDiagnoses() {
  return useQuery({
    queryKey: ['diagnoses'],
    queryFn: async () => (await api.get<{ data: { id: number; name: string; name_bn: string | null }[] }>('/lookups/diagnoses')).data.data,
    staleTime: Infinity,
  })
}

// Guardians
export function useGuardianMutations(patientId: number) {
  const refresh = useRefreshPatient()
  const onSuccess = () => refresh(patientId)
  return {
    add: useMutation({ mutationFn: (input: Record<string, unknown>) => api.post<{ data: Guardian[] }>(`/patients/${patientId}/guardians`, input), onSuccess }),
    update: useMutation({
      mutationFn: ({ id, ...input }: Record<string, unknown> & { id: number }) => api.put(`/patients/${patientId}/guardians/${id}`, input),
      onSuccess,
    }),
    remove: useMutation({ mutationFn: (id: number) => api.delete(`/patients/${patientId}/guardians/${id}`), onSuccess }),
    portalAccount: useMutation({
      mutationFn: ({ id, password }: { id: number; password: string }) => api.post(`/guardians/${id}/portal-account`, { password }),
      onSuccess,
    }),
  }
}

// Documents
export function useDocuments(patientId: number) {
  return useQuery({
    queryKey: ['documents', patientId],
    queryFn: async () => (await api.get<{ data: PatientDocument[] }>(`/patients/${patientId}/documents`)).data.data,
  })
}

export function useUploadDocument(patientId: number) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (body: FormData) => api.post(`/patients/${patientId}/documents`, body),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['documents', patientId] })
      queryClient.invalidateQueries({ queryKey: ['timeline', patientId] })
    },
  })
}

export function useDeleteDocument(patientId: number) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => api.delete(`/documents/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['documents', patientId] }),
  })
}

export function useTimeline(patientId: number) {
  return useQuery({
    queryKey: ['timeline', patientId],
    queryFn: async () => (await api.get<Paginated<TimelineEvent>>(`/patients/${patientId}/timeline`)).data.data,
  })
}

// Enrollments
export function useEnrollmentOptions(branchId: number | undefined) {
  return useQuery({
    queryKey: ['enrollment-options', branchId],
    queryFn: async () => (await api.get<{ data: EnrollmentOptions }>('/lookups/enrollment-options', { params: { branch_id: branchId } })).data.data,
    enabled: !!branchId,
  })
}

export function useEnrollmentMutations(patientId: number) {
  const refresh = useRefreshPatient()
  const queryClient = useQueryClient()
  const onSuccess = () => {
    refresh(patientId)
    queryClient.invalidateQueries({ queryKey: ['enrollment-options'] })
  }
  return {
    create: useMutation({ mutationFn: (input: Record<string, unknown>) => api.post<{ data: Enrollment }>('/enrollments', input), onSuccess }),
    changeStatus: useMutation({
      mutationFn: ({ id, action, ...input }: { id: number; action: string } & Record<string, unknown>) => api.post(`/enrollments/${id}/${action}`, input),
      onSuccess,
    }),
    transfer: useMutation({
      mutationFn: ({ id, ...input }: { id: number } & Record<string, unknown>) => api.post(`/enrollments/${id}/transfer`, input),
      onSuccess,
    }),
  }
}
