import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '../../api/client'
import type { Paginated, RoleInfo, RoleName, User, UserStatus } from '../../types'

export interface UserFilters {
  search?: string
  role?: string
  branch_id?: string
  status?: string
  page?: number
}

export interface UserInput {
  name: string
  email: string | null
  phone: string | null
  password?: string
  role: RoleName
  status: UserStatus
  must_change_password: boolean
  branch_ids: number[]
}

export function useUsers(filters: UserFilters) {
  return useQuery({
    queryKey: ['users', filters],
    queryFn: async () => (await api.get<Paginated<User>>('/users', { params: filters })).data,
    placeholderData: keepPreviousData,
  })
}

export function useSaveUser() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, ...input }: UserInput & { id?: number }) =>
      id ? (await api.put(`/users/${id}`, input)).data : (await api.post('/users', input)).data,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['users'] }),
  })
}

export function useRoles() {
  return useQuery({
    queryKey: ['roles'],
    queryFn: async () => (await api.get<{ data: RoleInfo[] }>('/roles')).data.data,
    staleTime: 5 * 60_000,
  })
}

export function usePermissionCatalog(enabled: boolean) {
  return useQuery({
    queryKey: ['permissions'],
    queryFn: async () => (await api.get<{ data: Record<string, string[]> }>('/permissions')).data.data,
    enabled,
    staleTime: Infinity,
  })
}

export function useSaveRolePermissions() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ role, permissions }: { role: RoleName; permissions: string[] }) =>
      (await api.put(`/roles/${role}`, { permissions })).data,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['roles'] }),
  })
}
