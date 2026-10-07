import type { PracticeLog } from '../api'

const label: Record<PracticeLog['status'], string> = { done: 'done', partly: 'partly', not_done: 'not done' }

/** Sprint 20: what the family reported from home about the last home practice — counts plus their comments. */
export function PracticeFeedbackSummary({ logs }: { logs: PracticeLog[] }) {
  const count = (s: PracticeLog['status']) => logs.filter((l) => l.status === s).length
  if (!logs.length) return <p className="mt-1 text-xs text-slate-400">No feedback from the family yet.</p>

  return (
    <div className="mt-1 text-xs text-slate-600">
      <span className="text-slate-500">Family reported:</span> <b className="text-brand-700">{count('done')} done</b> · <span className="text-amber-700">{count('partly')} partly</span> ·{' '}
      {count('not_done')} not done
      {logs
        .filter((l) => l.comment)
        .slice(0, 3)
        .map((l) => (
          <p key={l.date} className="text-slate-500">
            {l.date} ({label[l.status]}): “{l.comment}”
          </p>
        ))}
    </div>
  )
}
