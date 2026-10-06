import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '../../api/client'
import type { Branch } from '../../types'

export type BranchInput = Pick<Branch, 'code' | 'name' | 'name_bn' | 'address' | 'phone' | 'email' | 'map_url' | 'is_active' | 'show_on_website'>

const KEY = ['branches']

export function useBranches(enabled = true) {
  return useQuery({
    queryKey: KEY,
    queryFn: async () => (await api.get<{ data: Branch[] }>('/branches')).data.data,
    enabled,
  })
}

export function useSaveBranch() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, ...input }: BranchInput & { id?: number }) =>
      id ? (await api.put(`/branches/${id}`, input)).data : (await api.post('/branches', input)).data,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: KEY }),
  })
}
