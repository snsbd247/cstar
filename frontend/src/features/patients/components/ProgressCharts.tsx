import { useQuery } from '@tanstack/react-query'
import { api } from '../../../api/client'
import { Card } from '../../../components/ui/Card'
import { Spinner } from '../../../components/ui/Spinner'
import { TrendChart } from '../../../components/ui/TrendChart'

export interface ProgressChartData {
  months: string[]
  attendance_rate: (number | null)[]
  performance: (number | null)[]
  therapy_sessions: number[]
  goals: { id: number; title: string; domain: string | null; progress_percent: number; status: string; scores: (number | null)[] }[]
}

export type ProgressWords = { attendance: string; performance: string; noRecords: string; sessions: string; goals: string; empty: string; progress: string; month: (ym: string) => string }

const english: ProgressWords = {
  attendance: 'Class attendance',
  performance: 'Class performance (1–5)',
  noRecords: 'Nothing recorded yet.',
  sessions: 'Therapy sessions',
  goals: 'Goal scores (monthly average, 1–5)',
  empty: 'No attendance, class records or session notes in the last six months yet.',
  progress: 'progress',
  month: (ym) => new Date(`${ym}-01`).toLocaleDateString('en-GB', { month: 'short' }),
}

/** Sprint 20: six months at a glance — used on the staff profile (English) and the parent portal (Bangla words). */
export function ProgressCharts({ url, words = english }: { url: string; words?: ProgressWords }) {
  const { data, isLoading } = useQuery({ queryKey: ['progress-chart', url], queryFn: async () => (await api.get<{ data: ProgressChartData }>(url)).data.data })
  if (isLoading || !data) return <Spinner className="text-brand-600" />
  const labels = data.months.map(words.month)
  const hasTraining = data.attendance_rate.some((v) => v !== null) || data.performance.some((v) => v !== null)
  const hasTherapy = data.therapy_sessions.some((v) => v > 0)
  const goals = data.goals.filter((g) => g.scores.some((s) => s !== null))
  if (!hasTraining && !hasTherapy && !goals.length) return <Card className="p-4 text-sm text-slate-500">{words.empty}</Card>

  return (
    <div className="grid gap-4 md:grid-cols-2">
      {hasTraining && (
        <>
          <Card className="p-4">
            <h3 className="mb-2 text-sm font-semibold text-slate-900">{words.attendance}</h3>
            {data.attendance_rate.some((v) => v !== null) ? (
              <TrendChart label={words.attendance} labels={labels} values={data.attendance_rate} max={100} format={(v) => `${v}%`} />
            ) : (
              <p className="text-sm text-slate-500">{words.noRecords}</p>
            )}
          </Card>
          <Card className="p-4">
            <h3 className="mb-2 text-sm font-semibold text-slate-900">{words.performance}</h3>
            {data.performance.some((v) => v !== null) ? (
              <TrendChart label={words.performance} labels={labels} values={data.performance} max={5} color="text-sky-brand-600" />
            ) : (
              <p className="text-sm text-slate-500">{words.noRecords}</p>
            )}
          </Card>
        </>
      )}
      {hasTherapy && (
        <Card className="p-4">
          <h3 className="mb-2 text-sm font-semibold text-slate-900">{words.sessions}</h3>
          <TrendChart label={words.sessions} labels={labels} values={data.therapy_sessions} color="text-violet-600" />
        </Card>
      )}
      {goals.length > 0 && (
        <Card className="p-4 md:col-span-2">
          <h3 className="mb-3 text-sm font-semibold text-slate-900">{words.goals}</h3>
          <div className="grid gap-4 md:grid-cols-2">
            {goals.map((g) => (
              <div key={g.id}>
                <p className="text-sm text-slate-800">
                  {g.title} <span className="text-xs text-slate-500">· {g.progress_percent}% {words.progress}</span>
                </p>
                <TrendChart label={g.title} labels={labels} values={g.scores} max={5} height={72} color="text-amber-500" />
              </div>
            ))}
          </div>
        </Card>
      )}
    </div>
  )
}
