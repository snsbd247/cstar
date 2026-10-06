import { Card } from '../../../components/ui/Card'
import { Spinner } from '../../../components/ui/Spinner'
import { useTimeline } from '../api'

/** Unified history; sessions, assessments and payments join this feed in later sprints. */
export function TimelineTab({ patientId }: { patientId: number }) {
  const { data: events, isLoading } = useTimeline(patientId)

  if (isLoading) return <Spinner className="text-brand-600" />
  if (!events?.length) return <Card className="p-5 text-sm text-slate-500">No history yet.</Card>

  return (
    <Card className="p-5">
      <ol className="relative space-y-5 border-l border-slate-200 pl-5">
        {events.map((e) => (
          <li key={e.id} className="relative">
            <span className="absolute -left-[26px] top-1.5 size-2.5 rounded-full bg-brand-500 ring-4 ring-white" />
            <p className="text-xs text-slate-400">
              {new Date(e.occurred_at).toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Dhaka' })}
              {e.actor && ` · ${e.actor}`}
            </p>
            <p className="text-sm font-medium text-slate-900">{e.title}</p>
            {e.description && <p className="text-sm text-slate-600">{e.description}</p>}
          </li>
        ))}
      </ol>
    </Card>
  )
}
