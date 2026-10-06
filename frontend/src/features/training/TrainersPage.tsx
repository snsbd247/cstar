import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Pencil, Plus } from 'lucide-react'
import { useState, type FormEvent } from 'react'
import { api, errorMessage, validationErrors } from '../../api/client'
import { Button } from '../../components/ui/Button'
import { Alert, Badge, Card, PageHeader } from '../../components/ui/Card'
import { Field, Input, Select } from '../../components/ui/Field'
import { Modal } from '../../components/ui/Modal'
import { Spinner } from '../../components/ui/Spinner'
import { useAuth } from '../../contexts/useAuth'
import type { User } from '../../types'

interface TrainerRow {
  id: number
  name: string
  phone: string | null
  email: string | null
  qualification: string | null
  experience_years: number | null
  status: 'active' | 'inactive'
  employee_code: string | null
  classes_count: number
  user_id: number | null
  branch: { id: number; name: string } | null
  login: string | null
}

/** Trainers only — therapists are separate staff (TRAINER ≠ THERAPIST). */
export default function TrainersPage() {
  const { can } = useAuth()
  const { data, isLoading } = useQuery({ queryKey: ['trainers'], queryFn: async () => (await api.get<{ data: TrainerRow[] }>('/trainers')).data.data })
  const [editing, setEditing] = useState<TrainerRow | 'new' | null>(null)

  return (
    <>
      <PageHeader
        title="Trainers"
        description="Run regular classes and daily functional training. Therapists are managed separately."
        actions={
          can('trainers.manage') && (
            <Button onClick={() => setEditing('new')}>
              <Plus className="size-4" /> New trainer
            </Button>
          )
        }
      />
      {isLoading ? (
        <Spinner className="text-brand-600" />
      ) : (
        <Card className="overflow-hidden">
          <ul className="divide-y divide-slate-100">
            {data?.map((t) => (
              <li key={t.id} className="flex flex-wrap items-center gap-3 px-4 py-3">
                <span className="flex size-10 items-center justify-center rounded-full bg-brand-100 font-semibold text-brand-700">{t.name[0]}</span>
                <div className="min-w-0 flex-1">
                  <p className="font-medium text-slate-900">{t.name}</p>
                  <p className="text-sm text-slate-500">{[t.qualification, t.branch?.name, t.phone].filter(Boolean).join(' · ')}</p>
                </div>
                <Badge tone="blue">{t.classes_count} classes</Badge>
                {t.login ? <Badge tone="green">Login: {t.login}</Badge> : <Badge>No login</Badge>}
                <Badge tone={t.status === 'active' ? 'green' : 'gray'}>{t.status}</Badge>
                {can('trainers.manage') && (
                  <button onClick={() => setEditing(t)} className="rounded-md p-2 text-slate-500 hover:bg-slate-100" aria-label={`Edit ${t.name}`}>
                    <Pencil className="size-4" />
                  </button>
                )}
              </li>
            ))}
          </ul>
        </Card>
      )}
      {editing && <TrainerForm trainer={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />}
    </>
  )
}

function TrainerForm({ trainer, onClose }: { trainer: TrainerRow | null; onClose: () => void }) {
  const { user } = useAuth()
  const qc = useQueryClient()
  const { data: trainerLogins } = useQuery({
    queryKey: ['users', 'trainer-role'],
    queryFn: async () => (await api.get<{ data: User[] }>('/users', { params: { role: 'trainer', per_page: 100 } })).data.data,
  })
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [error, setError] = useState<string | null>(null)
  const save = useMutation({
    mutationFn: (body: Record<string, unknown>) => (trainer ? api.put(`/trainers/${trainer.id}`, body) : api.post('/trainers', body)),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['trainers'] })
      onClose()
    },
    onError: (e) => {
      setErrors(validationErrors(e))
      if (!Object.keys(validationErrors(e)).length) setError(errorMessage(e))
    },
  })

  const onSubmit = (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault()
    const f = Object.fromEntries(new FormData(e.currentTarget)) as Record<string, string>
    save.mutate({ ...f, branch_id: Number(f.branch_id), experience_years: Number(f.experience_years) || null, user_id: Number(f.user_id) || null })
  }

  return (
    <Modal open title={trainer ? `Edit ${trainer.name}` : 'New trainer'} onClose={onClose}>
      <form onSubmit={onSubmit} className="space-y-4" noValidate>
        {error && <Alert>{error}</Alert>}
        <Field label="Name" htmlFor="t_name" error={errors.name}>
          <Input id="t_name" name="name" defaultValue={trainer?.name} />
        </Field>
        <div className="grid gap-3 sm:grid-cols-2">
          <Field label="Branch" htmlFor="t_branch" error={errors.branch_id}>
            <Select id="t_branch" name="branch_id" defaultValue={trainer?.branch?.id ?? user?.branches?.[0]?.id}>
              {user?.branches?.map((b) => (
                <option key={b.id} value={b.id}>
                  {b.name}
                </option>
              ))}
            </Select>
          </Field>
          <Field label="Status" htmlFor="t_status">
            <Select id="t_status" name="status" defaultValue={trainer?.status ?? 'active'}>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </Select>
          </Field>
          <Field label="Mobile" htmlFor="t_phone">
            <Input id="t_phone" name="phone" defaultValue={trainer?.phone ?? ''} />
          </Field>
          <Field label="Email" htmlFor="t_email" error={errors.email}>
            <Input id="t_email" name="email" type="email" defaultValue={trainer?.email ?? ''} />
          </Field>
          <Field label="Qualification" htmlFor="t_q">
            <Input id="t_q" name="qualification" defaultValue={trainer?.qualification ?? ''} />
          </Field>
          <Field label="Experience (years)" htmlFor="t_exp">
            <Input id="t_exp" name="experience_years" type="number" defaultValue={trainer?.experience_years ?? ''} />
          </Field>
          <Field label="Employee code" htmlFor="t_code" error={errors.employee_code}>
            <Input id="t_code" name="employee_code" defaultValue={trainer?.employee_code ?? ''} />
          </Field>
          <Field label="Login account" htmlFor="t_user" hint="A user with the Trainer role" error={errors.user_id}>
            <Select id="t_user" name="user_id" defaultValue={trainer?.user_id ?? ''}>
              <option value="">No login</option>
              {trainerLogins?.map((u) => (
                <option key={u.id} value={u.id}>
                  {u.name} ({u.email ?? u.phone})
                </option>
              ))}
            </Select>
          </Field>
        </div>
        <div className="flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" loading={save.isPending}>
            Save
          </Button>
        </div>
      </form>
    </Modal>
  )
}
