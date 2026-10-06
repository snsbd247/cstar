export type RoleName =
  | 'super_admin'
  | 'branch_admin'
  | 'receptionist'
  | 'trainer'
  | 'therapist'
  | 'accountant'
  | 'parent'

export type UserStatus = 'active' | 'inactive' | 'suspended'

export interface Branch {
  id: number
  code: string
  name: string
  name_bn: string | null
  slug: string
  address: string | null
  phone: string | null
  email: string | null
  map_url: string | null
  opening_hours: Record<string, string | null> | null
  is_active: boolean
  show_on_website: boolean
  sort_order: number
  is_primary?: boolean
  users_count?: number
}

export interface User {
  id: number
  name: string
  email: string | null
  phone: string | null
  user_type: 'staff' | 'parent'
  status: UserStatus
  must_change_password: boolean
  roles: RoleName[]
  primary_role: RoleName | null
  primary_role_label: string | null
  home_path: string
  branches?: Branch[]
  last_login_at: string | null
  created_at: string
}

export interface AuthUser extends User {
  is_super_admin: boolean
  permissions: string[]
}

export interface RoleInfo {
  name: RoleName
  label: string
  editable: boolean
  assignable_by_branch_admin: boolean
  permissions: string[]
}

export interface Paginated<T> {
  data: T[]
  meta: { current_page: number; last_page: number; per_page: number; total: number; from: number | null; to: number | null }
}
