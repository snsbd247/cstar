import { cn } from '../../utils/cn'

export function Logo({ compact, className }: { compact?: boolean; className?: string }) {
  return (
    <div className={cn('flex items-center gap-2', className)}>
      <span className="flex size-9 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-sky-brand-600 text-white shadow-sm">
        <svg viewBox="0 0 24 24" className="size-5" fill="currentColor" aria-hidden="true">
          <path d="M12 2.5l2.9 6 6.6.8-4.9 4.5 1.3 6.5L12 17l-5.9 3.3 1.3-6.5L2.5 9.3l6.6-.8z" />
        </svg>
      </span>
      {!compact && (
        <span className="leading-tight">
          <span className="block text-base font-bold tracking-tight text-slate-900">C-STAR</span>
          <span className="block text-[11px] text-slate-500">Speech Therapy &amp; Autism Rehabilitation</span>
        </span>
      )}
    </div>
  )
}
