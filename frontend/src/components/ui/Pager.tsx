import { ChevronLeft, ChevronRight } from 'lucide-react'
import { Button } from './Button'

/** "Page 2 of 7 · 140 total" with previous / next buttons; hidden when everything fits on one page. */
export function Pager({ meta, onPage }: { meta?: { current_page: number; last_page: number; total: number }; onPage: (page: number) => void }) {
  if (!meta || meta.last_page <= 1) return null

  return (
    <div className="flex items-center justify-between gap-3 border-t border-slate-100 px-4 py-3 text-sm text-slate-500">
      <span>
        Page {meta.current_page} of {meta.last_page} · {meta.total} total
      </span>
      <div className="flex gap-2">
        <Button variant="secondary" disabled={meta.current_page <= 1} onClick={() => onPage(meta.current_page - 1)} aria-label="Previous page">
          <ChevronLeft className="size-4" />
        </Button>
        <Button variant="secondary" disabled={meta.current_page >= meta.last_page} onClick={() => onPage(meta.current_page + 1)} aria-label="Next page">
          <ChevronRight className="size-4" />
        </Button>
      </div>
    </div>
  )
}
