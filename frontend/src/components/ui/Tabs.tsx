import { useSearchParams } from 'react-router'
import { cn } from '../../utils/cn'

/** Underlined tabs kept in the URL (?tab= by default) so menu links and reloads land on the same tab. */
export function UrlTabs({ tabs, param = 'tab', fallback }: { tabs: readonly (readonly [string, string])[]; param?: string; fallback?: string }) {
  const [params, setParams] = useSearchParams()
  const current = params.get(param) ?? fallback ?? tabs[0]?.[0]

  return (
    <div className="mb-4 flex gap-1 overflow-x-auto border-b border-slate-200">
      {tabs.map(([key, label]) => (
        <button
          key={key}
          onClick={() => setParams(key === (fallback ?? tabs[0]?.[0]) ? {} : { [param]: key }, { replace: true })}
          className={cn(
            'whitespace-nowrap border-b-2 px-3 py-2.5 text-sm font-medium',
            current === key ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-800',
          )}
        >
          {label}
        </button>
      ))}
    </div>
  )
}
