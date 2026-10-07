import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { CheckCircle2, MessageSquareWarning } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router'
import { api, errorMessage, validationErrors } from '../../api/client'
import { Button } from '../../components/ui/Button'
import { Alert, Badge, Card } from '../../components/ui/Card'
import { Textarea } from '../../components/ui/Field'
import { Spinner } from '../../components/ui/Spinner'
import { cn } from '../../utils/cn'

export interface Review {
  id: number
  outcome: 'ok' | 'needs_changes'
  comment: string | null
  by: string | null
  at: string
}
interface Item {
  type: 'session' | 'assessment'
  id: number
  link_id: number
  date: string
  patient: { id: number; name: string; patient_code: string }
  therapist: string | null
  title: string | null
  summary: string | null
  review: Review | null
}

/** Sprint 22 (Plan #২০): the clinical supervisor reviews colleagues' finalized notes and assessments. */
export function ClinicalReviewPage() {
  const [status, setStatus] = useState<'pending' | 'reviewed'>('pending')
  const { data, isLoading } = useQuery({
    queryKey: ['clinical-reviews', status],
    queryFn: async () => (await api.get<{ data: Item[] }>('/clinical-reviews', { params: { status } })).data.data,
  })

  return (
    <div className="space-y-4">
      <div>
        <h1 className="text-xl font-semibold text-slate-900">Clinical review</h1>
        <p className="text-sm text-slate-500">Finalized session notes and assessments of your branch from the last 60 days. “Needs changes” asks the therapist to amend.</p>
      </div>
      <div className="flex gap-1">
        {(['pending', 'reviewed'] as const).map((s) => (
          <button key={s} onClick={() => setStatus(s)} className={cn('rounded-lg px-3 py-1.5 text-sm', status === s ? 'bg-brand-600 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200')}>
            {s === 'pending' ? 'To review' : 'Reviewed'}
          </button>
        ))}
      </div>
      {isLoading ? (
        <Spinner className="text-brand-600" />
      ) : !data?.length ? (
        <Card className="p-4 text-sm text-slate-500">{status === 'pending' ? 'Nothing waiting for review.' : 'No reviews yet.'}</Card>
      ) : (
        data.map((item) => <ReviewItem key={`${item.type}-${item.id}`} item={item} />)
      )}
    </div>
  )
}

function ReviewItem({ item }: { item: Item }) {
  const qc = useQueryClient()
  const [comment, setComment] = useState('')
  const [error, setError] = useState<string | null>(null)
  const save = useMutation({
    mutationFn: (outcome: 'ok' | 'needs_changes') => api.post('/clinical-reviews', { type: item.type, id: item.id, outcome, comment: comment || null }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['clinical-reviews'] }),
    onError: (e) => setError(Object.values(validationErrors(e))[0] ?? errorMessage(e)),
  })
  const link = item.type === 'session' ? `/therapist/session/${item.link_id}` : `/therapist/assessments/${item.link_id}`

  return (
    <Card className="space-y-2 p-4">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <p className="text-sm">
          <Link to={link} className="font-semibold text-slate-900 hover:text-brand-700">
            {item.patient.name}
          </Link>{' '}
          <span className="text-slate-500">
            · {item.type === 'session' ? 'Session' : 'Assessment'} · {item.title} · {item.date} · {item.therapist}
          </span>
        </p>
        {item.review && <ReviewBadge review={item.review} />}
      </div>
      {item.summary && <p className="line-clamp-3 text-sm text-slate-700">{item.summary}</p>}
      {item.review?.comment && <p className="text-xs text-slate-500">“{item.review.comment}” — {item.review.by}</p>}
      {!item.review && (
        <>
          <Textarea rows={2} value={comment} onChange={(e) => setComment(e.target.value)} placeholder="Comment (required for “Needs changes”)" aria-label="Review comment" />
          {error && <Alert>{error}</Alert>}
          <div className="flex gap-2">
            <Button className="min-h-8 px-3 text-xs" loading={save.isPending && save.variables === 'ok'} onClick={() => save.mutate('ok')}>
              <CheckCircle2 className="size-3.5" /> OK
            </Button>
            <Button variant="secondary" className="min-h-8 px-3 text-xs" loading={save.isPending && save.variables === 'needs_changes'} onClick={() => save.mutate('needs_changes')}>
              <MessageSquareWarning className="size-3.5" /> Needs changes
            </Button>
          </div>
        </>
      )}
    </Card>
  )
}

export function ReviewBadge({ review }: { review: Review }) {
  return <Badge tone={review.outcome === 'ok' ? 'green' : 'amber'}>{review.outcome === 'ok' ? 'Reviewed — OK' : 'Needs changes'}</Badge>
}

/** Under a final note / assessment: what the supervisor said (Sprint 22). */
export function ReviewHistory({ type, id }: { type: 'session' | 'assessment'; id: number }) {
  const { data } = useQuery({ queryKey: ['clinical-review', type, id], queryFn: async () => (await api.get<{ data: Review[] }>(`/clinical-reviews/${type}/${id}`)).data.data })
  if (!data?.length) return null

  return (
    <Card className="space-y-2 p-4 text-sm">
      <p className="font-semibold text-slate-900">Supervisor review</p>
      {data.map((r) => (
        <div key={r.id}>
          <ReviewBadge review={r} /> <span className="text-xs text-slate-500">{r.by} · {new Date(r.at).toLocaleDateString('en-GB')}</span>
          {r.comment && <p className="mt-1 text-slate-700">{r.comment}</p>}
        </div>
      ))}
    </Card>
  )
}
