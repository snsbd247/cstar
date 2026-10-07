import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { UserRoundCog } from 'lucide-react'
import { useState } from 'react'
import { api, errorMessage } from '../../../api/client'
import { Button } from '../../../components/ui/Button'
import { Alert } from '../../../components/ui/Card'
import { Select } from '../../../components/ui/Field'
import { Spinner } from '../../../components/ui/Spinner'

interface Options {
  appointments: { id: number; date: string; start_time: string; end_time: string; patient: string; service: string }[]
  candidates: { id: number; name: string; can_take: number }[]
}
interface Result {
  moved: { id: number; date: string; time: string; patient: string }[]
  skipped: { id: number; date: string; time: string; patient: string; reason: string }[]
  dry_run: boolean
}

/**
 * Sprint 22 (Plan #৬): move an absent therapist's booked appointments to a colleague who gives the same service and
 * is free. Preview first; families are told in Bangla, the substitute gets a notification.
 */
export function SubstitutePanel({ therapistId, from, to }: { therapistId: number; from: string; to: string }) {
  const qc = useQueryClient()
  const [substitute, setSubstitute] = useState('')
  const [result, setResult] = useState<Result | null>(null)
  const { data, isLoading } = useQuery({
    queryKey: ['substitute-options', therapistId, from, to],
    queryFn: async () => (await api.get<{ data: Options }>(`/therapists/${therapistId}/substitute`, { params: { from, to } })).data.data,
  })
  const run = useMutation({
    mutationFn: async (dryRun: boolean) =>
      (await api.post<{ data: Result }>(`/therapists/${therapistId}/substitute`, { from, to, substitute_id: Number(substitute), dry_run: dryRun })).data.data,
    onSuccess: (r) => {
      setResult(r)
      if (!r.dry_run) {
        qc.invalidateQueries({ queryKey: ['substitute-options'] })
        qc.invalidateQueries({ queryKey: ['appointments'] })
      }
    },
  })

  if (isLoading || !data) return <Spinner className="text-brand-600" />
  if (!data.appointments.length) return <p className="text-sm text-slate-500">No booked appointments between {from} and {to}.</p>

  return (
    <div className="space-y-3 rounded-lg border border-slate-200 p-3">
      <p className="flex items-center gap-1.5 text-sm font-semibold text-slate-900">
        <UserRoundCog className="size-4 text-slate-500" /> Substitute for {data.appointments.length} appointment(s), {from} → {to}
      </p>
      <ul className="max-h-36 overflow-y-auto text-xs text-slate-600">
        {data.appointments.map((a) => (
          <li key={a.id}>
            {a.date} {a.start_time} · {a.patient} · {a.service}
          </li>
        ))}
      </ul>
      {!data.candidates.length ? (
        <Alert>No other active therapist gives these services. Reschedule from Appointments instead.</Alert>
      ) : (
        <div className="flex flex-wrap gap-2">
          <Select value={substitute} onChange={(e) => (setSubstitute(e.target.value), setResult(null))} className="sm:w-72" aria-label="Substitute therapist">
            <option value="">Choose a substitute…</option>
            {data.candidates.map((c) => (
              <option key={c.id} value={c.id}>
                {c.name} — free for {c.can_take} of {data.appointments.length}
              </option>
            ))}
          </Select>
          <Button variant="secondary" disabled={!substitute} loading={run.isPending && run.variables} onClick={() => run.mutate(true)}>
            Preview
          </Button>
          <Button disabled={!substitute || !result?.dry_run || !result.moved.length} loading={run.isPending && !run.variables} onClick={() => run.mutate(false)}>
            Move {result?.dry_run ? result.moved.length : ''} appointment(s)
          </Button>
        </div>
      )}
      {run.isError && <Alert>{errorMessage(run.error)}</Alert>}
      {result && (
        <div className="space-y-1 text-xs">
          <p className={result.dry_run ? 'text-slate-700' : 'font-medium text-brand-700'}>
            {result.dry_run ? `${result.moved.length} can move.` : `${result.moved.length} moved — families were told by message.`}
          </p>
          {result.skipped.map((s) => (
            <p key={s.id} className="text-amber-700">
              {s.date} {s.time} · {s.patient}: {s.reason}
            </p>
          ))}
        </div>
      )}
    </div>
  )
}
