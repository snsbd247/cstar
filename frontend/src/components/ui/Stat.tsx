import { Link } from 'react-router'
import { cn } from '../../utils/cn'
import { Card } from './Card'

/** One number on a dashboard; with `to` the whole card is a link to the list behind it. */
export function Stat({ label, value, hint, to, tone }: { label: string; value: string | number | null; hint?: string; to?: string; tone?: 'amber' | 'red' | 'green' }) {
  const body = (
    <>
      <p className="text-xs text-slate-500">{label}</p>
      <p className={cn('mt-1 text-2xl font-semibold text-slate-900', tone === 'amber' && 'text-amber-700', tone === 'red' && 'text-red-700', tone === 'green' && 'text-brand-700')}>{value ?? '—'}</p>
      {hint && <p className="text-xs text-slate-400">{hint}</p>}
    </>
  )

  return to ? (
    <Link to={to} className="block rounded-xl border border-slate-200 bg-white p-4 shadow-xs transition hover:border-brand-300">
      {body}
    </Link>
  ) : (
    <Card className="p-4">{body}</Card>
  )
}
