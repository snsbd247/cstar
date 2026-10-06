import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, apiUrl } from '../../api/client'

export type Department = 'therapist' | 'trainer' | 'admin' | 'support'
export type PayType = 'fixed' | 'per_session' | 'mixed' | 'revenue_share'

export interface Employee {
  id: number
  employee_code: string
  name: string
  designation: string | null
  department: Department
  employment_type: 'full_time' | 'part_time' | 'visiting'
  pay_type: PayType
  phone: string | null
  payment_method: 'cash' | 'bank' | 'bkash' | 'nagad'
  bank_name: string | null
  bank_account: string | null
  mfs_number: string | null
  status: 'active' | 'inactive' | 'left'
  notes: string | null
  branch_id: number
  user_id: number | null
  joining_date: string
  left_date: string | null
  branch: { id: number; name: string } | null
  user: { id: number; name: string; email: string | null } | null
  therapist: { id: number; name: string } | null
  trainer: { id: number; name: string } | null
  monthly_salary: number
  advance_balance: number
}

export interface Payslip {
  id: number
  run_no: string
  label: string
  month: string
  status: string
  gross: number
  net_pay: number
  paid_at: string | null
}

export interface EmployeeDetail extends Employee {
  structures: { id: number; effective_from: string; basic: number; house_rent: number; medical: number; conveyance: number; other_allowances: { name: string; amount: number }[] | null; included_sessions: number; revenue_share_percent: number | null; total: number }[]
  rates: { id: number; service: { id: number; name: string } | null; rate: number; effective_from: string }[]
  advances: { id: number; date: string; amount: number; installment: number; balance: number; status: string; reason: string | null; paid_from: string }[]
  payslips: Payslip[]
}

export interface PayrollRun {
  id: number
  run_no: string
  month: string
  type: 'salary' | 'bonus'
  title: string | null
  label: string
  status: 'draft' | 'posted' | 'paid'
  total_gross: number
  total_deductions: number
  total_net: number
  items_count: number | null
  branch: { id: number; name: string } | null
  prepared_by: { id: number; name: string } | null
  approved_by: { id: number; name: string } | null
  can: { edit: boolean; approve: boolean; reopen: boolean; pay: boolean }
}

export interface PayrollItem {
  id: number
  department: Department
  session_count: number
  breakdown: {
    allowances?: { name: string; amount: number }[]
    sessions?: { label: string; count: number; rate?: number; amount: number }[]
    advances?: { id: number; amount: number }[]
    prorated_days?: number
    note?: string
  } | null
  note: string | null
  fixed_amount: number
  session_pay: number
  bonus: number
  other_addition: number
  absence_deduction: number
  gross: number
  advance_deduction: number
  tax: number
  other_deduction: number
  net_pay: number
  paid_amount: number
  paid_at: string | null
  employee: { id: number; employee_code: string; name: string; designation: string | null; pay_type: PayType; payment_method: string; bank_account: string | null; mfs_number: string | null }
}

export const departmentLabel: Record<Department, string> = { therapist: 'Therapist', trainer: 'Trainer', admin: 'Admin & front desk', support: 'Support staff' }
export const payTypeLabel: Record<PayType, string> = { fixed: 'Fixed monthly', per_session: 'Per session', mixed: 'Fixed + extra sessions', revenue_share: 'Revenue share' }
export const payslipUrl = (itemId: number) => apiUrl(`/payroll/payslips/${itemId}/pdf`)
export const salarySheetUrl = (runId: number) => apiUrl(`/payroll/runs/${runId}/sheet`)

export function useEmployees(status?: string) {
  return useQuery({ queryKey: ['employees', status], queryFn: async () => (await api.get<{ data: Employee[] }>('/hr/employees', { params: { status } })).data.data })
}

export function useEmployee(id?: number) {
  return useQuery({ queryKey: ['employee', id], queryFn: async () => (await api.get<{ data: EmployeeDetail }>(`/hr/employees/${id}`)).data.data, enabled: !!id })
}

export function usePayrollRuns() {
  return useQuery({ queryKey: ['payroll-runs'], queryFn: async () => (await api.get<{ data: PayrollRun[] }>('/payroll/runs')).data.data })
}

export function usePayrollRun(id?: number) {
  return useQuery({
    queryKey: ['payroll-run', id],
    queryFn: async () => (await api.get<{ data: PayrollRun & { items: PayrollItem[] } }>(`/payroll/runs/${id}`)).data.data,
    enabled: !!id,
  })
}

export function useMyPayslips() {
  return useQuery({
    queryKey: ['my-payslips'],
    queryFn: async () => (await api.get<{ data: Payslip[]; employee: { employee_code: string; name: string; designation: string | null } | null }>('/me/payslips')).data,
  })
}

export function usePayrollMutations() {
  const qc = useQueryClient()
  const invalidate = () => {
    for (const key of ['employees', 'employee', 'payroll-runs', 'payroll-run', 'ledger', 'accounts-dashboard', 'report']) {
      qc.invalidateQueries({ queryKey: [key] })
    }
  }
  return {
    saveEmployee: useMutation({
      mutationFn: async ({ id, ...body }: Record<string, unknown> & { id?: number }) =>
        (id ? await api.put<{ data: Employee }>(`/hr/employees/${id}`, body) : await api.post<{ data: Employee }>('/hr/employees', body)).data.data,
      onSuccess: invalidate,
    }),
    saveStructure: useMutation({ mutationFn: ({ employeeId, ...body }: Record<string, unknown> & { employeeId: number }) => api.post(`/hr/employees/${employeeId}/salary-structures`, body), onSuccess: invalidate }),
    saveRate: useMutation({ mutationFn: ({ employeeId, ...body }: Record<string, unknown> & { employeeId: number }) => api.post(`/hr/employees/${employeeId}/session-rates`, body), onSuccess: invalidate }),
    giveAdvance: useMutation({ mutationFn: ({ employeeId, ...body }: Record<string, unknown> & { employeeId: number }) => api.post(`/hr/employees/${employeeId}/advances`, body), onSuccess: invalidate }),
    createRun: useMutation({ mutationFn: async (body: Record<string, unknown>) => (await api.post<{ data: PayrollRun }>('/payroll/runs', body)).data.data, onSuccess: invalidate }),
    runAction: useMutation({
      mutationFn: ({ id, action, ...body }: { id: number; action: 'recalculate' | 'approve' | 'reopen' | 'pay'; reason?: string; paid_from_account_id?: number; item_ids?: number[] }) =>
        api.post(`/payroll/runs/${id}/${action}`, body),
      onSuccess: invalidate,
    }),
    updateItem: useMutation({ mutationFn: ({ id, ...body }: Record<string, unknown> & { id: number }) => api.put(`/payroll/items/${id}`, body), onSuccess: invalidate }),
  }
}
