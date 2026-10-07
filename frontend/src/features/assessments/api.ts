import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, apiUrl } from '../../api/client'
import type { Paginated } from '../../types'

export interface AssessmentType {
  id: number
  name: string
  name_bn: string | null
  sections: { key: string; label: string }[]
}

export interface RecommendationItem {
  id?: number
  enrollment_type: 'training' | 'therapy'
  service?: { id: number; name: string } | null
  service_id?: number | null
  frequency: string | null
  priority: 'high' | 'normal' | 'low'
  note: string | null
  enrollment?: { id: number; enrollment_code: string; status: string } | null
}

export interface Assessment {
  id: number
  assessment_code: string
  date: string
  status: 'draft' | 'final'
  shared_with_parent: boolean
  finalized_at: string | null
  appointment_id: number | null
  chief_complaint: string | null
  background: string | null
  summary: string | null
  recommendations: string | null
  parent_summary: string | null
  section_findings: Record<string, string>
  can_edit: boolean
  can_amend?: boolean
  updated_at?: string
  patient?: { id: number; name: string; patient_code: string }
  type?: AssessmentType
  therapist?: { id: number; name: string }
  branch?: { id: number; name: string }
  recommendation_items?: RecommendationItem[]
}

/** What reception sees: recommendations only, no clinical findings. */
export interface PatientRecommendation extends RecommendationItem {
  id: number
  assessment: { id: number; code: string; date: string; type: string; therapist: { id: number; name: string } }
}

export const assessmentPdfUrl = (id: number) => apiUrl(`/assessments/${id}/pdf`)
export const progressReportUrl = (patientId: number, from?: string, to?: string) =>
  apiUrl(`/patients/${patientId}/progress-report`) + (from || to ? `?${new URLSearchParams({ ...(from && { from }), ...(to && { to }) })}` : '')

export function useAssessmentTypes() {
  return useQuery({
    queryKey: ['assessment-types'],
    queryFn: async () => (await api.get<{ data: AssessmentType[] }>('/lookups/assessment-types')).data.data,
    staleTime: Infinity,
  })
}

export function useAssessments(params: Record<string, string | number | boolean | undefined>, enabled = true) {
  return useQuery({
    queryKey: ['assessments', params],
    queryFn: async () => (await api.get<Paginated<Assessment>>('/assessments', { params })).data,
    enabled,
  })
}

export function useAssessment(id?: number) {
  return useQuery({
    queryKey: ['assessment', id],
    queryFn: async () => (await api.get<{ data: Assessment }>(`/assessments/${id}`)).data.data,
    enabled: !!id,
  })
}

export function usePatientRecommendations(patientId: number, enabled = true) {
  return useQuery({
    queryKey: ['recommendations', patientId],
    queryFn: async () => (await api.get<{ data: PatientRecommendation[] }>(`/patients/${patientId}/recommendations`)).data.data,
    enabled,
  })
}

function useInvalidate() {
  const qc = useQueryClient()
  return () => {
    for (const key of ['assessments', 'assessment', 'recommendations', 'timeline', 'therapist-today', 'appointments']) {
      qc.invalidateQueries({ queryKey: [key] })
    }
  }
}

export function useSaveAssessment() {
  const invalidate = useInvalidate()
  return useMutation({
    mutationFn: async ({ id, patientId, body }: { id?: number; patientId: number; body: Record<string, unknown> }) =>
      (id
        ? await api.put<{ data: Assessment }>(`/assessments/${id}`, body)
        : await api.post<{ data: Assessment }>(`/patients/${patientId}/assessments`, body)
      ).data.data,
    onSuccess: invalidate,
  })
}

export function useShareAssessment() {
  const invalidate = useInvalidate()
  return useMutation({
    mutationFn: async ({ id, shared }: { id: number; shared: boolean }) => (await api.post<{ data: Assessment }>(`/assessments/${id}/share`, { shared })).data.data,
    onSuccess: invalidate,
  })
}

export const priorityTone = { high: 'red', normal: 'blue', low: 'gray' } as const
