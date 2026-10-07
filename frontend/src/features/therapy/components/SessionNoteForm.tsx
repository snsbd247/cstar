import { Lock } from 'lucide-react'
import { useState } from 'react'
import { errorMessage, validationErrors } from '../../../api/client'
import { Button } from '../../../components/ui/Button'
import { Alert, Card } from '../../../components/ui/Card'
import { Field, Textarea } from '../../../components/ui/Field'
import { Spinner } from '../../../components/ui/Spinner'
import { cn } from '../../../utils/cn'
import { usePlans } from '../../training/api'
import { PlanPanel } from '../../training/components/PlanPanel'
import { useAppointmentSession, useSaveSession, useTherapyActivityTypes, type SessionBundle } from '../api'
import { AmendmentsPanel } from '../../../components/shared/AmendmentsPanel'
import { ReviewHistory } from '../../therapist/ClinicalReviewPage'
import { StatusPill } from './AppointmentActions'
import { PracticeFeedbackSummary } from './PracticeFeedbackSummary'

const fields = [
  ['goals_worked', 'Goals worked on'],
  ['observation', 'Observation'],
  ['patient_response', 'Child\'s response'],
  ['progress', 'Progress'],
  ['challenges', 'Challenges'],
  ['home_practice', 'Home practice'],
  ['next_session_plan', 'Next session plan'],
  ['therapist_notes', 'Clinical notes (internal)'],
] as const
type FieldKey = (typeof fields)[number][0] | 'parent_summary'

/**
 * Plan §১৪ clinical workflow: summary on the left (last plan, home practice, goals), structured note on the right.
 * Draft can be saved any time; Finalize locks it and completes the appointment.
 */
export function SessionNote({ appointmentId, onDone }: { appointmentId: number; onDone?: () => void }) {
  const { data, isLoading } = useAppointmentSession(appointmentId)
  if (isLoading || !data) return <Spinner className="text-brand-600" />
  return <NoteBody key={`${data.session?.id ?? 'new'}-${data.session?.updated_at ?? ''}`} data={data} onDone={onDone} />
}

function NoteBody({ data, onDone }: { data: SessionBundle; onDone?: () => void }) {
  const { appointment: a, session, previous, can_write } = data
  const save = useSaveSession(a.id)
  const { data: activityTypes } = useTherapyActivityTypes()
  const { data: plans } = usePlans(a.enrollment_id ?? undefined)
  const goals = plans?.find((p) => p.status === 'active')?.goals.filter((g) => !['achieved', 'discontinued'].includes(g.status)) ?? []
  const locked = session?.status === 'final' || !can_write

  const [text, setText] = useState<Record<FieldKey, string>>(() =>
    Object.fromEntries([...fields.map(([k]) => k), 'parent_summary'].map((k) => [k, (session?.[k as FieldKey] as string | null) ?? ''])) as Record<FieldKey, string>,
  )
  const [activities, setActivities] = useState<number[]>(session?.activities?.map((x) => x.id) ?? [])
  const [scores, setScores] = useState<Record<number, number>>(() => Object.fromEntries(session?.goal_scores?.map((g) => [g.goal_id, g.score]) ?? []))
  const [message, setMessage] = useState<{ tone: 'green' | 'red'; text: string } | null>(null)

  const submit = async (finalize: boolean) => {
    setMessage(null)
    try {
      await save.mutateAsync({
        ...Object.fromEntries(Object.entries(text).map(([k, v]) => [k, v || null])),
        activity_ids: activities,
        goal_scores: Object.entries(scores).map(([goal_id, score]) => ({ goal_id: Number(goal_id), score })),
        finalize,
      })
      setMessage({ tone: 'green', text: finalize ? 'Session finalized.' : 'Draft saved.' })
      if (finalize) onDone?.()
    } catch (e) {
      setMessage({ tone: 'red', text: Object.values(validationErrors(e))[0] ?? errorMessage(e) })
    }
  }

  return (
    <div className="grid gap-5 lg:grid-cols-[320px_1fr]">
      <aside className="space-y-4">
        <Card className="p-4">
          <p className="text-lg font-semibold text-slate-900">{a.patient?.name}</p>
          <p className="text-sm text-slate-500">
            {a.patient?.patient_code} · {a.service?.name}
          </p>
          <p className="mt-1 flex items-center gap-2 text-sm text-slate-600">
            {a.date} · {a.start_time}–{a.end_time} <StatusPill status={a.status} />
          </p>
          {a.notes && <p className="mt-2 rounded bg-amber-50 p-2 text-xs text-amber-800">Front desk: {a.notes}</p>}
        </Card>
        {previous && (
          <Card className="space-y-2 p-4 text-sm">
            <p className="font-semibold text-slate-900">Last session ({previous.date})</p>
            {previous.next_session_plan && (
              <p>
                <span className="text-slate-500">Planned: </span>
                {previous.next_session_plan}
              </p>
            )}
            {previous.home_practice && (
              <p>
                <span className="text-slate-500">Home practice: </span>
                {previous.home_practice}
              </p>
            )}
            {previous.home_practice && <PracticeFeedbackSummary logs={previous.practice_feedback ?? []} />}
          </Card>
        )}
        {a.enrollment_id && <PlanPanel enrollmentId={a.enrollment_id} canEdit={can_write} title="Therapy plan" />}
        {session?.status === 'final' && <ReviewHistory type="session" id={session.id} />}
        {session?.status === 'final' && (
          <AmendmentsPanel
            url={`/appointments/${a.id}/session/amendments`}
            fields={[...fields, ['parent_summary', 'Summary for parents']]}
            current={session as unknown as Record<string, string | null>}
            canAmend={can_write}
            refresh={[['appointment-session', a.id]]}
          />
        )}
      </aside>

      <Card className="space-y-5 p-5">
        {locked && (
          <Alert tone="green">
            <span className="inline-flex items-center gap-1.5">
              <Lock className="size-4" /> {session?.status === 'final' ? 'This note is final and locked.' : 'Read only — only the treating therapist writes this note.'}
            </span>
          </Alert>
        )}
        {message && <Alert tone={message.tone}>{message.text}</Alert>}

        <div>
          <p className="mb-2 text-sm font-medium text-slate-700">Activities</p>
          <div className="flex flex-wrap gap-2">
            {activityTypes?.map((t) => {
              const on = activities.includes(t.id)
              return (
                <button
                  key={t.id}
                  type="button"
                  disabled={locked}
                  aria-pressed={on}
                  onClick={() => setActivities((l) => (on ? l.filter((x) => x !== t.id) : [...l, t.id]))}
                  className={cn('rounded-full px-3 py-1.5 text-sm font-medium', on ? 'bg-sky-brand-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200')}
                >
                  {t.name}
                </button>
              )
            })}
          </div>
        </div>

        {goals.length > 0 && (
          <div>
            <p className="mb-2 text-sm font-medium text-slate-700">Goal scores today (1–5)</p>
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
                        aria-label={`${g.title}: ${n}`}
                        onClick={() => setScores((s) => (s[g.id] === n ? Object.fromEntries(Object.entries(s).filter(([k]) => Number(k) !== g.id)) : { ...s, [g.id]: n }))}
                        className={cn('size-9 rounded-lg text-sm font-semibold', scores[g.id] === n ? 'bg-sky-brand-600 text-white' : 'bg-slate-100 text-slate-500')}
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

        <div className="grid gap-4 md:grid-cols-2">
          {fields.map(([key, label]) => (
            <Field key={key} label={label} htmlFor={`s_${key}`}>
              <Textarea id={`s_${key}`} rows={2} disabled={locked} value={text[key]} onChange={(e) => setText((t) => ({ ...t, [key]: e.target.value }))} />
            </Field>
          ))}
        </div>
        <Field label="Summary for parents" htmlFor="s_parent_summary" hint="Plain language. Required to finalize; shown in the parent portal.">
          <Textarea id="s_parent_summary" rows={3} disabled={locked} value={text.parent_summary} onChange={(e) => setText((t) => ({ ...t, parent_summary: e.target.value }))} />
        </Field>

        {!locked && (
          <div className="flex flex-col gap-2 sm:flex-row sm:justify-end">
            <Button variant="secondary" onClick={() => submit(false)} loading={save.isPending}>
              Save draft
            </Button>
            <Button onClick={() => submit(true)} loading={save.isPending}>
              Finalize session
            </Button>
          </div>
        )}
      </Card>
    </div>
  )
}
