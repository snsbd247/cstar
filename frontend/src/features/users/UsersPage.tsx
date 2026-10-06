import { ChevronLeft, ChevronRight, Pencil, Plus, Search } from 'lucide-react'
import { useEffect, useState } from 'react'
import { useSearchParams } from 'react-router'
import { useForm } from 'react-hook-form'
import { errorMessage, validationErrors } from '../../api/client'
import { Button } from '../../components/ui/Button'
import { Alert, Badge, Card, PageHeader } from '../../components/ui/Card'
import { Field, Input, Select } from '../../components/ui/Field'
import { Modal } from '../../components/ui/Modal'
import { Spinner } from '../../components/ui/Spinner'
import { useAuth } from '../../contexts/useAuth'
import type { RoleName, User } from '../../types'
import { useBranches } from '../branches/api'
import { useRoles, useSaveUser, useUsers, type UserFilters, type UserInput } from './api'

const statusTone = { active: 'green', inactive: 'gray', suspended: 'red' } as const

export default function UsersPage() {
  const { can } = useAuth()
  const [filters, setFilters] = useState<UserFilters>({ page: 1 })
  const [search, setSearch] = useState('')
  const [params, setParams] = useSearchParams()
  const [picked, setPicked] = useState<User | 'new' | null>(null)
  // The menu's "Add" link opens the form through ?new=1.
  const editing = picked ?? (params.get('new') && can('users.manage') ? 'new' : null)
  const setEditing = (row: User | 'new' | null) => {
    setPicked(row)
    if (!row && params.has('new')) setParams({}, { replace: true })
  }
  const { data, isLoading, isFetching } = useUsers(filters)
  const { data: roles } = useRoles()
  const { data: branches } = useBranches()

  // Debounced search
  useEffect(() => {
    const t = setTimeout(() => setFilters((f) => (f.search === search ? f : { ...f, search: search || undefined, page: 1 })), 350)
    return () => clearTimeout(t)
  }, [search])

  return (
    <>
      <PageHeader
        title="Users"
        description="Staff and parent login accounts. Each user has one role and one or more branches."
        actions={
          can('users.manage') && (
            <Button onClick={() => setEditing('new')}>
              <Plus className="size-4" /> New user
            </Button>
          )
        }
      />

      <Card className="mb-4 grid gap-3 p-3 sm:grid-cols-4">
        <div className="relative sm:col-span-2">
          <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
          <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search name, email or mobile" className="pl-9" aria-label="Search users" />
        </div>
        <Select value={filters.role ?? ''} onChange={(e) => setFilters({ ...filters, role: e.target.value || undefined, page: 1 })} aria-label="Filter by role">
          <option value="">All roles</option>
          {roles?.map((r) => (
            <option key={r.name} value={r.name}>
              {r.label}
            </option>
          ))}
        </Select>
        <Select value={filters.branch_id ?? ''} onChange={(e) => setFilters({ ...filters, branch_id: e.target.value || undefined, page: 1 })} aria-label="Filter by branch">
          <option value="">All branches</option>
          {branches?.map((b) => (
            <option key={b.id} value={b.id}>
              {b.name}
            </option>
          ))}
        </Select>
      </Card>

      <Card className="overflow-hidden">
        {isLoading ? (
          <div className="p-6">
            <Spinner className="text-brand-600" />
          </div>
        ) : !data?.data.length ? (
          <p className="p-6 text-sm text-slate-500">No users found.</p>
        ) : (
          <ul className={`divide-y divide-slate-100 ${isFetching ? 'opacity-60' : ''}`}>
            {data.data.map((u) => (
              <li key={u.id} className="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:gap-4">
                <div className="min-w-0 flex-1">
                  <p className="truncate font-medium text-slate-900">{u.name}</p>
                  <p className="truncate text-sm text-slate-500">{[u.email, u.phone].filter(Boolean).join(' · ')}</p>
                </div>
                <div className="flex flex-wrap items-center gap-2 sm:w-80 sm:justify-end">
                  <Badge tone="blue">{u.primary_role_label}</Badge>
                  {u.branches?.map((b) => (
                    <Badge key={b.id}>{b.code}</Badge>
                  ))}
                  <Badge tone={statusTone[u.status]}>{u.status}</Badge>
                </div>
                {can('users.manage') && (
                  <Button variant="ghost" className="self-start sm:self-auto" onClick={() => setEditing(u)} aria-label={`Edit ${u.name}`}>
                    <Pencil className="size-4" />
                  </Button>
                )}
              </li>
            ))}
          </ul>
        )}
        {data && data.meta.last_page > 1 && (
          <div className="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-sm text-slate-500">
            <span>
              {data.meta.from}–{data.meta.to} of {data.meta.total}
            </span>
            <div className="flex gap-1">
              <Button variant="secondary" disabled={data.meta.current_page <= 1} onClick={() => setFilters({ ...filters, page: data.meta.current_page - 1 })} aria-label="Previous page">
                <ChevronLeft className="size-4" />
              </Button>
              <Button variant="secondary" disabled={data.meta.current_page >= data.meta.last_page} onClick={() => setFilters({ ...filters, page: data.meta.current_page + 1 })} aria-label="Next page">
                <ChevronRight className="size-4" />
              </Button>
            </div>
          </div>
        )}
      </Card>

      {editing && <UserForm user={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />}
    </>
  )
}

function UserForm({ user, onClose }: { user: User | null; onClose: () => void }) {
  const { user: me } = useAuth()
  const { data: roles } = useRoles()
  const { data: branches } = useBranches()
  const save = useSaveUser()
  const [error, setError] = useState<string | null>(null)
  const { register, handleSubmit, setError: setFieldError, formState } = useForm<UserInput>({
    defaultValues: {
      name: user?.name ?? '',
      email: user?.email ?? '',
      phone: user?.phone ?? '',
      password: '',
      role: user?.primary_role ?? 'receptionist',
      status: user?.status ?? 'active',
      must_change_password: user ? user.must_change_password : true,
      branch_ids: user?.branches?.map((b) => b.id) ?? (branches?.length === 1 ? [branches[0].id] : []),
    },
  })
  const errors = formState.errors
  const assignableRoles = roles?.filter((r) => me?.is_super_admin || r.assignable_by_branch_admin) ?? []

  const onSubmit = async (values: UserInput) => {
    setError(null)
    try {
      await save.mutateAsync({
        ...values,
        id: user?.id,
        email: values.email || null,
        phone: values.phone || null,
        password: values.password || undefined,
        branch_ids: (values.branch_ids ?? []).map(Number),
      })
      onClose()
    } catch (e) {
      const fields = validationErrors(e)
      Object.entries(fields).forEach(([name, message]) =>
        setFieldError((name.startsWith('branch_ids') ? 'branch_ids' : name) as keyof UserInput, { message }),
      )
      if (!Object.keys(fields).length) setError(errorMessage(e))
    }
  }

  return (
    <Modal open title={user ? `Edit ${user.name}` : 'New user'} onClose={onClose}>
      <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
        {error && <Alert>{error}</Alert>}
        <Field label="Full name" htmlFor="name" error={errors.name?.message}>
          <Input id="name" {...register('name', { required: 'Required' })} />
        </Field>
        <div className="grid gap-3 sm:grid-cols-2">
          <Field label="Mobile" htmlFor="phone" hint="01XXXXXXXXX" error={errors.phone?.message}>
            <Input id="phone" inputMode="tel" {...register('phone')} />
          </Field>
          <Field label="Email" htmlFor="email" hint="Optional for parents" error={errors.email?.message}>
            <Input id="email" type="email" {...register('email')} />
          </Field>
        </div>
        <div className="grid gap-3 sm:grid-cols-2">
          <Field label="Role" htmlFor="role" error={errors.role?.message}>
            <Select id="role" {...register('role')}>
              {assignableRoles.map((r) => (
                <option key={r.name} value={r.name as RoleName}>
                  {r.label}
                </option>
              ))}
            </Select>
          </Field>
          <Field label="Status" htmlFor="status" error={errors.status?.message}>
            <Select id="status" {...register('status')}>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
              <option value="suspended">Suspended</option>
            </Select>
          </Field>
        </div>
        <Field label="Branches" error={errors.branch_ids?.message}>
          <div className="flex flex-wrap gap-x-5 gap-y-2 rounded-lg border border-slate-200 p-3">
            {branches?.map((b) => (
              <label key={b.id} className="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" value={b.id} className="size-4" {...register('branch_ids')} /> {b.name}
              </label>
            ))}
          </div>
        </Field>
        <Field
          label={user ? 'New password' : 'Password'}
          htmlFor="password"
          hint={user ? 'Leave empty to keep the current password' : 'At least 8 characters with letters and numbers'}
          error={errors.password?.message}
        >
          <Input id="password" type="password" autoComplete="new-password" {...register('password')} />
        </Field>
        <label className="flex items-center gap-2 text-sm text-slate-700">
          <input type="checkbox" className="size-4" {...register('must_change_password')} /> Ask to change password at next login
        </label>
        <div className="flex justify-end gap-2 pt-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" loading={formState.isSubmitting}>
            Save
          </Button>
        </div>
      </form>
    </Modal>
  )
}
