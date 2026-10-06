import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Pencil, Plus } from 'lucide-react'
import { useState } from 'react'
import { api, errorMessage, validationErrors } from '../../api/client'
import { Button } from '../../components/ui/Button'
import { Alert, Badge, Card, PageHeader } from '../../components/ui/Card'
import { Field, Input, Select } from '../../components/ui/Field'
import { Modal } from '../../components/ui/Modal'
import { Spinner } from '../../components/ui/Spinner'
import { useAuth } from '../../contexts/useAuth'
import { cn } from '../../utils/cn'
import { useBranches } from './api'

interface Room {
  id: number
  branch_id: number
  branch: string
  name: string
  type: 'class' | 'therapy' | 'assessment' | 'other'
  capacity: number | null
  is_active: boolean
  classes: string[]
}

const roomType = { class: 'Class room', therapy: 'Therapy room', assessment: 'Assessment room', other: 'Other' }

/** Branches → Rooms: class and therapy rooms of each branch. */
export function RoomsPage() {
  const { can } = useAuth()
  const [editing, setEditing] = useState<Room | 'new' | null>(null)
  const { data, isLoading } = useQuery({ queryKey: ['rooms'], queryFn: async () => (await api.get<{ data: Room[] }>('/rooms')).data.data })
  const byBranch = Object.entries((data ?? []).reduce<Record<string, Room[]>>((acc, r) => ({ ...acc, [r.branch]: [...(acc[r.branch] ?? []), r] }), {}))

  return (
    <>
      <PageHeader
        title="Rooms"
        description="Rooms each branch has. A class can be given its room on the class page."
        actions={
          can('branches.manage') && (
            <Button onClick={() => setEditing('new')}>
              <Plus className="size-4" /> New room
            </Button>
          )
        }
      />
      {isLoading ? (
        <Spinner className="text-brand-600" />
      ) : !byBranch.length ? (
        <Card className="p-5 text-sm text-slate-500">No rooms yet.</Card>
      ) : (
        <div className="space-y-4">
          {byBranch.map(([branch, rooms]) => (
            <Card key={branch} className="overflow-hidden">
              <p className="bg-slate-50 px-4 py-2 text-sm font-semibold text-slate-700">{branch}</p>
              <ul className="divide-y divide-slate-100">
                {rooms.map((r) => (
                  <li key={r.id} className={cn('flex flex-wrap items-center justify-between gap-2 px-4 py-2.5 text-sm', !r.is_active && 'opacity-50')}>
                    <div>
                      <p className="font-medium text-slate-900">{r.name}</p>
                      <p className="text-xs text-slate-500">
                        {roomType[r.type]}
                        {r.capacity && ` · up to ${r.capacity}`}
                        {r.classes.length > 0 && ` · ${r.classes.join(', ')}`}
                      </p>
                    </div>
                    <span className="flex items-center gap-2">
                      {!r.is_active && <Badge>not in use</Badge>}
                      {can('branches.manage') && (
                        <button onClick={() => setEditing(r)} aria-label={`Edit ${r.name}`} className="text-slate-400 hover:text-slate-700">
                          <Pencil className="size-4" />
                        </button>
                      )}
                    </span>
                  </li>
                ))}
              </ul>
            </Card>
          ))}
        </div>
      )}
      {editing && <RoomModal room={editing === 'new' ? undefined : editing} onClose={() => setEditing(null)} />}
    </>
  )
}

function RoomModal({ room, onClose }: { room?: Room; onClose: () => void }) {
  const qc = useQueryClient()
  const { data: branches } = useBranches()
  const [v, setV] = useState({ branch_id: String(room?.branch_id ?? ''), name: room?.name ?? '', type: room?.type ?? 'therapy', capacity: String(room?.capacity ?? ''), is_active: room?.is_active ?? true })
  const [errors, setErrors] = useState<Record<string, string>>({})
  const save = useMutation({
    mutationFn: () => {
      const body = { ...v, branch_id: Number(v.branch_id), capacity: v.capacity ? Number(v.capacity) : null }
      return room ? api.put(`/rooms/${room.id}`, body) : api.post('/rooms', body)
    },
    onSuccess: () => (qc.invalidateQueries({ queryKey: ['rooms'] }), onClose()),
    onError: (e) => setErrors(validationErrors(e)),
  })

  return (
    <Modal open title={room ? `Edit ${room.name}` : 'New room'} onClose={onClose}>
      <div className="space-y-3">
        {save.isError && !Object.keys(errors).length && <Alert>{errorMessage(save.error)}</Alert>}
        <Field label="Branch" htmlFor="rm_branch" error={errors.branch_id}>
          <Select id="rm_branch" value={v.branch_id} onChange={(e) => setV({ ...v, branch_id: e.target.value })}>
            <option value="">Choose…</option>
            {branches?.map((b) => (
              <option key={b.id} value={b.id}>
                {b.name}
              </option>
            ))}
          </Select>
        </Field>
        <div className="grid gap-3 sm:grid-cols-3">
          <Field label="Name" htmlFor="rm_name" error={errors.name}>
            <Input id="rm_name" value={v.name} onChange={(e) => setV({ ...v, name: e.target.value })} placeholder="Therapy room 2" />
          </Field>
          <Field label="Kind" htmlFor="rm_type">
            <Select id="rm_type" value={v.type} onChange={(e) => setV({ ...v, type: e.target.value as Room['type'] })}>
              {Object.entries(roomType).map(([k, l]) => (
                <option key={k} value={k}>
                  {l}
                </option>
              ))}
            </Select>
          </Field>
          <Field label="Capacity" htmlFor="rm_cap" error={errors.capacity}>
            <Input id="rm_cap" type="number" value={v.capacity} onChange={(e) => setV({ ...v, capacity: e.target.value })} />
          </Field>
        </div>
        <label className="flex items-center gap-2 text-sm text-slate-700">
          <input type="checkbox" className="size-4 accent-brand-600" checked={v.is_active} onChange={(e) => setV({ ...v, is_active: e.target.checked })} /> In use
        </label>
        <div className="flex justify-end gap-2">
          <Button variant="ghost" onClick={onClose}>
            Cancel
          </Button>
          <Button loading={save.isPending} disabled={!v.branch_id || !v.name} onClick={() => save.mutate()}>
            Save
          </Button>
        </div>
      </div>
    </Modal>
  )
}

/** Branches → Services by Branch: what each branch can give, from who works there. */
export function ServicesByBranchPage() {
  const { data, isLoading } = useQuery({
    queryKey: ['branches-services'],
    queryFn: async () =>
      (
        await api.get<{
          data: {
            services: { id: number; name: string; category: string }[]
            branches: { branch: { id: number; name: string; code: string }; cells: Record<string, { count: number; unit: string }> }[]
          }
        }>('/branches-overview/services')
      ).data.data,
  })

  return (
    <>
      <PageHeader title="Services by Branch" description="A therapy service is available at a branch when a therapist who gives it works there; training when a class runs there." />
      {isLoading || !data ? (
        <Spinner className="text-brand-600" />
      ) : (
        <Card className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
              <tr>
                <th className="px-4 py-2.5 font-medium">Service</th>
                {data.branches.map((b) => (
                  <th key={b.branch.id} className="px-4 py-2.5 text-center font-medium">
                    {b.branch.name}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {data.services.map((s) => (
                <tr key={s.id}>
                  <td className="px-4 py-2.5">
                    <p className="font-medium text-slate-900">{s.name}</p>
                    <p className="text-xs capitalize text-slate-500">{s.category}</p>
                  </td>
                  {data.branches.map((b) => {
                    const c = b.cells[s.id]
                    return (
                      <td key={b.branch.id} className="px-4 py-2.5 text-center">
                        {c?.count ? (
                          <Badge tone="green">
                            {c.count} {c.unit}
                          </Badge>
                        ) : (
                          <span className="text-xs text-slate-400">not offered</span>
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

interface BranchStaff {
  branch: { id: number; name: string; code: string }
  employees: Record<string, { id: number; name: string; designation: string | null }[]>
  therapists: { id: number; name: string; designation: string | null }[]
  trainers: { id: number; name: string }[]
  users: { id: number; name: string; status: string; roles: string[] }[]
}

const deptLabel: Record<string, string> = { therapist: 'Therapists', trainer: 'Trainers', admin: 'Admin & front desk', support: 'Support staff' }

/** Branches → Staff by Branch: payroll staff, clinical profiles and login accounts per branch. */
export function StaffByBranchPage() {
  const { data, isLoading } = useQuery({ queryKey: ['branches-staff'], queryFn: async () => (await api.get<{ data: BranchStaff[] }>('/branches-overview/staff')).data.data })

  return (
    <>
      <PageHeader title="Staff by Branch" description="Who works where: employees on the payroll, therapist and trainer profiles, and who can sign in for each branch." />
      {isLoading ? (
        <Spinner className="text-brand-600" />
      ) : (
        <div className="grid gap-4 lg:grid-cols-2">
          {data?.map((b) => (
            <Card key={b.branch.id} className="p-4">
              <h2 className="font-semibold text-slate-900">
                {b.branch.name} <span className="font-mono text-xs text-slate-500">{b.branch.code}</span>
              </h2>
              <div className="mt-3 grid gap-3 sm:grid-cols-2">
                {Object.entries(b.employees).map(([dept, people]) => (
                  <div key={dept}>
                    <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                      {deptLabel[dept] ?? dept} ({people.length})
                    </p>
                    <ul className="mt-1 text-sm text-slate-700">
                      {people.map((p) => (
                        <li key={p.id}>
                          {p.name}
                          {p.designation && <span className="text-xs text-slate-500"> · {p.designation}</span>}
                        </li>
                      ))}
                    </ul>
                  </div>
                ))}
                <div>
                  <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Sign-in accounts ({b.users.length})</p>
                  <ul className="mt-1 text-sm text-slate-700">
                    {b.users.map((u) => (
                      <li key={u.id} className={u.status !== 'active' ? 'opacity-50' : ''}>
                        {u.name} <span className="text-xs text-slate-500">· {u.roles.map((r) => r.replace('_', ' ')).join(', ')}</span>
                      </li>
                    ))}
                  </ul>
                </div>
              </div>
              <p className="mt-3 text-xs text-slate-500">
                {b.therapists.length} therapist profile(s) · {b.trainers.length} trainer profile(s) based here
              </p>
            </Card>
          ))}
        </div>
      )}
    </>
  )
}
