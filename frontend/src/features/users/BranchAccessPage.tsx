import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Star } from 'lucide-react'
import { useState } from 'react'
import { api, errorMessage, validationErrors } from '../../api/client'
import { Alert, Badge, Card, PageHeader } from '../../components/ui/Card'
import { Spinner } from '../../components/ui/Spinner'
import { useAuth } from '../../contexts/useAuth'
import { cn } from '../../utils/cn'

interface AccessData {
  branches: { id: number; name: string; code: string }[]
  users: { id: number; name: string; email: string | null; status: string; roles: string[]; all_branches: boolean; branch_ids: number[]; primary_id: number | null }[]
}

/**
 * Users → Branch Access: tick the branches each staff member works in. They see only those branches'
 * children, appointments and money. The star is their main branch (used as the default on forms).
 */
export default function BranchAccessPage() {
  const { can } = useAuth()
  const qc = useQueryClient()
  const [error, setError] = useState<string | null>(null)
  const { data, isLoading } = useQuery({ queryKey: ['branch-access'], queryFn: async () => (await api.get<AccessData>('/branch-access')).data })
  const save = useMutation({
    mutationFn: ({ id, ...body }: { id: number; branch_ids: number[]; primary_id: number | null }) => api.put(`/branch-access/${id}`, body),
    onSuccess: () => (setError(null), qc.invalidateQueries({ queryKey: ['branch-access'] })),
    onError: (e) => setError(Object.values(validationErrors(e))[0] ?? errorMessage(e)),
  })
  const editable = can('users.manage')

  const toggle = (u: AccessData['users'][number], branchId: number) => {
    const ids = u.branch_ids.includes(branchId) ? u.branch_ids.filter((b) => b !== branchId) : [...u.branch_ids, branchId]
    save.mutate({ id: u.id, branch_ids: ids, primary_id: ids.includes(u.primary_id ?? -1) ? u.primary_id : (ids[0] ?? null) })
  }

  return (
    <>
      <PageHeader title="Branch Access" description="Which branches each staff member works in. Super Admins always see every branch. Every change is recorded in the activity log." />
      {error && (
        <div className="mb-4">
          <Alert>{error}</Alert>
        </div>
      )}
      {isLoading || !data ? (
        <Spinner className="text-brand-600" />
      ) : (
        <Card className="overflow-x-auto">
          <table className="w-full min-w-[640px] text-sm">
            <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
              <tr>
                <th className="px-4 py-2.5 text-left font-medium">Staff</th>
                {data.branches.map((b) => (
                  <th key={b.id} className="px-3 py-2.5 text-center font-medium">
                    {b.name}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {data.users.map((u) => (
                <tr key={u.id} className={cn(u.status !== 'active' && 'opacity-50')}>
                  <td className="px-4 py-2">
                    <p className="font-medium text-slate-900">{u.name}</p>
                    <p className="text-xs text-slate-500">{u.roles.map((r) => r.replace('_', ' ')).join(', ')}</p>
                  </td>
                  {data.branches.map((b) => {
                    const on = u.branch_ids.includes(b.id)
                    return (
                      <td key={b.id} className="px-3 py-2 text-center">
                        {u.all_branches ? (
                          <Badge tone="green">all</Badge>
                        ) : (
                          <span className="inline-flex items-center gap-1.5">
                            <input
                              type="checkbox"
                              className="size-4 accent-brand-600"
                              checked={on}
                              disabled={!editable || save.isPending || (on && u.branch_ids.length === 1)}
                              onChange={() => toggle(u, b.id)}
                              aria-label={`${u.name} works at ${b.name}`}
                            />
                            {on && (
                              <button
                                disabled={!editable || u.primary_id === b.id}
                                onClick={() => save.mutate({ id: u.id, branch_ids: u.branch_ids, primary_id: b.id })}
                                aria-label={`Make ${b.name} ${u.name}'s main branch`}
                                title={u.primary_id === b.id ? 'Main branch' : 'Make main branch'}
                              >
                                <Star className={cn('size-3.5', u.primary_id === b.id ? 'fill-amber-400 text-amber-500' : 'text-slate-300 hover:text-amber-400')} />
                              </button>
                            )}
                          </span>
                        )}
                      </td>
                    )
                  })}
                </tr>
              ))}
            </tbody>
          </table>
        </Card>
      )}
    </>
  )
}
