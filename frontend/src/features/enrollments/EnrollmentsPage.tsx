import { useQuery } from '@tanstack/react-query'
import { ArrowRight, Search } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router'
import { api } from '../../api/client'
import { Card, PageHeader } from '../../components/ui/Card'
import { Input } from '../../components/ui/Field'
import { Pager } from '../../components/ui/Pager'
import { Spinner } from '../../components/ui/Spinner'
import { cn } from '../../utils/cn'
import { EnrollmentStatusBadge } from '../patients/components/badges'
import type { Enrollment, EnrollmentStatus } from '../patients/types'

type Meta = { current_page: number; last_page: number; total: number }

const statuses: [EnrollmentStatus | '', string][] = [
  ['', 'All'],
  ['active', 'Active'],
  ['pending', 'Pending'],
  ['on_hold', 'On hold'],
  ['completed', 'Completed'],
  ['discontinued', 'Discontinued'],
]

const titles: Record<string, string> = { training: 'Training Enrollments', therapy: 'Therapy Enrollments', active: 'Active Enrollments', on_hold: 'On Hold', completed: 'Completed', discontinued: 'Discontinued' }

/** Enrollments (Plan নীতি ৩): every programme a child is in — training and therapy, any status. Changes happen on the child's profile. */
export default function EnrollmentsPage() {
  const [params, setParams] = useSearchParams()
  const type = params.get('type') ?? ''
  const status = params.get('status') ?? ''
  const [search, setSearch] = useState('')
  const [q, setQ] = useState('')
  const [page, setPage] = useState(1)

  useEffect(() => {
    const t = setTimeout(() => (setQ(search.trim()), setPage(1)), 300)
    return () => clearTimeout(t)
  }, [search])

  const { data, isLoading } = useQuery({
    queryKey: ['enrollments', type, status, q, page],
    queryFn: async () =>
      (await api.get<{ data: Enrollment[]; meta: Meta; counts: Record<string, number> }>('/enrollments', { params: { type: type || undefined, status: status || undefined, q: q || undefined, page } })).data,
  })
  const set = (key: string, value: string) => {
    const next = new URLSearchParams(params)
    if (value) next.set(key, value)
    else next.delete(key)
    setParams(next)
  }
  const total = Object.values(data?.counts ?? {}).reduce((a, b) => a + b, 0)
  const title = type === 'therapy' && status === 'active' ? 'Therapy Patients' : (titles[status] ?? titles[type] ?? 'All Enrollments')

  return (
    <>
      <PageHeader
        title={title}
        description="One child can be in several programmes. To put one on hold, complete, discontinue or transfer it, open the child’s Enrollments tab."
        actions={
          <Link to="/app/enrollments/transfers" className="text-sm font-medium text-brand-700 hover:underline">
            Transfer history →
          </Link>
        }
      />
      <div className="mb-3 flex flex-wrap gap-1 border-b border-slate-200">
        {[
          ['', 'All programmes'],
          ['training', 'Training'],
          ['therapy', 'Therapy'],
        ].map(([key, label]) => (
          <button
            key={key}
            onClick={() => set('type', key)}
            className={cn('border-b-2 px-3 py-2.5 text-sm font-medium', type === key ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-800')}
          >
            {label}
          </button>
        ))}
      </div>
      <Card className="mb-4 flex flex-col gap-3 p-3 lg:flex-row lg:items-center">
        <div className="flex flex-wrap gap-1.5">
          {statuses.map(([key, label]) => (
            <button
              key={key}
              onClick={() => set('status', key)}
              className={cn(
                'rounded-full px-3 py-1 text-sm',
                status === key ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200',
              )}
            >
              {label} <span className={status === key ? 'text-brand-100' : 'text-slate-400'}>{key ? (data?.counts[key] ?? 0) : total}</span>
            </button>
          ))}
        </div>
        <div className="relative lg:ml-auto lg:w-72">
          <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
          <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Child, patient ID or enrollment no." className="pl-9" aria-label="Search enrollments" />
        </div>
      </Card>

      <Card className="overflow-hidden">
        {isLoading ? (
          <div className="p-6">
            <Spinner className="text-brand-600" />
          </div>
        ) : !data?.data.length ? (
          <p className="p-6 text-sm text-slate-500">No enrollments match.</p>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                  <th className="px-4 py-2.5 font-medium">Child</th>
                  <th className="px-4 py-2.5 font-medium">Programme</th>
                  <th className="px-4 py-2.5 font-medium">With</th>
                  <th className="px-4 py-2.5 font-medium">Branch</th>
                  <th className="px-4 py-2.5 font-medium">Started</th>
                  <th className="px-4 py-2.5 font-medium">Status</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {data.data.map((e) => (
                  <tr key={e.id} className="hover:bg-slate-50">
                    <td className="px-4 py-2.5">
                      <Link to={`/app/patients/${e.patient?.id}?tab=enrollments`} className="font-medium text-slate-900 hover:text-brand-700">
                        {e.patient?.name}
                      </Link>
                      <span className="block text-xs text-slate-500">
                        {e.patient?.patient_code} · {e.enrollment_code}
                      </span>
                    </td>
                    <td className="px-4 py-2.5 text-slate-700">
                      {e.training ? `Regular Training — ${e.training.class.name}` : e.therapy?.service.name}
                      {e.therapy?.sessions_per_week && <span className="block text-xs text-slate-500">{e.therapy.sessions_per_week}× a week</span>}
                    </td>
                    <td className="px-4 py-2.5 text-slate-700">{e.training?.trainer.name ?? e.therapy?.therapist.name}</td>
                    <td className="px-4 py-2.5 text-slate-600">{e.branch?.name}</td>
                    <td className="whitespace-nowrap px-4 py-2.5 text-slate-600">
                      {e.start_date}
                      {e.end_date && <span className="block text-xs text-slate-400">ended {e.end_date}</span>}
                    </td>
                    <td className="px-4 py-2.5">
                      <EnrollmentStatusBadge status={e.status} />
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
        <Pager meta={data?.meta} onPage={setPage} />
      </Card>
    </>
  )
}

interface Transfer {
  id: number
  date: string
  enrollment: { id: number; code: string; type: string; summary: string }
  patient: { id: number; name: string; patient_code: string }
  branch: string
  from: string | null
  to: string
  reason: string | null
  by: string | null
}

/** Enrollments → Transfer History: every class, trainer or therapist change, with the reason. */
export function TransfersPage() {
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const [page, setPage] = useState(1)
  const { data, isLoading } = useQuery({
    queryKey: ['enrollment-transfers', from, to, page],
    queryFn: async () => (await api.get<{ data: Transfer[]; meta: Meta }>('/enrollment-transfers', { params: { from: from || undefined, to: to || undefined, page } })).data,
  })

  return (
    <>
      <PageHeader title="Transfer History" description="Children moved to another class, trainer or therapist. Transfers are made from the child’s Enrollments tab." />
      <Card className="mb-4 flex flex-col gap-2 p-3 sm:flex-row">
        <Input type="date" value={from} onChange={(e) => (setFrom(e.target.value), setPage(1))} className="sm:w-44" aria-label="From date" />
        <Input type="date" value={to} onChange={(e) => (setTo(e.target.value), setPage(1))} className="sm:w-44" aria-label="To date" />
      </Card>
      <Card className="overflow-hidden">
        {isLoading ? (
          <div className="p-6">
            <Spinner className="text-brand-600" />
          </div>
        ) : !data?.data.length ? (
          <p className="p-6 text-sm text-slate-500">No transfers in this period.</p>
        ) : (
          <ul className="divide-y divide-slate-100">
            {data.data.map((t) => (
              <li key={t.id} className="px-4 py-3">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <Link to={`/app/patients/${t.patient.id}?tab=enrollments`} className="font-medium text-slate-900 hover:text-brand-700">
                    {t.patient.name} <span className="text-xs font-normal text-slate-500">· {t.enrollment.summary}</span>
                  </Link>
                  <span className="text-xs text-slate-500">
                    {t.date} · {t.branch}
                    {t.by && ` · by ${t.by}`}
                  </span>
                </div>
                <p className="mt-1 flex flex-wrap items-center gap-2 text-sm">
                  <span className="text-slate-500 line-through decoration-slate-300">{t.from ?? '—'}</span>
                  <ArrowRight className="size-3.5 text-slate-400" />
                  <span className="font-medium text-brand-700">{t.to}</span>
                </p>
                {t.reason && <p className="text-xs text-slate-500">Reason: {t.reason}</p>}
              </li>
            ))}
          </ul>
        )}
        <Pager meta={data?.meta} onPage={setPage} />
      </Card>
    </>
  )
}
