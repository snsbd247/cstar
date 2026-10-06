import { useQuery, useQueryClient } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import { useCallback, useMemo, type ReactNode } from 'react'
import { api, fetchCsrfCookie } from '../api/client'
import type { AuthUser } from '../types'
import { AuthContext, type AuthContextValue } from './useAuth'

const ME_KEY = ['auth', 'me']

async function fetchMe(): Promise<AuthUser | null> {
  try {
    const { data } = await api.get<{ data: AuthUser }>('/auth/me')
    return data.data
  } catch (error) {
    if (error instanceof AxiosError && [401, 403, 419].includes(error.response?.status ?? 0)) return null
    throw error
  }
}

export function AuthProvider({ children }: { children: ReactNode }) {
  const queryClient = useQueryClient()
  const { data: user = null, isLoading } = useQuery({ queryKey: ME_KEY, queryFn: fetchMe, staleTime: Infinity, retry: false })

  const login = useCallback(
    async (login: string, password: string, remember: boolean) => {
      await fetchCsrfCookie()
      const { data } = await api.post<{ data: AuthUser }>('/auth/login', { login, password, remember })
      queryClient.setQueryData(ME_KEY, data.data)
      return data.data
    },
    [queryClient],
  )

  const logout = useCallback(async () => {
    try {
      await api.post('/auth/logout')
    } finally {
      queryClient.clear()
      queryClient.setQueryData(ME_KEY, null)
    }
  }, [queryClient])

  const refresh = useCallback(async () => {
    await queryClient.invalidateQueries({ queryKey: ME_KEY })
  }, [queryClient])

  const value = useMemo<AuthContextValue>(() => {
    const can = (permission: string) => !!user && (user.is_super_admin || user.permissions.includes(permission))
    return {
      user,
      isLoading,
      login,
      logout,
      refresh,
      can,
      canAny: (...permissions) => permissions.some(can),
      hasRole: (...roles) => !!user && user.roles.some((r) => roles.includes(r)),
    }
  }, [user, isLoading, login, logout, refresh])

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}
