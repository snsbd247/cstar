import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, apiUrl } from '../../api/client'

export type VoucherType = 'payment' | 'receipt' | 'journal' | 'contra'
export type VoucherStatus = 'draft' | 'submitted' | 'posted' | 'rejected' | 'reversed'

export interface AccountRow {
  id: number
  code: string
  name: string
  name_bn: string | null
  type: 'asset' | 'liability' | 'equity' | 'income' | 'expense'
  parent_id: number | null
  is_group: boolean
  is_system: boolean
  is_active: boolean
  subtype: string | null
  balance: number
}

export interface VoucherLine {
  account_id: number
  account?: { id: number; code: string; name: string }
  debit: number
  credit: number
  memo: string | null
}

export interface Voucher {
  id: number
  voucher_no: string
  type: VoucherType
  date: string
  narration: string
  amount: number
  status: VoucherStatus
  reject_reason: string | null
  branch: { id: number; name: string } | null
  prepared_by: { id: number; name: string } | null
  approved_by: { id: number; name: string } | null
  needs_approval: boolean
  lines: VoucherLine[]
  can: { edit: boolean; approve: boolean; reverse: boolean }
}

export interface Expense {
  id: number
  expense_no: string
  date: string
  amount: number
  payee: string | null
  reference: string | null
  description: string | null
  status: VoucherStatus
  category: { id: number; name: string; name_bn: string | null }
  branch: { id: number; name: string }
  paid_from: { id: number; code: string; name: string }
  created_by: { id: number; name: string } | null
  voucher_id: number | null
  voucher_no: string | null
  reject_reason: string | null
  has_attachment: boolean
}

export interface CashClosing {
  id: number
  date: string
  status: 'submitted' | 'received'
  expected: { by_method: Record<string, number>; cash_expenses: number }
  expected_cash: number
  counted_cash: number
  difference: number
  denominations: Record<string, number> | null
  reason: string | null
  user: { id: number; name: string }
  branch: { id: number; name: string }
  received_by: { id: number; name: string } | null
  can_receive: boolean
}

export const voucherTypeLabel: Record<VoucherType, string> = { payment: 'Payment (PV)', receipt: 'Receipt (RV)', journal: 'Journal (JV)', contra: 'Contra (CV)' }
export const voucherStatusTone: Record<VoucherStatus, 'gray' | 'amber' | 'green' | 'red'> = { draft: 'gray', submitted: 'amber', posted: 'green', rejected: 'red', reversed: 'gray' }
export const reportPdfUrl = (report: string, params: Record<string, string | number | undefined>, format: 'pdf' | 'xlsx' = 'pdf') =>
  apiUrl(`/accounts/reports/${report}?${new URLSearchParams(Object.entries({ ...params, format } as Record<string, string | number | undefined>).filter(([, v]) => v !== undefined && v !== '').map(([k, v]) => [k, String(v)]))}`)
export const expenseAttachmentUrl = (id: number) => apiUrl(`/accounts/expenses/${id}/attachment`)

function useInvalidate() {
  const qc = useQueryClient()
  return () => {
    for (const key of ['vouchers', 'expenses', 'ledger', 'accounts-dashboard', 'cash-closings', 'cash-expected', 'periods', 'report', 'dashboard']) {
      qc.invalidateQueries({ queryKey: [key] })
    }
  }
}

export function useChart() {
  return useQuery({
    queryKey: ['ledger', 'chart'],
    queryFn: async () => (await api.get<{ data: AccountRow[]; totals: { debit: number; credit: number } }>('/accounts/chart')).data,
  })
}

export function useAccountsDashboard() {
  return useQuery({
    queryKey: ['accounts-dashboard'],
    queryFn: async () =>
      (
        await api.get<{
          data: {
            money: { account: { id: number; code: string; name: string; subtype: string }; balance: number }[]
            receivable: number
            today: { income: number; expense: number }
            month: { income: number; expense: number; profit: number }
            months: { month: string; income: number; expense: number }[]
            pending_vouchers: number
            pending_closings: number
          }
        }>('/accounts/dashboard')
      ).data.data,
  })
}

export function useVouchers(params: Record<string, string | number | undefined>) {
  return useQuery({
    queryKey: ['vouchers', params],
    queryFn: async () => (await api.get<{ data: Voucher[]; meta: { current_page: number; last_page: number; total: number }; approval_limit: number }>('/accounts/vouchers', { params })).data,
  })
}

export function useExpenses(params: Record<string, string | number | undefined>) {
  return useQuery({
    queryKey: ['expenses', params],
    queryFn: async () => (await api.get<{ data: Expense[]; meta: { current_page: number; last_page: number; total: number } }>('/accounts/expenses', { params })).data,
  })
}

export function useExpenseOptions(branchId?: number) {
  return useQuery({
    queryKey: ['expense-options', branchId],
    queryFn: async () =>
      (await api.get<{ data: { categories: { id: number; name: string; name_bn: string | null }[]; paid_from: { id: number; code: string; name: string; subtype: string }[] } }>('/accounts/expense-options', { params: { branch_id: branchId } })).data.data,
    enabled: !!branchId,
  })
}

export function useCashClosings(params: Record<string, string | undefined>) {
  return useQuery({ queryKey: ['cash-closings', params], queryFn: async () => (await api.get<{ data: CashClosing[] }>('/accounts/cash-closings', { params })).data.data })
}

export function useCashExpected(branchId?: number, date?: string) {
  return useQuery({
    queryKey: ['cash-expected', branchId, date],
    queryFn: async () =>
      (await api.get<{ data: { by_method: Record<string, number>; cash_expenses: number; expected_cash: number; payments: number; already_closed: boolean; notes: number[] } }>('/accounts/cash-closings/expected', { params: { branch_id: branchId, date } })).data.data,
    enabled: !!branchId,
  })
}

export function useReport<T>(report: string, params: Record<string, string | number | undefined>, enabled = true) {
  return useQuery({
    queryKey: ['report', report, params],
    queryFn: async () => (await api.get<{ data: T }>(`/accounts/reports/${report}`, { params })).data.data,
    enabled,
  })
}

export function usePeriods(year?: number) {
  return useQuery({
    queryKey: ['periods', year],
    queryFn: async () =>
      (
        await api.get<{
          data: { id: number; label: string; status: 'open' | 'closed'; start_date: string; end_date: string }[]
          year: { id: number; name: string; status: 'open' | 'closed'; closed_at: string | null; start_date: string; end_date: string }
          years: { id: number; name: string; status: string; start_year: number }[]
        }>('/accounts/periods', { params: { year } })
      ).data,
  })
}

export function useAccountSettings() {
  return useQuery({ queryKey: ['account-settings'], queryFn: async () => (await api.get<{ data: { approval_limit: string } }>('/accounts/settings')).data.data })
}

export function useAccountsMutations() {
  const invalidate = useInvalidate()
  const qc = useQueryClient()
  return {
    saveVoucher: useMutation({
      mutationFn: async ({ id, ...body }: Record<string, unknown> & { id?: number }) =>
        (id ? await api.put<{ data: Voucher }>(`/accounts/vouchers/${id}`, body) : await api.post<{ data: Voucher }>('/accounts/vouchers', body)).data.data,
      onSuccess: invalidate,
    }),
    voucherAction: useMutation({
      mutationFn: async ({ id, action, reason }: { id: number; action: 'submit' | 'approve' | 'reject' | 'reverse'; reason?: string }) =>
        (await api.post<{ data: Voucher }>(`/accounts/vouchers/${id}/${action}`, { reason })).data.data,
      onSuccess: invalidate,
    }),
    deleteVoucher: useMutation({ mutationFn: (id: number) => api.delete(`/accounts/vouchers/${id}`), onSuccess: invalidate }),
    saveExpense: useMutation({
      mutationFn: async (form: FormData) => (await api.post<{ data: { id: number; expense_no: string; status: VoucherStatus } }>('/accounts/expenses', form)).data.data,
      onSuccess: invalidate,
    }),
    submitExpense: useMutation({ mutationFn: (id: number) => api.post(`/accounts/expenses/${id}/submit`), onSuccess: invalidate }),
    closeCash: useMutation({ mutationFn: (body: Record<string, unknown>) => api.post('/accounts/cash-closings', body), onSuccess: invalidate }),
    receiveCash: useMutation({ mutationFn: (id: number) => api.post(`/accounts/cash-closings/${id}/receive`), onSuccess: invalidate }),
    periodAction: useMutation({
      mutationFn: ({ id, action, reason }: { id: number; action: 'close' | 'reopen'; reason?: string }) => api.post(`/accounts/periods/${id}/${action}`, { reason }),
      onSuccess: invalidate,
    }),
    saveAccount: useMutation({
      mutationFn: ({ id, ...body }: Record<string, unknown> & { id?: number }) => (id ? api.put(`/accounts/chart/${id}`, body) : api.post('/accounts/chart', body)),
      onSuccess: () => qc.invalidateQueries({ queryKey: ['ledger'] }),
    }),
    saveSettings: useMutation({
      mutationFn: (body: { approval_limit: number }) => api.put('/accounts/settings', body),
      onSuccess: () => qc.invalidateQueries({ queryKey: ['account-settings'] }),
    }),
  }
}
