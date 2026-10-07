import type { Practice } from './HomePractice'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { api, apiUrl } from '../../api/client'

export interface Child {
  id: number
  name: string
  name_bn: string | null
  patient_code: string
  gender: string
  age: string | null
  has_training: boolean
  has_therapy: boolean
  programmes: { type: 'training' | 'therapy'; name: string | null; name_bn?: string | null; with: string | null }[]
}

export interface PortalAppointment {
  id: number
  date: string
  start_time: string
  end_time: string
  status: string
  type: string
  service: string | null
  service_bn: string | null
  therapist: string | null
  branch: string | null
}

export interface AttendanceSummary {
  present: number
  late: number
  absent: number
  leave: number
  holiday: number
  rate: number | null
}

/** Bangla numbers and dates for the parent portal (decision D7). */
export const bnNumber = (n: number) => n.toLocaleString('bn-BD')
export const bnTaka = (n: number) => `৳${Number(n || 0).toLocaleString('bn-BD', { maximumFractionDigits: 2 })}`
export const bnDate = (iso: string, opts: Intl.DateTimeFormatOptions = { day: 'numeric', month: 'long', year: 'numeric' }) =>
  new Date(`${iso.slice(0, 10)}T00:00:00`).toLocaleDateString('bn-BD', opts)
export const bnWeekday = (iso: string) => new Date(`${iso.slice(0, 10)}T00:00:00`).toLocaleDateString('bn-BD', { weekday: 'long' })
export const bnTime = (hhmm: string) => {
  const [h, m] = hhmm.split(':').map(Number)
  const part = h < 12 ? 'সকাল' : h < 15 ? 'দুপুর' : h < 18 ? 'বিকাল' : 'সন্ধ্যা'
  return `${part} ${bnNumber(h % 12 || 12)}:${String(m).padStart(2, '0').replace(/\d/g, (d) => '০১২৩৪৫৬৭৮৯'[Number(d)])}`
}
export const WEEKDAYS_BN = ['রবিবার', 'সোমবার', 'মঙ্গলবার', 'বুধবার', 'বৃহস্পতিবার', 'শুক্রবার', 'শনিবার']

export const appointmentStatusBn: Record<string, string> = {
  pending: 'অপেক্ষমাণ',
  confirmed: 'নিশ্চিত',
  checked_in: 'উপস্থিত',
  completed: 'সম্পন্ন',
  cancelled: 'বাতিল',
  no_show: 'আসেনি',
}
export const invoiceStatusBn: Record<string, { label: string; tone: 'red' | 'amber' | 'green' | 'gray' }> = {
  issued: { label: 'বকেয়া', tone: 'red' },
  partially_paid: { label: 'আংশিক পরিশোধ', tone: 'amber' },
  paid: { label: 'পরিশোধিত', tone: 'green' },
  void: { label: 'বাতিল', tone: 'gray' },
}
export const methodBn: Record<string, string> = { cash: 'নগদ', bkash: 'বিকাশ', nagad: 'নগদ (মোবাইল)', bank: 'ব্যাংক', card: 'কার্ড' }

export const portalPdf = {
  invoice: (id: number) => apiUrl(`/portal/invoices/${id}/pdf`),
  receipt: (id: number) => apiUrl(`/portal/payments/${id}/receipt`),
  assessment: (id: number) => apiUrl(`/portal/assessments/${id}/pdf`),
  progress: (childId: number) => apiUrl(`/portal/children/${childId}/progress-report`),
}

export function useChildren() {
  return useQuery({ queryKey: ['portal-children'], queryFn: async () => (await api.get<{ data: Child[] }>('/portal/children')).data.data })
}

const KEY = 'cstar.portal.child'

/** The child being viewed — remembered on this phone (a per-viewer convenience only). */
export function useSelectedChild() {
  const { data: children, isLoading } = useChildren()
  const [id, setId] = useState<number | null>(() => {
    try {
      return Number(localStorage.getItem(KEY)) || null
    } catch {
      return null
    }
  })
  const child = children?.find((c) => c.id === id) ?? children?.[0] ?? null

  useEffect(() => {
    if (!child) return
    try {
      localStorage.setItem(KEY, String(child.id))
    } catch {
      // storage blocked — the first child is shown next time
    }
  }, [child])

  return { children: children ?? [], child, isLoading, select: setId }
}

function usePortal<T>(childId: number | undefined, path: string, params?: Record<string, string>) {
  return useQuery({
    queryKey: ['portal', path, childId, params],
    queryFn: async () => (await api.get<{ data: T }>(`/portal/children/${childId}/${path}`, { params })).data.data,
    enabled: !!childId,
  })
}

export const usePortalHome = (childId?: number) =>
  usePortal<{
    child: Child
    next_appointment: PortalAppointment | null
    attendance: AttendanceSummary | null
    due: number
    new_reports: number
    latest_note: { date: string; from: string; text: string | null; home_practice: string | null; session_id?: number; practice?: Practice } | null
    updates: { title: string; description: string | null; at: string; type: string }[]
  }>(childId, 'home')

export const usePortalSchedule = (childId?: number) =>
  usePortal<{
    upcoming: PortalAppointment[]
    past: PortalAppointment[]
    class: { name: string; trainer: string | null; days: { weekday: number; start_time: string; end_time: string }[] } | null
    requests_enabled: boolean
    services: { id: number; name: string; name_bn: string | null }[]
  }>(childId, 'schedule')

export const usePortalAttendance = (childId: number | undefined, month: string, enabled: boolean) =>
  useQuery({
    queryKey: ['portal', 'attendance', childId, month],
    queryFn: async () =>
      (await api.get<{ data: { month: string; days: { date: string; status: string; remarks: string | null }[]; summary: AttendanceSummary } | null }>(`/portal/children/${childId}/attendance`, { params: { month } })).data.data,
    enabled: !!childId && enabled,
  })

export const usePortalProgress = (childId?: number) =>
  usePortal<{
    programmes: { type: string; label: string; plans: { title: string; review_date: string | null; goals: { domain: string | null; title: string; target: string | null; progress_percent: number; status: string }[] }[] }[]
    notes: { kind: 'therapy' | 'training'; date: string; title: string; by: string | null; text: string | null; home_practice: string | null; performance?: number | null; session_id?: number; practice?: Practice }[]
    reports: { id: number; title: string; title_bn: string | null; date: string; by: string; summary: string | null }[]
  }>(childId, 'progress')

export const usePortalBilling = (childId?: number) =>
  usePortal<{
    due: number
    advance: number
    invoices: { id: number; invoice_no: string; status: string; issue_date: string; due_date: string | null; total: number; paid_total: number; due_total: number; items: string[] }[]
    payments: { id: number; receipt_no: string; type: string; amount: number; method: string; paid_at: string }[]
    packages: { name: string; name_bn: string | null; status: string; total_sessions: number; used_sessions: number; remaining: number; expiry_date: string }[]
  }>(childId, 'billing')

export function usePortalProfile() {
  return useQuery({
    queryKey: ['portal', 'profile'],
    queryFn: async () =>
      (
        await api.get<{
          data: {
            guardian: { name: string; phone: string; alt_phone: string | null; email: string | null; address: string | null } | null
            children: { id: number; name: string; name_bn: string | null; patient_code: string; relationship: string }[]
            requests: { reference: string; status: string; preferred_date: string | null; message: string | null; created_at: string }[]
          }
        }>('/portal/profile')
      ).data.data,
  })
}

export function useRequestAppointment(childId?: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (body: Record<string, unknown>) => (await api.post<{ data: { reference: string } }>(`/portal/children/${childId}/appointment-requests`, body)).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['portal', 'profile'] }),
  })
}
