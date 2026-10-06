import { createContext, useContext } from 'react'
import type { AuthUser, RoleName } from '../types'

export interface AuthContextValue {
  user: AuthUser | null
  isLoading: boolean
  login: (login: string, password: string, remember: boolean) => Promise<AuthUser>
  logout: () => Promise<void>
  refresh: () => Promise<void>
  /** UI hint only — the API enforces every permission itself. */
  can: (permission: string) => boolean
  canAny: (...permissions: string[]) => boolean
  hasRole: (...roles: RoleName[]) => boolean
}

export const AuthContext = createContext<AuthContextValue | null>(null)

export function useAuth() {
  const context = useContext(AuthContext)
  if (!context) throw new Error('useAuth must be used inside <AuthProvider>')
  return context
}
