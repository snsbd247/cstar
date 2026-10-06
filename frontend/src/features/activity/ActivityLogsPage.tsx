import { useQuery } from '@tanstack/react-query'
import { Download, ShieldAlert, X } from 'lucide-react'
import { useState } from 'react'
import { Link, useSearchParams } from 'react-router'
import { api, apiUrl } from '../../api/client'
import { Badge, Card, PageHeader } from '../../components/ui/Card'
import { Input, Select } from '../../components/ui/Field'
import { Modal } from '../../components/ui/Modal'
import { Pager } from '../../components/ui/Pager'
import { Spinner } from '../../components/ui/Spinner'
import { UrlTabs } from '../../components/ui/Tabs'
import { todayISO } from '../../utils/format'
import { PatientPicker, type PickedPatient } from '../billing/components/PatientPicker'

interface LogRow {
  id: number
  at: string
  action: string
  user: { id: number; name: string } | null
  model: string | null
  record_id: number | null
  record: string | null
  patient: { id: number; name: string; patient_code: string } | null
  changes: { field: string; old: string | null; new: string | null }[]
  more_changes: number
  ip: string | null
  device: string | null
}

interface LoginSummary {
  logins: number
  failed: number
  people: number
  suspicious_ips: { ip: string; attempts: number; last_at: string }[]
}

type LogType = '' | 'system' | 'login' | 'patient' | 'clinical'

const types = [
  ['', 'All (Audit Logs)'],
  ['system', 'System Activity'],
  ['login', 'Login History'],
  ['patient', 'Patient Activity'],
  ['clinical', 'Clinical Access'],
] as const

const intro: Record<LogType, string> = {
  '': 'Every recorded action — who did what, when, from where. Entries can never be edited or deleted.',
  system: 'Records created, changed or deleted, and business actions such as voids, postings and approvals.',
  login: 'Sign-ins, sign-outs, failed attempts and password changes.',
  patient: 'Everything recorded against a child — pick a child to see their full history.',
  clinical: 'Who opened clinical information: clinical profiles, assessments and documents.',
}

const actionTone = (a: string) =>
  a === 'login_failed' || a === 'deleted' || a.endsWith('voided') || a === 'reversed' ? 'red' : a === 'created' || a === 'login' ? 'green' : a === 'viewed' || a === 'exported' ? 'amber' : a === 'updated' ? 'blue' : 'gray'

const actionLabel = (a: string) => a.replace(/[._]/g, ' ')

/** Activity Logs (Plan §২১): System Activity, Login History, Patient Activity, Clinical Access and the full audit log. */
export default function ActivityLogsPage() {
  const [params] = useSearchParams()
  const type = (params.get('type') ?? '') as LogType
  const [from, setFrom] = useState(type === 'login' ? todayISO().slice(0, 8) + '01' : '')
  const [to, setTo] = useState('')
  const [userId, setUserId] = useState('')
  const [action, setAction] = useState('')
  const [model, setModel] = useState('')
  const [patient, setPatient] = useState<PickedPatient | null>(null)
  const [picking, setPicking] = useState(false)
  const [page, setPage] = useState(1)
  const [open, setOpen] = useState<LogRow | null>(null)

  const query = { type: type || undefined, from: from || undefined, to: to || undefined, user_id: userId || undefined, action: action || undefined, model: model || undefined, patient_id: patient?.id }
  const { data: options } = useQuery({
    queryKey: ['audit-log-options'],
    queryFn: async () => (await api.get<{ data: { users: { id: number; name: string }[]; models: { value: string; label: string }[]; actions: string[] } }>('/audit-logs/options')).data.data,
  })
  const { data, isLoading } = useQuery({
    queryKey: ['audit-logs', query, page],
    queryFn: async () =>
      (await api.get<{ data: LogRow[]; meta: { current_page: number; last_page: number; total: number }; summary: LoginSummary | null }>('/audit-logs', { params: { ...query, page } })).data,
  })
  const exportUrl = apiUrl(`/audit-logs?${new URLSearchParams(Object.entries({ ...query, format: 'csv' }).filter(([, v]) => v !== undefined) as [string, string][])}`)
  const reset = <T,>(set: (v: T) => void) => (v: T) => (set(v), setPage(1))
  const title = types.find(([k]) => k === type)?.[1] ?? 'Activity Logs'

  return (
    <>
      <PageHeader
        title={title === 'All (Audit Logs)' ? 'Audit Logs' : title}
        description={intro[type]}
        actions={
          <a href={exportUrl} className="inline-flex min-h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            <Download className="size-4" /> Excel (CSV)
          </a>
        }
      />
      <UrlTabs tabs={types} param="type" />

      {data?.summary && <LoginSummaryCards summary={data.summary} />}

      <Card className="mb-4 grid gap-2 p-3 sm:grid-cols-2 lg:grid-cols-5">
        <Input type="date" value={from} onChange={(e) => reset(setFrom)(e.target.value)} aria-label="From date" />
        <Input type="date" value={to} onChange={(e) => reset(setTo)(e.target.value)} aria-label="To date" />
        <Select value={userId} onChange={(e) => reset(setUserId)(e.target.value)} aria-label="User">
          <option value="">Anyone</option>
          {options?.users.map((u) => (
            <option key={u.id} value={u.id}>
              {u.name}
            </option>
          ))}
        </Select>
        {type === 'patient' ? (
          patient ? (
            <span className="flex items-center justify-between gap-2 rounded-lg border border-slate-300 px-3 py-2 text-sm lg:col-span-2">
              <span className="truncate">
                {patient.name} <span className="text-slate-400">· {patient.patient_code}</span>
              </span>
              <button onClick={() => reset(setPatient)(null)} aria-label="Clear child" className="text-slate-400 hover:text-slate-700">
                <X className="size-4" />
              </button>
            </span>
          ) : (
            <button onClick={() => setPicking(true)} className="rounded-lg border border-dashed border-slate-300 px-3 py-2 text-left text-sm text-slate-500 hover:bg-slate-50 lg:col-span-2">
              Pick a child…
            </button>
          )
        ) : (
          <>
            <Select value={action} onChange={(e) => reset(setAction)(e.target.value)} aria-label="Action">
              <option value="">Any action</option>
              {options?.actions.map((a) => (
                <option key={a} value={a}>
                  {actionLabel(a)}
                </option>
              ))}
            </Select>
            <Select value={model} onChange={(e) => reset(setModel)(e.target.value)} aria-label="Record type">
              <option value="">Any record</option>
              {options?.models.map((m) => (
                <option key={m.value} value={m.value}>
                  {m.label}
                </option>
              ))}
            </Select>
          </>
        )}
      </Card>

      <Card className="overflow-hidden">
        {isLoading ? (
          <div className="p-6">
            <Spinner className="text-brand-600" />
          </div>
        ) : !data?.data.length ? (
          <p className="p-6 text-sm text-slate-500">Nothing recorded for these filters.</p>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                  <th className="px-4 py-2.5 font-medium">When</th>
                  <th className="px-4 py-2.5 font-medium">Who</th>
                  <th className="px-4 py-2.5 font-medium">Action</th>
                  <th className="px-4 py-2.5 font-medium">Record</th>
                  <th className="px-4 py-2.5 font-medium">Child</th>
                  <th className="px-4 py-2.5 font-medium">From</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {data.data.map((r) => (
                  <tr key={r.id} onClick={() => setOpen(r)} className="cursor-pointer hover:bg-slate-50">
                    <td className="whitespace-nowrap px-4 py-2.5 text-slate-600">{new Date(r.at).toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short' })}</td>
                    <td className="px-4 py-2.5 font-medium text-slate-900">{r.user?.name ?? <span className="text-slate-400">Unknown / system</span>}</td>
                    <td className="px-4 py-2.5">
                      <Badge tone={actionTone(r.action)}>{actionLabel(r.action)}</Badge>
                    </td>
                    <td className="px-4 py-2.5 text-slate-700">
                      {r.model && <span className="text-slate-500">{r.model} · </span>}
                      {r.record}
                      {r.changes.length > 0 && r.action === 'updated' && <span className="block text-xs text-slate-400">{r.changes.map((c) => c.field).join(', ')}</span>}
                    </td>
                    <td className="px-4 py-2.5">
                      {r.patient && (
                        <Link to={`/app/patients/${r.patient.id}`} onClick={(e) => e.stopPropagation()} className="text-brand-700 hover:underline">
                          {r.patient.name}
                        </Link>
                      )}
                    </td>
                    <td className="whitespace-nowrap px-4 py-2.5 text-xs text-slate-500">
                      {r.ip}
                      {r.device && <span className="block">{r.device}</span>}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
        <Pager meta={data?.meta} onPage={setPage} />
      </Card>

      {picking && (
        <Modal open title="Activity of one child" onClose={() => setPicking(false)}>
          <PatientPicker onPick={(p) => (reset(setPatient)(p), setPicking(false))} />
        </Modal>
      )}
      {open && <LogDetail row={open} onClose={() => setOpen(null)} />}
    </>
  )
}

function LoginSummaryCards({ summary }: { summary: LoginSummary }) {
  return (
    <div className="mb-4 grid gap-3 sm:grid-cols-3 lg:grid-cols-4">
      {[
        ['Sign-ins', summary.logins],
        ['Different people', summary.people],
        ['Failed attempts', summary.failed],
      ].map(([label, value]) => (
        <Card key={label} className="p-4">
          <p className="text-xs text-slate-500">{label}</p>
          <p className="mt-1 text-2xl font-semibold text-slate-900">{value}</p>
        </Card>
      ))}
      <Card className="p-4">
        <p className="flex items-center gap-1 text-xs text-slate-500">
          <ShieldAlert className="size-3.5" /> Addresses with 5+ failed attempts
        </p>
        {summary.suspicious_ips.length ? (
          <ul className="mt-1 space-y-0.5 text-sm">
            {summary.suspicious_ips.map((s) => (
              <li key={s.ip} className="flex justify-between gap-2 text-red-700">
                <span>{s.ip}</span>
                <span>{s.attempts}×</span>
              </li>
            ))}
          </ul>
        ) : (
          <p className="mt-1 text-sm text-brand-700">None — looks normal</p>
        )}
      </Card>
    </div>
  )
}

function LogDetail({ row, onClose }: { row: LogRow; onClose: () => void }) {
  return (
    <Modal open title={`${actionLabel(row.action)} · ${row.model ?? 'session'}`} onClose={onClose}>
      <dl className="grid grid-cols-[110px_1fr] gap-x-3 gap-y-1.5 text-sm">
        <dt className="text-slate-500">When</dt>
        <dd>{new Date(row.at).toLocaleString('en-GB', { dateStyle: 'full', timeStyle: 'medium' })}</dd>
        <dt className="text-slate-500">Who</dt>
        <dd>{row.user?.name ?? 'Unknown / system'}</dd>
        <dt className="text-slate-500">Record</dt>
        <dd>
          {row.model} {row.record} {row.record_id && <span className="text-slate-400">(id {row.record_id})</span>}
        </dd>
        {row.patient && (
          <>
            <dt className="text-slate-500">Child</dt>
            <dd>
              {row.patient.name} · {row.patient.patient_code}
            </dd>
          </>
        )}
        <dt className="text-slate-500">From</dt>
        <dd>
          {row.ip ?? '—'} {row.device && `· ${row.device}`}
        </dd>
      </dl>
      {row.changes.length > 0 && (
        <table className="mt-4 w-full text-sm">
          <thead className="text-left text-xs text-slate-500">
            <tr>
              <th className="py-1 font-medium">Field</th>
              {row.action !== 'created' && <th className="py-1 font-medium">Before</th>}
              {row.action !== 'deleted' && <th className="py-1 font-medium">{row.action === 'created' ? 'Value' : 'After'}</th>}
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {row.changes.map((c) => (
              <tr key={c.field}>
                <td className="py-1.5 pr-2 text-slate-600">{c.field}</td>
                {row.action !== 'created' && <td className="py-1.5 pr-2 text-red-700">{c.old ?? '—'}</td>}
                {row.action !== 'deleted' && <td className="py-1.5 text-brand-700">{c.new ?? '—'}</td>}
              </tr>
            ))}
          </tbody>
        </table>
      )}
      {row.more_changes > 0 && <p className="mt-2 text-xs text-slate-400">…and {row.more_changes} more fields (see the CSV export).</p>}
    </Modal>
  )
}
