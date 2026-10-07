import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, apiUrl } from '../../api/client'
import type { Paginated } from '../../types'

export type InvoiceStatus = 'draft' | 'issued' | 'partially_paid' | 'paid' | 'void'
export type PaymentMethod = 'cash' | 'bkash' | 'nagad' | 'bank' | 'card' | 'online'
export type ItemType = 'admission' | 'assessment' | 'training_fee' | 'therapy_session' | 'package' | 'consultation' | 'other' | 'opening_balance'

export interface InvoiceItem {
  id?: number
  item_type: ItemType
  description: string
  service_id?: number | null
  package_id?: number | null
  enrollment_id?: number | null
  billing_period?: string | null
  quantity: number
  unit_price: number
  discount: number
  line_total?: number
}

export interface Invoice {
  id: number
  invoice_no: string | null
  status: InvoiceStatus
  issue_date: string | null
  due_date: string | null
  subtotal: number
  discount_total: number
  total: number
  paid_total: number
  due_total: number
  discount_reason: string | null
  notes: string | null
  void_reason: string | null
  is_overdue: boolean
  patient?: { id: number; name: string; patient_code: string; phone: string }
  branch?: { id: number; name: string }
  items?: InvoiceItem[]
  payments?: { amount: number; from_advance: boolean; receipt_no: string; payment_id: number; method: PaymentMethod; paid_at: string }[]
  can: { edit: boolean; void: boolean; pay: boolean }
}

export interface Payment {
  id: number
  receipt_no: string
  type: 'payment' | 'refund'
  amount: number
  method: PaymentMethod
  transaction_ref: string | null
  paid_at: string
  payer_name: string | null
  notes: string | null
  status: 'completed' | 'void'
  void_reason: string | null
  patient?: { id: number; name: string; patient_code: string }
  branch?: { id: number; name: string }
  received_by?: { id: number; name: string } | null
  allocations?: { invoice_id: number; invoice_no: string; amount: number; from_advance: boolean }[]
  unallocated?: number
}

export interface PackageRow {
  id: number
  name: string
  name_bn: string | null
  service_id: number
  sessions_count: number
  validity_days: number
  price: number
  per_session: number
  branch_id: number | null
  description: string | null
  is_active: boolean
  service: { id: number; name: string }
  branch: { id: number; name: string } | null
}

export interface PatientPackageRow {
  id: number
  name: string
  service: string
  status: 'active' | 'exhausted' | 'expired' | 'cancelled'
  total_sessions: number
  used_sessions: number
  remaining: number
  expiry_date: string
  start_date?: string
  price: number
  patient?: { id: number; name: string; patient_code: string }
  renewal_due?: boolean
}

export interface BillingAccount {
  due: number
  advance: number
  invoices: Invoice[]
  payments: Payment[]
  packages: PatientPackageRow[]
}

export const methodLabel: Record<PaymentMethod, string> = { cash: 'Cash', bkash: 'bKash', nagad: 'Nagad', bank: 'Bank', card: 'Card', online: 'Online' }
export const itemTypeLabel: Record<ItemType, string> = {
  admission: 'Admission fee',
  assessment: 'Assessment',
  training_fee: 'Training fee',
  therapy_session: 'Therapy session',
  package: 'Package',
  consultation: 'Consultation',
  other: 'Other',
  opening_balance: 'Previous dues',
}
export const invoiceStatusStyle: Record<InvoiceStatus, { label: string; tone: 'gray' | 'blue' | 'amber' | 'green' | 'red' }> = {
  draft: { label: 'Draft', tone: 'gray' },
  issued: { label: 'Unpaid', tone: 'red' },
  partially_paid: { label: 'Part paid', tone: 'amber' },
  paid: { label: 'Paid', tone: 'green' },
  void: { label: 'Void', tone: 'gray' },
}

/** ৳1,234 (decimals only when needed). */
export const taka = (v: number | string | null | undefined) => {
  const n = Number(v ?? 0)
  return `৳${n.toLocaleString('en-IN', { minimumFractionDigits: n % 1 ? 2 : 0, maximumFractionDigits: 2 })}`
}

export const invoicePdfUrl = (id: number) => apiUrl(`/invoices/${id}/pdf`)
export const receiptPdfUrl = (id: number) => apiUrl(`/payments/${id}/receipt`)

function useInvalidate() {
  const qc = useQueryClient()
  return () => {
    for (const key of ['invoices', 'invoice', 'payments', 'billing-account', 'dues', 'collection', 'patient-packages', 'timeline', 'ledger']) {
      qc.invalidateQueries({ queryKey: [key] })
    }
  }
}

export function useInvoices(params: Record<string, string | number | boolean | undefined>) {
  return useQuery({ queryKey: ['invoices', params], queryFn: async () => (await api.get<Paginated<Invoice>>('/invoices', { params })).data })
}

export function useInvoice(id?: number) {
  return useQuery({ queryKey: ['invoice', id], queryFn: async () => (await api.get<{ data: Invoice }>(`/invoices/${id}`)).data.data, enabled: !!id })
}

export function useBillingAccount(patientId: number, enabled = true) {
  return useQuery({
    queryKey: ['billing-account', patientId],
    queryFn: async () => (await api.get<{ data: BillingAccount }>(`/patients/${patientId}/billing`)).data.data,
    enabled,
  })
}

export function usePayments(params: Record<string, string | number | undefined>) {
  return useQuery({ queryKey: ['payments', params], queryFn: async () => (await api.get<Paginated<Payment>>('/payments', { params })).data })
}

export interface Collection {
  date: string
  own_only: boolean
  total: number
  count: number
  by_method: Record<PaymentMethod, number>
  by_user: { user: { id: number; name: string } | null; total: number; by_method: Record<PaymentMethod, number> }[]
}

export function useCollection(date: string) {
  return useQuery({ queryKey: ['collection', date], queryFn: async () => (await api.get<{ data: Collection }>('/billing/collection', { params: { date } })).data.data })
}

export function useDues(enabled = true) {
  return useQuery({
    queryKey: ['dues'],
    queryFn: async () =>
      (await api.get<{ data: { patient: { id: number; name: string; patient_code: string; phone: string }; due: number; invoices: number; oldest_due_date: string; overdue: boolean }[]; total: number }>('/billing/dues')).data,
    enabled,
  })
}

export function usePackages(all = false) {
  return useQuery({ queryKey: ['packages', all], queryFn: async () => (await api.get<{ data: PackageRow[] }>('/packages', { params: { all: all ? 1 : undefined } })).data.data })
}

export function usePatientPackages(status?: string) {
  return useQuery({
    queryKey: ['patient-packages', status],
    queryFn: async () => (await api.get<{ data: PatientPackageRow[] }>('/patient-packages', { params: { status } })).data.data,
  })
}

export function useBillingSettings() {
  return useQuery({ queryKey: ['billing-settings'], queryFn: async () => (await api.get<{ data: Record<string, string> }>('/billing/settings')).data.data })
}

export function useBillingMutations() {
  const invalidate = useInvalidate()
  const qc = useQueryClient()
  return {
    saveInvoice: useMutation({
      mutationFn: async ({ id, ...body }: Record<string, unknown> & { id?: number }) =>
        (id ? await api.put<{ data: Invoice }>(`/invoices/${id}`, body) : await api.post<{ data: Invoice }>('/invoices', body)).data.data,
      onSuccess: invalidate,
    }),
    issue: useMutation({ mutationFn: async (id: number) => (await api.post<{ data: Invoice }>(`/invoices/${id}/issue`)).data.data, onSuccess: invalidate }),
    voidInvoice: useMutation({ mutationFn: ({ id, reason }: { id: number; reason: string }) => api.post(`/invoices/${id}/void`, { reason }), onSuccess: invalidate }),
    deleteDraft: useMutation({ mutationFn: (id: number) => api.delete(`/invoices/${id}`), onSuccess: invalidate }),
    pay: useMutation({
      mutationFn: async ({ patientId, ...body }: Record<string, unknown> & { patientId: number }) =>
        (await api.post<{ data: Payment }>(`/patients/${patientId}/payments`, body)).data.data,
      onSuccess: invalidate,
    }),
    refund: useMutation({
      mutationFn: ({ patientId, ...body }: Record<string, unknown> & { patientId: number }) => api.post(`/patients/${patientId}/refunds`, body),
      onSuccess: invalidate,
    }),
    voidPayment: useMutation({ mutationFn: ({ id, reason }: { id: number; reason: string }) => api.post(`/payments/${id}/void`, { reason }), onSuccess: invalidate }),
    sellPackage: useMutation({
      mutationFn: async ({ patientId, ...body }: Record<string, unknown> & { patientId: number }) =>
        (await api.post<{ data: Invoice }>(`/patients/${patientId}/packages`, body)).data.data,
      onSuccess: invalidate,
    }),
    savePackage: useMutation({
      mutationFn: ({ id, ...body }: Record<string, unknown> & { id?: number }) => (id ? api.put(`/packages/${id}`, body) : api.post('/packages', body)),
      onSuccess: () => qc.invalidateQueries({ queryKey: ['packages'] }),
    }),
    trainingFees: useMutation({
      mutationFn: async (body: { month: string; branch_id?: number }) => (await api.post<{ created: number; message: string }>('/billing/training-fees', body)).data,
      onSuccess: invalidate,
    }),
    saveSettings: useMutation({
      mutationFn: (body: Record<string, unknown>) => api.put('/billing/settings', body),
      onSuccess: () => qc.invalidateQueries({ queryKey: ['billing-settings'] }),
    }),
  }
}
