import { cn } from '../../utils/cn'

/**
 * Small line chart drawn as SVG (no chart library on shared hosting). Gaps (null) break the line;
 * `max` fixes the top of the scale (100 for %, 5 for scores), otherwise the highest value is used.
 */
export function TrendChart({
  labels,
  values,
  max,
  color = 'text-brand-600',
  format = String,
  height = 96,
  label,
}: {
  labels: string[]
  values: (number | null)[]
  max?: number
  color?: string
  format?: (v: number) => string
  height?: number
  label: string
}) {
  const w = 300
  const pad = 14
  const top = max ?? Math.max(1, ...values.map((v) => v ?? 0))
  const x = (i: number) => pad + (values.length > 1 ? (i * (w - 2 * pad)) / (values.length - 1) : (w - 2 * pad) / 2)
  const y = (v: number) => pad + (1 - v / top) * (height - 2 * pad)
  const segments: string[] = []
  let current = ''
  values.forEach((v, i) => {
    if (v === null) {
      if (current) segments.push(current)
      current = ''
      return
    }
    current += `${current ? 'L' : 'M'}${x(i).toFixed(1)},${y(v).toFixed(1)} `
  })
  if (current) segments.push(current)

  return (
    <figure className={cn('w-full', color)}>
      <svg viewBox={`0 0 ${w} ${height}`} className="h-auto w-full overflow-visible" role="img" aria-label={label}>
        <line x1={pad} x2={w - pad} y1={height - pad} y2={height - pad} className="stroke-slate-200" strokeWidth="1" />
        {segments.map((d, i) => (
          <path key={i} d={d} fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" />
        ))}
        {values.map((v, i) =>
          v === null ? null : (
            <g key={i}>
              <circle cx={x(i)} cy={y(v)} r="3.5" fill="currentColor">
                <title>{`${labels[i]}: ${format(v)}`}</title>
              </circle>
              <text x={x(i)} y={y(v) - 7} textAnchor="middle" className="fill-slate-500 text-[9px]">
                {format(v)}
              </text>
            </g>
          ),
        )}
      </svg>
      <div className="flex justify-between px-1 text-[10px] text-slate-400">
        {labels.map((l, i) => (
          <span key={i}>{l}</span>
        ))}
      </div>
    </figure>
  )
}
