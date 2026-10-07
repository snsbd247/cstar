import { useQuery } from '@tanstack/react-query'
import { FileDown, Sheet } from 'lucide-react'
import { useMemo, useState } from 'react'
import { useSearchParams } from 'react-router'
import { api, apiUrl } from '../../api/client'
import { Button } from '../../components/ui/Button'
import { Card, PageHeader } from '../../components/ui/Card'
import { Input, Select } from '../../components/ui/Field'
import { Spinner } from '../../components/ui/Spinner'
import { useAuth } from '../../contexts/useAuth'
import { cn } from '../../utils/cn'
import { todayISO } from '../../utils/format'
import { taka } from '../billing/api'

interface CatalogItem {
  key: string
  group: string
  title: string
  filters: ('branch' | 'service' | 'therapist')[]
}

interface Column {
  key: string
  label: string
  type: 'text' | 'number' | 'money' | 'percent' | 'decimal' | 'date'
}

interface Report {
  key: string
  title: string
  columns: Column[]
  rows: Record<string, string | number | null>[]
  totals: Record<string, string | number | null> | null
  chart: { type: 'bar'; x: string; series: { key: string; label: string }[] } | null
  note?: string
}

const SERIES_COLORS = ['bg-brand-500', 'bg-sky-brand-500', 'bg-amber-400', 'bg-red-400']

const fmt = (v: string | number | null | undefined, type: Column['type']) => {
  if (v === null || v === undefined || v === '') return '—'
  switch (type) {
    case 'money':
      return taka(Number(v))
    case 'percent':
      return `${v}%`
    case 'decimal':
      return Number(v).toFixed(1)
    case 'date':
      return new Date(`${v}T00:00:00`).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })
    case 'number':
      return Number(v).toLocaleString('en-IN')
    default:
      return String(v)
  }
}

/** Plan §২০: pick a report → filter → table + chart → export PDF / Excel (.xlsx) / CSV. */
export default function ReportsPage() {
  const { user } = useAuth()
  const [params, setParams] = useSearchParams()
  const { data: catalog } = useQuery({ queryKey: ['report-catalog'], queryFn: async () => (await api.get<{ data: CatalogItem[] }>('/reports')).data.data })
  const key = params.get('r') ?? catalog?.[0]?.key
  const item = catalog?.find((c) => c.key === key)
  const [from, setFrom] = useState(todayISO().slice(0, 8) + '01')
  const [to, setTo] = useState(todayISO())
  const [branchId, setBranchId] = useState('')
  const query = { from, to, branch_id: branchId || undefined }
  const { data: report, isLoading } = useQuery({
    queryKey: ['report-run', key, query],
    queryFn: async () => (await api.get<{ data: Report }>(`/reports/${key}`, { params: query })).data.data,
    enabled: !!key,
  })
  const exportUrl = (format: 'pdf' | 'xlsx' | 'csv') =>
    apiUrl(`/reports/${key}?${new URLSearchParams(Object.entries({ ...query, format }).filter(([, v]) => v) as [string, string][])}`)
  const groups = useMemo(
    () => Object.entries((catalog ?? []).reduce<Record<string, CatalogItem[]>>((acc, c) => ({ ...acc, [c.group]: [...(acc[c.group] ?? []), c] }), {})),
    [catalog],
  )

  return (
    <>
      <PageHeader title="Reports" description="Every report can be printed (PDF) or downloaded for Excel." />
      <div className="grid gap-5 lg:grid-cols-[240px_1fr]">
        <Card className="h-fit p-2">
          {groups.map(([group, items]) => (
            <div key={group} className="mb-2">
              <p className="px-2 py-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">{group}</p>
              {items?.map((c) => (
                <button
                  key={c.key}
                  onClick={() => setParams({ r: c.key }, { replace: true })}
                  className={cn('block w-full rounded-lg px-2 py-1.5 text-left text-sm', c.key === key ? 'bg-brand-50 font-medium text-brand-700' : 'text-slate-600 hover:bg-slate-50')}
                >
                  {c.title}
                </button>
              ))}
            </div>
          ))}
        </Card>

        <div className="min-w-0 space-y-4">
          <Card className="flex flex-col gap-2 p-3 sm:flex-row sm:items-center">
            <Input type="date" value={from} onChange={(e) => setFrom(e.target.value)} className="sm:w-44" aria-label="From" />
            <Input type="date" value={to} max={todayISO()} onChange={(e) => setTo(e.target.value)} className="sm:w-44" aria-label="To" />
            {item?.filters.includes('branch') && (user?.branches?.length ?? 0) > 1 && (
              <Select value={branchId} onChange={(e) => setBranchId(e.target.value)} className="sm:w-48" aria-label="Branch">
                <option value="">All my branches</option>
                {user?.branches?.map((b) => (
                  <option key={b.id} value={b.id}>
                    {b.name}
                  </option>
                ))}
              </Select>
            )}
            {key && (
              <div className="flex gap-2 sm:ml-auto">
                <a href={exportUrl('pdf')} target="_blank" rel="noreferrer">
                  <Button variant="secondary">
                    <FileDown className="size-4" /> PDF
                  </Button>
                </a>
                <a href={exportUrl('xlsx')}>
                  <Button variant="secondary">
                    <Sheet className="size-4" /> Excel
                  </Button>
                </a>
                <a href={exportUrl('csv')} className="self-center text-xs text-slate-500 hover:text-brand-700">
                  CSV
                </a>
              </div>
            )}
          </Card>

          {isLoading || !report ? (
            <Spinner className="text-brand-600" />
          ) : (
            <>
              <div>
                <h2 className="text-lg font-semibold text-slate-900">{report.title}</h2>
                {report.note && <p className="text-sm text-slate-500">{report.note}</p>}
              </div>
              {report.chart && report.rows.length > 0 && <BarChart report={report} />}
              <Card className="overflow-x-auto">
                <table className="w-full min-w-[560px] text-sm">
                  <thead className="bg-slate-50 text-left text-xs text-slate-500">
                    <tr>
                      {report.columns.map((c) => (
                        <th key={c.key} className={cn('px-3 py-2 font-medium', c.type !== 'text' && c.type !== 'date' && 'text-right')}>
                          {c.label}
                        </th>
                      ))}
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {report.rows.map((row, i) => (
                      <tr key={i}>
                        {report.columns.map((c) => (
                          <td key={c.key} className={cn('px-3 py-2', c.type !== 'text' && c.type !== 'date' && 'text-right tabular-nums')}>
                            {fmt(row[c.key], c.type)}
                          </td>
                        ))}
                      </tr>
                    ))}
                    {report.rows.length === 0 && (
                      <tr>
                        <td colSpan={report.columns.length} className="px-3 py-6 text-center text-slate-500">
                          No data for this period.
                        </td>
                      </tr>
                    )}
                  </tbody>
                  {report.totals && report.rows.length > 0 && (
                    <tfoot className="border-t border-slate-200 font-semibold">
                      <tr>
                        {report.columns.map((c) => (
                          <td key={c.key} className={cn('px-3 py-2', c.type !== 'text' && c.type !== 'date' && 'text-right tabular-nums')}>
                            {report.totals![c.key] === undefined ? '' : c.type === 'date' ? report.totals![c.key] : fmt(report.totals![c.key], c.type)}
                          </td>
                        ))}
                      </tr>
                    </tfoot>
                  )}
                </table>
              </Card>
            </>
          )}
        </div>
      </div>
    </>
  )
}

/** Simple grouped bar chart drawn with CSS — no chart library needed on shared hosting. */
function BarChart({ report }: { report: Report }) {
  const { x, series } = report.chart!
  const rows = report.rows.slice(-31)
  const peak = Math.max(1, ...rows.flatMap((r) => series.map((s) => Number(r[s.key]) || 0)))
  const xCol = report.columns.find((c) => c.key === x)

  return (
    <Card className="p-4">
      <div className="flex h-48 items-end gap-2 overflow-x-auto" role="img" aria-label={`${report.title} chart`}>
        {rows.map((r, i) => (
          <div key={i} className="flex min-w-8 flex-1 flex-col items-center gap-1">
            <div className="flex h-40 w-full items-end justify-center gap-0.5">
              {series.map((s, si) => (
                <div
                  key={s.key}
                  className={cn('w-full max-w-6 rounded-t', SERIES_COLORS[si % SERIES_COLORS.length])}
                  style={{ height: `${((Number(r[s.key]) || 0) / peak) * 100}%` }}
                  title={`${s.label}: ${fmt(r[s.key], report.columns.find((c) => c.key === s.key)?.type ?? 'number')}`}
                />
              ))}
            </div>
            <span className="max-w-20 truncate text-[10px] text-slate-500">{fmt(r[x], xCol?.type ?? 'text')}</span>
          </div>
        ))}
      </div>
      <p className="mt-2 flex flex-wrap gap-3 text-xs text-slate-500">
        {series.map((s, si) => (
          <span key={s.key} className="flex items-center gap-1">
            <span className={cn('size-2.5 rounded-sm', SERIES_COLORS[si % SERIES_COLORS.length])} /> {s.label}
          </span>
        ))}
      </p>
    </Card>
  )
}
