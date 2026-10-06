import { Pencil, Plus, Target, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { errorMessage, validationErrors } from '../../../api/client'
import { Button } from '../../../components/ui/Button'
import { Alert, Badge, Card } from '../../../components/ui/Card'
import { Field, Input, Select, Textarea } from '../../../components/ui/Field'
import { Modal } from '../../../components/ui/Modal'
import { Spinner } from '../../../components/ui/Spinner'
import { todayISO } from '../../../utils/format'
import { usePlans, useSavePlan } from '../api'
import type { IndividualPlan, PlanGoal } from '../types'

const goalTone = { not_started: 'gray', in_progress: 'blue', achieved: 'green', discontinued: 'red' } as const

/** Individual plan (ITP for training) with goals and recent daily scores. */
export function PlanPanel({ enrollmentId, canEdit, title = 'Individual Training Plan' }: { enrollmentId: number; canEdit: boolean; title?: string }) {
  const { data: plans, isLoading } = usePlans(enrollmentId)
  const [editing, setEditing] = useState<IndividualPlan | 'new' | null>(null)
  const active = plans?.find((p) => p.status === 'active')

  if (isLoading) return <Spinner className="text-brand-600" />

  return (
    <Card className="p-5">
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div>
          <h3 className="flex items-center gap-2 font-semibold text-slate-900">
            <Target className="size-4 text-brand-600" /> {title}
          </h3>
          {active ? (
            <p className="text-sm text-slate-500">
              {active.title} · from {active.start_date}
              {active.review_date && ` · review ${active.review_date}`}
            </p>
          ) : (
            <p className="text-sm text-slate-500">No active plan yet.</p>
          )}
        </div>
        {canEdit && (
          <Button variant="secondary" onClick={() => setEditing(active ?? 'new')}>
            {active ? <Pencil className="size-4" /> : <Plus className="size-4" />} {active ? 'Edit plan' : 'Create plan'}
          </Button>
        )}
      </div>

      {active && (
        <ul className="mt-4 space-y-3">
          {active.goals.map((g, i) => (
            <li key={g.id} className="rounded-xl border border-slate-100 p-3">
              <div className="flex items-start justify-between gap-2">
                <p className="text-sm font-medium text-slate-900">
                  Goal {i + 1}: {g.title}
                </p>
                <Badge tone={goalTone[g.status]}>{g.status.replace('_', ' ')}</Badge>
              </div>
              {g.target && <p className="mt-1 text-xs text-slate-500">Target: {g.target}</p>}
              <div className="mt-2 flex items-center gap-2">
                <div className="h-2 flex-1 overflow-hidden rounded-full bg-slate-100">
                  <div className="h-full rounded-full bg-brand-500" style={{ width: `${g.progress_percent}%` }} />
                </div>
                <span className="w-10 text-right text-xs font-medium text-slate-600">{g.progress_percent}%</span>
              </div>
              {g.recent_scores.length > 0 && (
                <div className="mt-2 flex items-end gap-1" aria-label="Recent daily scores">
                  {[...g.recent_scores].reverse().map((s, j) => (
                    <span key={j} title={`${s.date}: ${s.score}/5`} className="w-2.5 rounded-sm bg-sky-brand-500/80" style={{ height: `${s.score * 5}px` }} />
                  ))}
                  <span className="ml-1 text-[11px] text-slate-400">last {g.recent_scores.length} scores</span>
                </div>
              )}
            </li>
          ))}
        </ul>
      )}

      {editing && <PlanEditor enrollmentId={enrollmentId} plan={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />}
    </Card>
  )
}

type DraftGoal = Partial<PlanGoal> & { title: string }

function PlanEditor({ enrollmentId, plan, onClose }: { enrollmentId: number; plan: IndividualPlan | null; onClose: () => void }) {
  const save = useSavePlan(enrollmentId)
  const [title, setTitle] = useState(plan?.title ?? 'Individual Training Plan')
  const [startDate, setStartDate] = useState(plan?.start_date ?? todayISO())
  const [reviewDate, setReviewDate] = useState(plan?.review_date ?? '')
  const [goals, setGoals] = useState<DraftGoal[]>(plan?.goals.filter((g) => g.status !== 'discontinued') ?? [{ title: '' }])
  const [error, setError] = useState<string | null>(null)

  const setGoal = (i: number, patch: Partial<DraftGoal>) => setGoals((list) => list.map((g, j) => (j === i ? { ...g, ...patch } : g)))

  const submit = async (close = false) => {
    setError(null)
    try {
      await save.mutateAsync({
        id: plan?.id,
        title,
        start_date: startDate,
        review_date: reviewDate || null,
        ...(close ? { status: 'closed' } : {}),
        goals: goals
          .filter((g) => g.title.trim())
          .map((g) => ({
            id: g.id,
            title: g.title,
            domain: g.domain || null,
            target: g.target || null,
            baseline_level: g.baseline_level || null,
            current_level: g.current_level || null,
            measurement: g.measurement || null,
            progress_percent: g.progress_percent ?? 0,
            status: g.status ?? 'in_progress',
          })),
      })
      onClose()
    } catch (e) {
      setError(Object.values(validationErrors(e))[0] ?? errorMessage(e))
    }
  }

  return (
    <Modal open title={plan ? 'Edit plan' : 'New individual plan'} onClose={onClose}>
      <div className="space-y-4">
        {error && <Alert>{error}</Alert>}
        <Field label="Plan title" htmlFor="p_title">
          <Input id="p_title" value={title} onChange={(e) => setTitle(e.target.value)} />
        </Field>
        <div className="grid grid-cols-2 gap-3">
          <Field label="Start" htmlFor="p_start">
            <Input id="p_start" type="date" value={startDate} onChange={(e) => setStartDate(e.target.value)} />
          </Field>
          <Field label="Review on" htmlFor="p_review">
            <Input id="p_review" type="date" value={reviewDate} onChange={(e) => setReviewDate(e.target.value)} />
          </Field>
        </div>

        <div className="space-y-3">
          {goals.map((g, i) => (
            <div key={g.id ?? `new-${i}`} className="space-y-2 rounded-xl border border-slate-200 p-3">
              <div className="flex items-center justify-between">
                <p className="text-sm font-semibold text-slate-800">Goal {i + 1}</p>
                <button type="button" onClick={() => setGoals((list) => list.filter((_, j) => j !== i))} className="rounded p-1 text-slate-400 hover:text-red-600" aria-label="Remove goal">
                  <Trash2 className="size-4" />
                </button>
              </div>
              <Input placeholder="e.g. Improve fine motor skill" value={g.title} onChange={(e) => setGoal(i, { title: e.target.value })} aria-label={`Goal ${i + 1} title`} />
              <Textarea rows={2} placeholder="Target (what success looks like)" value={g.target ?? ''} onChange={(e) => setGoal(i, { target: e.target.value })} aria-label="Target" />
              <div className="grid grid-cols-2 gap-2">
                <Input placeholder="Current level" value={g.current_level ?? ''} onChange={(e) => setGoal(i, { current_level: e.target.value })} aria-label="Current level" />
                <Input placeholder="Measurement" value={g.measurement ?? ''} onChange={(e) => setGoal(i, { measurement: e.target.value })} aria-label="Measurement" />
              </div>
              <div className="grid grid-cols-2 gap-2">
                <label className="text-xs text-slate-600">
                  Progress {g.progress_percent ?? 0}%
                  <input type="range" min={0} max={100} step={5} value={g.progress_percent ?? 0} onChange={(e) => setGoal(i, { progress_percent: Number(e.target.value) })} className="w-full accent-brand-600" />
                </label>
                <Select value={g.status ?? 'in_progress'} onChange={(e) => setGoal(i, { status: e.target.value as PlanGoal['status'] })} aria-label="Goal status">
                  <option value="not_started">Not started</option>
                  <option value="in_progress">In progress</option>
                  <option value="achieved">Achieved</option>
                </Select>
              </div>
            </div>
          ))}
          <Button variant="secondary" className="w-full" onClick={() => setGoals((list) => [...list, { title: '' }])}>
            <Plus className="size-4" /> Add goal
          </Button>
        </div>

        <div className="flex flex-wrap justify-between gap-2 pt-1">
          {plan ? (
            <Button variant="ghost" className="text-red-600" onClick={() => confirm('Close this plan? A new plan can then be created.') && submit(true)}>
              Close plan
            </Button>
          ) : (
            <span />
          )}
          <div className="flex gap-2">
            <Button variant="secondary" onClick={onClose}>
              Cancel
            </Button>
            <Button onClick={() => submit()} loading={save.isPending}>
              Save plan
            </Button>
          </div>
        </div>
      </div>
    </Modal>
  )
}
