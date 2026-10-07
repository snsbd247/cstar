import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { api, errorMessage } from '../../api/client'
import { cn } from '../../utils/cn'
import { bnWeekday } from './api'

export interface Practice {
  today: { status: PracticeStatus; comment: string | null } | null
  week: { date: string; status: PracticeStatus }[]
}
type PracticeStatus = 'done' | 'partly' | 'not_done'

const choices: [PracticeStatus, string, string][] = [
  ['done', 'করেছি', 'border-brand-500 bg-brand-600 text-white'],
  ['partly', 'কিছুটা', 'border-amber-400 bg-amber-400 text-white'],
  ['not_done', 'পারিনি', 'border-slate-400 bg-slate-500 text-white'],
]
const dot: Record<PracticeStatus, string> = { done: 'bg-brand-500', partly: 'bg-amber-400', not_done: 'bg-slate-400' }

const lastSevenDays = () =>
  Array.from({ length: 7 }, (_, i) => {
    const d = new Date()
    d.setDate(d.getDate() - 6 + i)
    return d.toLocaleDateString('en-CA')
  })

/** Sprint 20: under the home practice, the family says whether they did it today; the therapist sees it. */
export function PracticeFeedback({ childId, sessionId, practice }: { childId: number; sessionId: number; practice: Practice }) {
  const qc = useQueryClient()
  const [comment, setComment] = useState(practice.today?.comment ?? '')
  const [showComment, setShowComment] = useState(false)
  const save = useMutation({
    mutationFn: (status: PracticeStatus) => api.post(`/portal/children/${childId}/home-practice/${sessionId}`, { status, comment: comment || null }),
    onSuccess: () => (setShowComment(false), qc.invalidateQueries({ queryKey: ['portal'] })),
  })
  const byDate = Object.fromEntries(practice.week.map((w) => [w.date, w.status]))

  return (
    <div className="mt-2 space-y-2">
      <p className="text-xs font-medium text-slate-600">আজ অনুশীলন করেছেন?</p>
      <div className="grid grid-cols-3 gap-1.5">
        {choices.map(([status, label, active]) => (
          <button
            key={status}
            disabled={save.isPending}
            onClick={() => save.mutate(status)}
            className={cn('rounded-lg border py-1.5 text-sm', practice.today?.status === status ? active : 'border-slate-200 bg-white text-slate-700')}
          >
            {label}
          </button>
        ))}
      </div>
      {showComment ? (
        <textarea
          rows={2}
          value={comment}
          onChange={(e) => setComment(e.target.value)}
          placeholder="থেরাপিস্টকে কিছু জানাতে চাইলে লিখুন, তারপর উপরের একটি বোতাম চাপুন"
          className="w-full rounded-lg border border-slate-200 p-2 text-sm"
        />
      ) : (
        <button onClick={() => setShowComment(true)} className="text-xs text-brand-700 hover:underline">
          {practice.today?.comment ? `মন্তব্য: ${practice.today.comment}` : '+ মন্তব্য লিখুন'}
        </button>
      )}
      <div className="flex items-center gap-1.5" aria-label="গত ৭ দিন">
        {lastSevenDays().map((d) => (
          <span key={d} title={bnWeekday(d)} className="flex flex-col items-center gap-0.5">
            <span className={cn('size-3 rounded-full', byDate[d] ? dot[byDate[d]] : 'bg-slate-200')} />
            <span className="text-[10px] text-slate-400">{bnWeekday(d).slice(0, 2)}</span>
          </span>
        ))}
      </div>
      {save.isError && <p className="text-xs text-red-600">{errorMessage(save.error)}</p>}
    </div>
  )
}
