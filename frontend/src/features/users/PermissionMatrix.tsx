import { Check } from 'lucide-react'
import { Card } from '../../components/ui/Card'
import type { RoleInfo } from '../../types'

/** Users → Permissions: every permission (rows) against every role (columns). Edit on the Roles tab. */
export function PermissionMatrix({ roles, catalog }: { roles: RoleInfo[]; catalog?: Record<string, string[]> }) {
  const groups =
    catalog ??
    [...new Set(roles.flatMap((r) => r.permissions))].sort().reduce<Record<string, string[]>>((acc, p) => {
      ;(acc[p.split('.')[0]] ??= []).push(p)
      return acc
    }, {})

  return (
    <Card className="overflow-x-auto">
      <table className="w-full min-w-[760px] text-sm">
        <thead className="sticky top-0 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
          <tr>
            <th className="px-4 py-2.5 text-left font-medium">Permission</th>
            {roles.map((r) => (
              <th key={r.name} className="px-2 py-2.5 text-center font-medium">
                {r.label}
              </th>
            ))}
          </tr>
        </thead>
        {Object.entries(groups).map(([module, permissions]) => (
          <tbody key={module} className="divide-y divide-slate-100">
            <tr className="bg-slate-50/60">
              <td colSpan={roles.length + 1} className="px-4 py-1.5 text-xs font-semibold uppercase tracking-wide text-slate-400">
                {module.replace(/_/g, ' ')}
              </td>
            </tr>
            {permissions.map((p) => (
              <tr key={p}>
                <td className="px-4 py-1.5 text-slate-700">{p.split('.').slice(1).join(' ').replace(/_/g, ' ') || p}</td>
                {roles.map((r) => (
                  <td key={r.name} className="px-2 py-1.5 text-center">
                    {r.name === 'super_admin' || r.permissions.includes(p) ? <Check className="mx-auto size-4 text-brand-600" aria-label="allowed" /> : <span className="text-slate-200">·</span>}
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        ))}
      </table>
    </Card>
  )
}
