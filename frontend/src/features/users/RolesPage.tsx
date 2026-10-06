import { Check, Lock } from 'lucide-react'
import { useState } from 'react'
import { useSearchParams } from 'react-router'
import { errorMessage } from '../../api/client'
import { Button } from '../../components/ui/Button'
import { Alert, Badge, Card, PageHeader } from '../../components/ui/Card'
import { Spinner } from '../../components/ui/Spinner'
import { useAuth } from '../../contexts/useAuth'
import type { RoleInfo } from '../../types'
import { cn } from '../../utils/cn'
import { UrlTabs } from '../../components/ui/Tabs'
import { usePermissionCatalog, useRoles, useSaveRolePermissions } from './api'
import { PermissionMatrix } from './PermissionMatrix'

export default function RolesPage() {
  const { can } = useAuth()
  const [params] = useSearchParams()
  const { data: roles, isLoading } = useRoles()
  const { data: catalog } = usePermissionCatalog(can('roles.manage'))
  const [selected, setSelected] = useState<string>('branch_admin')

  if (isLoading || !roles) return <Spinner className="text-brand-600" />
  const tabs = (
    <UrlTabs
      tabs={[
        ['roles', 'Roles'],
        ['permissions', 'Permission matrix'],
      ]}
      fallback="roles"
    />
  )

  if (params.get('tab') === 'permissions') {
    return (
      <>
        <PageHeader title="Permissions" description="Who can do what, by role. Record-level rules (own branch, assigned children, own child) always apply on top." />
        {tabs}
        <PermissionMatrix roles={roles} catalog={catalog} />
      </>
    )
  }

  const role = roles.find((r) => r.name === selected) ?? roles[0]

  return (
    <>
      <PageHeader title="Roles & Permissions" description="What each role can do. Record-level rules (own branch, assigned children, own child) always apply on top." />
      {tabs}

      <div className="grid gap-4 lg:grid-cols-[240px_1fr]">
        <Card className="h-fit p-2">
          <ul className="flex gap-1 overflow-x-auto lg:flex-col">
            {roles.map((r) => (
              <li key={r.name}>
                <button
                  onClick={() => setSelected(r.name)}
                  className={cn(
                    'flex w-full items-center justify-between gap-3 whitespace-nowrap rounded-lg px-3 py-2 text-left text-sm',
                    role.name === r.name ? 'bg-brand-50 font-medium text-brand-700' : 'text-slate-600 hover:bg-slate-50',
                  )}
                >
                  {r.label}
                  <span className="text-xs text-slate-400">{r.name === 'super_admin' ? 'all' : r.permissions.length}</span>
                </button>
              </li>
            ))}
          </ul>
        </Card>

        {/* Keyed so the draft resets whenever another role is picked. */}
        <RoleEditor key={role.name} role={role} />
      </div>
    </>
  )
}

function RoleEditor({ role }: { role: RoleInfo }) {
  const { can } = useAuth()
  const editable = can('roles.manage') && role.editable
  const { data: catalog } = usePermissionCatalog(can('roles.manage'))
  const save = useSaveRolePermissions()
  const [draft, setDraft] = useState(() => new Set(role.permissions))
  const [message, setMessage] = useState<{ tone: 'green' | 'red'; text: string } | null>(null)

  // Without roles.manage the catalogue endpoint is closed; group the role's own permissions instead.
  const groups =
    catalog ??
    role.permissions.reduce<Record<string, string[]>>((acc, p) => {
      ;(acc[p.split('.')[0]] ??= []).push(p)
      return acc
    }, {})

  const toggle = (permission: string) =>
    setDraft((d) => {
      const next = new Set(d)
      if (next.has(permission)) next.delete(permission)
      else next.add(permission)
      return next
    })

  const onSave = async () => {
    try {
      await save.mutateAsync({ role: role.name, permissions: [...draft] })
      setMessage({ tone: 'green', text: `${role.label} permissions saved.` })
    } catch (e) {
      setMessage({ tone: 'red', text: errorMessage(e) })
    }
  }

  return (
    <Card className="p-5">
      <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-2">
          <h2 className="font-semibold text-slate-900">{role.label}</h2>
          {!editable && (
            <Badge>
              <Lock className="mr-1 size-3" /> Read only
            </Badge>
          )}
        </div>
        {editable && (
          <Button onClick={onSave} loading={save.isPending}>
            Save changes
          </Button>
        )}
      </div>
      {message && (
        <div className="mb-4">
          <Alert tone={message.tone}>{message.text}</Alert>
        </div>
      )}
      {role.name === 'super_admin' ? (
        <p className="text-sm text-slate-500">Super Admin has full access to every module and cannot be restricted.</p>
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          {Object.entries(groups).map(([module, permissions]) => (
            <div key={module} className="rounded-lg border border-slate-100 p-3">
              <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">{module.replace(/_/g, ' ')}</p>
              <ul className="space-y-1.5">
                {permissions.map((p) => (
                  <li key={p}>
                    <label className={cn('flex items-center gap-2 text-sm', draft.has(p) ? 'text-slate-800' : 'text-slate-400')}>
                      {editable ? (
                        <input type="checkbox" className="size-4" checked={draft.has(p)} onChange={() => toggle(p)} />
                      ) : (
                        <Check className={cn('size-4', draft.has(p) ? 'text-brand-600' : 'invisible')} />
                      )}
                      {p.split('.').slice(1).join(' ').replace(/_/g, ' ') || p}
                    </label>
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </div>
      )}
    </Card>
  )
}
