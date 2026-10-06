import { useQuery } from '@tanstack/react-query'
import { Clock, Plus, Users } from 'lucide-react'
import { useState, type FormEvent } from 'react'
import { Link } from 'react-router'
import { api, errorMessage, validationErrors } from '../../api/client'
import { Button } from '../../components/ui/Button'
import { Alert, Badge, Card, PageHeader } from '../../components/ui/Card'
import { Field, Input, Select, Textarea } from '../../components/ui/Field'
import { Modal } from '../../components/ui/Modal'
import { Spinner } from '../../components/ui/Spinner'
import { useAuth } from '../../contexts/useAuth'
import { useClasses, useSaveClass } from './api'
import { dayNames as days, scheduleText, weekOrder } from './schedule'
import type { TrainingClass } from './types'

export default function ClassesPage() {
  const { can } = useAuth()
  const { data, isLoading } = useClasses()
  const [editing, setEditing] = useState<TrainingClass | 'new' | null>(null)

  return (
    <>
      <PageHeader
        title="Classes"
        description="Regular training groups. The roster is every student with an open training enrollment in the class."
        actions={
          can('classes.manage') && (
            <Button onClick={() => setEditing('new')}>
              <Plus className="size-4" /> New class
            </Button>
          )
        }
      />
      {isLoading ? (
        <Spinner className="text-brand-600" />
      ) : (
        <div className="grid gap-4 md:grid-cols-2">
          {data?.map((c) => (
            <Card key={c.id} className="p-5">
              <div className="flex items-start justify-between gap-2">
                <div>
                  <Link to={`/app/classes/${c.id}`} className="font-semibold text-slate-900 hover:text-brand-700">
                    {c.name}
                  </Link>
                  <p className="text-sm text-slate-500">
                    {c.code} · {c.branch?.name}
                  </p>
                </div>
                <Badge tone={c.status === 'active' ? 'green' : 'gray'}>{c.status}</Badge>
              </div>
              <div className="mt-3 space-y-1 text-sm text-slate-600">
                <p>Trainer: {c.lead_trainer?.name ?? '—'}</p>
                <p className="flex items-center gap-1.5">
                  <Clock className="size-4 text-slate-400" /> {scheduleText(c)}
                </p>
                <p className="flex items-center gap-1.5">
                  <Users className="size-4 text-slate-400" /> {c.students_count ?? 0}
                  {c.max_students ? ` / ${c.max_students}` : ''} students
                </p>
              </div>
              <div className="mt-4 flex gap-2 border-t border-slate-100 pt-3">
                <Link to={`/app/classes/${c.id}`}>
                  <Button variant="secondary">Open</Button>
                </Link>
                {can('classes.manage') && (
                  <Button variant="ghost" onClick={() => setEditing(c)}>
                    Edit
                  </Button>
                )}
              </div>
            </Card>
          ))}
        </div>
      )}
      {editing && <ClassForm cls={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />}
    </>
  )
}

export function ClassForm({ cls, onClose }: { cls: TrainingClass | null; onClose: () => void }) {
  const { user } = useAuth()
  const save = useSaveClass()
  const { data: trainers } = useQuery({
    queryKey: ['trainers'],
    queryFn: async () => (await api.get<{ data: { id: number; name: string; status: string; branch: { id: number } | null }[] }>('/trainers')).data.data,
  })
  const [selectedDays, setSelectedDays] = useState<number[]>(cls?.schedules?.map((s) => s.weekday) ?? [6, 0, 1, 2, 3, 4])
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [error, setError] = useState<string | null>(null)

  const onSubmit = async (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault()
    const f = Object.fromEntries(new FormData(e.currentTarget)) as Record<string, string>
    setErrors({})
    setError(null)
    try {
      await save.mutateAsync({
        id: cls?.id,
        ...(cls ? {} : { branch_id: Number(f.branch_id) }),
        code: f.code,
        name: f.name,
        lead_trainer_id: Number(f.lead_trainer_id) || null,
        max_students: Number(f.max_students) || null,
        start_date: f.start_date || null,
        status: f.status,
        notes: f.notes || null,
        schedules: selectedDays.map((weekday) => ({ weekday, start_time: f.start_time, end_time: f.end_time })),
      })
      onClose()
    } catch (err) {
      const fields = validationErrors(err)
      setErrors(fields)
      setError(Object.keys(fields).length ? (Object.entries(fields).find(([k]) => k.startsWith('schedules'))?.[1] ?? null) : errorMessage(err))
    }
  }

  return (
    <Modal open title={cls ? `Edit ${cls.name}` : 'New class'} onClose={onClose}>
      <form onSubmit={onSubmit} className="space-y-4" noValidate>
        {error && <Alert>{error}</Alert>}
        <div className="grid grid-cols-[110px_1fr] gap-3">
          <Field label="Code" htmlFor="c_code" error={errors.code}>
            <Input id="c_code" name="code" defaultValue={cls?.code} />
          </Field>
          <Field label="Class name" htmlFor="c_name" error={errors.name}>
            <Input id="c_name" name="name" defaultValue={cls?.name} placeholder="Functional Development A" />
          </Field>
        </div>
        <div className="grid gap-3 sm:grid-cols-2">
          {!cls && (
            <Field label="Branch" htmlFor="c_branch" error={errors.branch_id}>
              <Select id="c_branch" name="branch_id">
                {user?.branches?.map((b) => (
                  <option key={b.id} value={b.id}>
                    {b.name}
                  </option>
                ))}
              </Select>
            </Field>
          )}
          <Field label="Lead trainer" htmlFor="c_trainer" error={errors.lead_trainer_id}>
            <Select id="c_trainer" name="lead_trainer_id" defaultValue={cls?.lead_trainer?.id ?? ''}>
              <option value="">—</option>
              {trainers
                ?.filter((t) => t.status === 'active')
                .map((t) => (
                  <option key={t.id} value={t.id}>
                    {t.name}
                  </option>
                ))}
            </Select>
          </Field>
          <Field label="Max students" htmlFor="c_max" error={errors.max_students}>
            <Input id="c_max" name="max_students" type="number" defaultValue={cls?.max_students ?? ''} />
          </Field>
          <Field label="Status" htmlFor="c_status">
            <Select id="c_status" name="status" defaultValue={cls?.status ?? 'active'}>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
              <option value="closed">Closed</option>
            </Select>
          </Field>
          <Field label="Start date" htmlFor="c_start">
            <Input id="c_start" name="start_date" type="date" defaultValue={cls?.start_date ?? ''} />
          </Field>
        </div>

        <Field label="Class days">
          <div className="flex flex-wrap gap-1.5">
            {weekOrder.map((d) => {
              const on = selectedDays.includes(d)
              return (
                <button
                  key={d}
                  type="button"
                  aria-pressed={on}
                  onClick={() => setSelectedDays((s) => (on ? s.filter((x) => x !== d) : [...s, d]))}
                  className={`min-h-10 rounded-lg px-3 text-sm font-medium ${on ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-600'}`}
                >
                  {days[d]}
                </button>
              )
            })}
          </div>
        </Field>
        <div className="grid grid-cols-2 gap-3">
          <Field label="From" htmlFor="c_from">
            <Input id="c_from" name="start_time" type="time" defaultValue={cls?.schedules?.[0]?.start_time ?? '10:00'} />
          </Field>
          <Field label="To" htmlFor="c_to">
            <Input id="c_to" name="end_time" type="time" defaultValue={cls?.schedules?.[0]?.end_time ?? '13:00'} />
          </Field>
        </div>
        <Field label="Notes" htmlFor="c_notes">
          <Textarea id="c_notes" name="notes" rows={2} defaultValue={cls?.notes ?? ''} />
        </Field>
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
