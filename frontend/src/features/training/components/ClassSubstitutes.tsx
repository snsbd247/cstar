import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Trash2, UserRoundCog } from 'lucide-react'
import { useState } from 'react'
import { api, errorMessage, validationErrors } from '../../../api/client'
import { Button } from '../../../components/ui/Button'
import { Alert, Badge, Card } from '../../../components/ui/Card'
import { Input, Select } from '../../../components/ui/Field'
import { todayISO } from '../../../utils/format'
import { useTrainers } from '../api'

interface Substitute {
  id: number
  trainer: { id: number; name: string }
  date_from: string
  date_to: string
  reason: string | null
  active: boolean
}

/** Sprint 22 (Plan #৬): another trainer covers this class for some days — same access, only on those days. */
export function ClassSubstitutes({ classId, leadTrainerId, canEdit }: { classId: number; leadTrainerId: number | null; canEdit: boolean }) {
  const qc = useQueryClient()
  const { data } = useQuery({
    queryKey: ['class-substitutes', classId],
    queryFn: async () => (await api.get<{ data: Substitute[] }>(`/classes/${classId}/substitutes`)).data.data,
  })
  const { data: trainers } = useTrainers()
  const [v, setV] = useState({
    trainer_id: '',
    date_from: todayISO(),
    date_to: todayISO(),
    reason: '',
  })
  const [error, setError] = useState<string | null>(null)
  const refresh = () => qc.invalidateQueries({ queryKey: ['class-substitutes', classId] })
  const add = useMutation({
    mutationFn: () =>
      api.post(`/classes/${classId}/substitutes`, {
        ...v,
        trainer_id: Number(v.trainer_id),
      }),
    onSuccess: () => (refresh(), setV((x) => ({ ...x, trainer_id: '', reason: '' })), setError(null)),
    onError: (e) => setError(Object.values(validationErrors(e))[0] ?? errorMessage(e)),
  })
  const remove = useMutation({
    mutationFn: (id: number) => api.delete(`/class-substitutes/${id}`),
    onSuccess: refresh,
  })
  if (!canEdit && !data?.length) return null

  return (
    <Card className="mt-6 p-5">
      <h2 className="flex items-center gap-1.5 font-semibold text-slate-900">
        <UserRoundCog className="size-4 text-slate-500" /> Substitute trainer
      </h2>
      <p className="mt-1 text-sm text-slate-500">When the class trainer is away, another trainer takes attendance and writes records for these days only.</p>
      <ul className="mt-3 space-y-1 text-sm">
        {data?.map((s) => (
          <li key={s.id} className="flex items-center gap-2 rounded bg-slate-50 px-3 py-2">
            <span className="font-medium text-slate-800">{s.trainer.name}</span>
            <span className="text-slate-500">
              {s.date_from} → {s.date_to}
              {s.reason && ` · ${s.reason}`}
            </span>
            {s.active && <Badge tone="green">today</Badge>}
            {canEdit && (
              <button onClick={() => remove.mutate(s.id)} className="ml-auto text-slate-400 hover:text-red-600" aria-label="Remove substitute">
                <Trash2 className="size-4" />
              </button>
            )}
          </li>
        ))}
      </ul>
      {canEdit && (
        <div className="mt-3 flex flex-wrap gap-2">
          <Select value={v.trainer_id} onChange={(e) => setV({ ...v, trainer_id: e.target.value })} className="sm:w-56" aria-label="Substitute trainer">
            <option value="">Choose a trainer…</option>
            {trainers
              ?.filter((t) => t.id !== leadTrainerId && t.status === 'active')
              .map((t) => (
                <option key={t.id} value={t.id}>
                  {t.name}
                </option>
              ))}
          </Select>
          <Input type="date" value={v.date_from} onChange={(e) => setV({ ...v, date_from: e.target.value })} className="sm:w-40" aria-label="From" />
          <Input type="date" value={v.date_to} onChange={(e) => setV({ ...v, date_to: e.target.value })} className="sm:w-40" aria-label="To" />
          <Input value={v.reason} onChange={(e) => setV({ ...v, reason: e.target.value })} placeholder="Reason (optional)" className="sm:w-48" aria-label="Reason" />
          <Button disabled={!v.trainer_id} loading={add.isPending} onClick={() => add.mutate()}>
            Add substitute
          </Button>
        </div>
      )}
      {error && (
        <div className="mt-2">
          <Alert>{error}</Alert>
        </div>
      )}
    </Card>
  )
}
