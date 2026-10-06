import type { ReactNode } from 'react'
import { cn } from '../../utils/cn'

export function Card({ className, children }: { className?: string; children: ReactNode }) {
  return <div className={cn('rounded-xl border border-slate-200 bg-white shadow-xs', className)}>{children}</div>
}

export function PageHeader({ title, description, actions }: { title: string; description?: string; actions?: ReactNode }) {
  return (
    <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 className="text-xl font-semibold text-slate-900 sm:text-2xl">{title}</h1>
        {description && <p className="mt-1 text-sm text-slate-500">{description}</p>}
      </div>
      {actions && <div className="flex gap-2">{actions}</div>}
    </div>
  )
}

const badgeTones = {
  green: 'bg-brand-50 text-brand-700 ring-brand-600/20',
  blue: 'bg-sky-brand-50 text-sky-brand-700 ring-sky-brand-600/20',
  gray: 'bg-slate-100 text-slate-600 ring-slate-500/20',
  red: 'bg-red-50 text-red-700 ring-red-600/20',
  amber: 'bg-amber-50 text-amber-800 ring-amber-600/20',
}

export function Badge({ tone = 'gray', children }: { tone?: keyof typeof badgeTones; children: ReactNode }) {
  return (
    <span className={cn('inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset', badgeTones[tone])}>
      {children}
    </span>
  )
}

export function Alert({ tone = 'red', children }: { tone?: 'red' | 'green'; children: ReactNode }) {
  return (
    <div
      role="alert"
      className={cn(
        'rounded-lg border px-3 py-2 text-sm',
        tone === 'red' ? 'border-red-200 bg-red-50 text-red-700' : 'border-brand-200 bg-brand-50 text-brand-800',
      )}
    >
      {children}
    </div>
  )
}
