import { Lock, Star } from 'lucide-react'
import { useState } from 'react'
import { errorMessage, validationErrors } from '../../../api/client'
import { Button } from '../../../components/ui/Button'
import { Alert } from '../../../components/ui/Card'
import { Field, Textarea } from '../../../components/ui/Field'
import { cn } from '../../../utils/cn'
import { useActivityTypes, usePlans, useSaveRecord } from '../api'
import type { TrainingRecord } from '../types'

/**
 * Quick daily training record (Plan §১১): activity chips, 1–5 stars, short notes, goal scores.
 * "Save draft" keeps it editable; "Finalize" locks it and shares the parent note.
 */
export function RecordForm({ classId, date, enrollmentId, record, lastNextPlan, onDone }: {
  classId: number
  date: string
  enrollmentId: number
  record: TrainingRecord | null
  lastNextPlan?: string | null
  onDone: () => void
}) {
  const { data: activityTypes } = useActivityTypes()
  const { data: plans } = usePlans(enrollmentId)
  const goals = plans?.find((p) => p.status === 'active')?.goals.filter((g) => g.status !== 'discontinued' && g.status !== 'achieved') ?? []
  const save = useSaveRecord(classId)
  const locked = record?.status === 'final'

  const [activities, setActivities] = useState<number[]>(record?.activities?.map((a) => a.id) ?? [])
  const [performance, setPerformance] = useState<number | null>(record?.performance ?? null)
  const [scores, setScores] = useState<Record<number, number>>(() => Object.fromEntries(record?.goal_scores?.map((g) => [g.goal_id, g.score]) ?? []))
  const [text, setText] = useState({
    observation: record?.observation ?? '',
    progress: record?.progress ?? '',
    challenges: record?.challenges ?? '',
    next_plan: record?.next_plan ?? '',
    parent_note: record?.parent_note ?? '',
    trainer_notes: record?.trainer_notes ?? '',
  })
  const [error, setError] = useState<string | null>(null)

  const submit = async (finalize: boolean) => {
    setError(null)
    try {
      await save.mutateAsync({
        date,
        enrollment_id: enrollmentId,
        activity_ids: activities,
        performance,
        ...Object.fromEntries(Object.entries(text).map(([k, v]) => [k, v || null])),
        goal_scores: Object.entries(scores).map(([goal_id, score]) => ({ goal_id: Number(goal_id), score })),
        finalize,
      })
      onDone()
    } catch (e) {
      setError(Object.values(validationErrors(e))[0] ?? errorMessage(e))
    }
  }

  const textField = (key: keyof typeof text, label: string, hint?: string) => (
    <Field label={label} htmlFor={`r_${key}`} hint={hint}>
      <Textarea id={`r_${key}`} rows={2} disabled={locked} value={text[key]} onChange={(e) => setText((t) => ({ ...t, [key]: e.target.value }))} />
    </Field>
  )

  return (
    <div className="space-y-5">
      {locked && (
        <Alert tone="green">
          <span className="inline-flex items-center gap-1.5">
            <Lock className="size-4" /> This record is final and locked.
          </span>
        </Alert>
      )}
      {error && <Alert>{error}</Alert>}
      {lastNextPlan && !record && <p className="rounded-lg bg-sky-brand-50 p-3 text-sm text-sky-brand-700">Last plan: {lastNextPlan}</p>}

      <div>
        <p className="mb-2 text-sm font-medium text-slate-700">Activities</p>
        <div className="flex flex-wrap gap-2">
          {activityTypes?.map((a) => {
            const on = activities.includes(a.id)
            return (
              <button
                key={a.id}
                type="button"
                disabled={locked}
                aria-pressed={on}
                onClick={() => setActivities((list) => (on ? list.filter((x) => x !== a.id) : [...list, a.id]))}
                className={cn('rounded-full px-3 py-1.5 text-sm font-medium transition', on ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200')}
              >
                {a.name}
              </button>
            )
          })}
        </div>
      </div>

      <div>
        <p className="mb-2 text-sm font-medium text-slate-700">Performance today</p>
        <div className="flex gap-1" role="radiogroup" aria-label="Performance">
          {[1, 2, 3, 4, 5].map((n) => (
            <button key={n} type="button" disabled={locked} role="radio" aria-checked={performance === n} aria-label={`${n} of 5`} onClick={() => setPerformance(n)} className="p-1">
              <Star className={cn('size-8', performance && n <= performance ? 'fill-amber-400 text-amber-400' : 'text-slate-300')} />
            </button>
          ))}
        </div>
      </div>

      {goals.length > 0 && (
        <div>
          <p className="mb-2 text-sm font-medium text-slate-700">Plan goals worked on today (1–5)</p>
          <ul className="space-y-2">
            {goals.map((g) => (
              <li key={g.id} className="flex flex-col gap-2 rounded-lg border border-slate-100 p-3 sm:flex-row sm:items-center sm:justify-between">
                <span className="text-sm text-slate-800">{g.title}</span>
                <div className="flex gap-1">
                  {[1, 2, 3, 4, 5].map((n) => (
                    <button
                      key={n}
                      type="button"
                      disabled={locked}
                      onClick={() => setScores((s) => (s[g.id] === n ? Object.fromEntries(Object.entries(s).filter(([k]) => Number(k) !== g.id)) : { ...s, [g.id]: n }))}
                      className={cn('size-9 rounded-lg text-sm font-semibold', scores[g.id] === n ? 'bg-sky-brand-600 text-white' : 'bg-slate-100 text-slate-500')}
                      aria-label={`${g.title}: ${n}`}
                    >
                      {n}
                    </button>
                  ))}
                </div>
              </li>
            ))}
          </ul>
        </div>
      )}

      <div className="grid gap-4 sm:grid-cols-2">
        {textField('observation', 'Observation')}
        {textField('progress', 'Progress')}
        {textField('challenges', 'Challenges')}
        {textField('next_plan', 'Next plan')}
        {textField('parent_note', 'Note for parents', 'Visible in the parent portal once finalized')}
        {textField('trainer_notes', 'Internal notes', 'Staff only')}
      </div>

      {!locked && (
        <div className="flex flex-col gap-2 sm:flex-row sm:justify-end">
          <Button variant="secondary" onClick={() => submit(false)} loading={save.isPending}>
            Save draft
          </Button>
          <Button onClick={() => submit(true)} loading={save.isPending}>
            Finalize record
          </Button>
        </div>
      )}
    </div>
  )
}
