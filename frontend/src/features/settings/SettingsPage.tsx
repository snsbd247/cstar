import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { CheckCircle2, Download, HardDriveDownload, RefreshCw, Trash2, XCircle } from 'lucide-react'
import { useState } from 'react'
import { Link, useSearchParams } from 'react-router'
import { api, apiUrl, errorMessage, validationErrors } from '../../api/client'
import { Button } from '../../components/ui/Button'
import { Alert, Badge, Card, PageHeader } from '../../components/ui/Card'
import { Field, Input, Select, Textarea } from '../../components/ui/Field'
import { Spinner } from '../../components/ui/Spinner'
import { UrlTabs } from '../../components/ui/Tabs'
import { ApprovalLimitCard } from '../accounts/AccountsPage'
import { BillingSettingsForm } from '../billing/PackagesPage'
import { useBranches } from '../branches/api'
import { NotificationSettingsCard } from '../notifications/NotificationsPage'

type Groups = Record<string, Record<string, string>>
type FieldDef = { key: string; label: string; type?: 'text' | 'number' | 'bool' | 'color' | 'textarea' | 'select'; hint?: string; options?: [string, string][] }

const tabs = [
  ['general', 'General'],
  ['center', 'Center Information'],
  ['branch', 'Branch'],
  ['patient-id', 'Patient ID'],
  ['appointment', 'Appointment'],
  ['billing', 'Billing'],
  ['accounts', 'Accounts'],
  ['notification', 'Notification'],
  ['language', 'Language'],
  ['pdf', 'PDF'],
  ['security', 'Security'],
  ['backup', 'Backup'],
  ['system', 'System'],
] as const

const forms: Record<string, { group: string; title: string; intro: string; fields: FieldDef[] }> = {
  general: {
    group: 'general',
    title: 'General',
    intro: 'The center’s name as it appears on every PDF (reports, invoices, receipts, payslips).',
    fields: [
      { key: 'center_short_name', label: 'Short name', hint: 'Large title at the top of PDFs, e.g. C-STAR' },
      { key: 'center_full_name', label: 'Full name' },
    ],
  },
  center: {
    group: 'center',
    title: 'Center Information',
    intro: 'Printed in the PDF letterhead and footer. Empty fields fall back to the website contact details (Website / CMS).',
    fields: [
      { key: 'legal_name', label: 'Registered (legal) name', hint: 'Replaces the full name on PDFs when filled in' },
      { key: 'address', label: 'Address', type: 'textarea' },
      { key: 'phone', label: 'Phone' },
      { key: 'email', label: 'Email' },
      { key: 'website', label: 'Website' },
      { key: 'registration_no', label: 'Registration no.', hint: 'e.g. Social Services / NGO registration' },
      { key: 'tin', label: 'TIN' },
    ],
  },
  'patient-id': {
    group: 'patient_id',
    title: 'Patient ID',
    intro: 'Format of the ID every new child gets at registration. Existing IDs never change.',
    fields: [
      { key: 'prefix', label: 'Prefix', hint: 'Capital letters and digits only' },
      { key: 'digits', label: 'Number of digits', type: 'number' },
      { key: 'include_year', label: 'Include the year (numbers restart every year)', type: 'bool' },
      { key: 'include_branch_code', label: 'Include the branch code (each branch counts on its own)', type: 'bool' },
    ],
  },
  appointment: {
    group: 'appointment',
    title: 'Appointment',
    intro: 'Rules used when booking and cancelling therapy appointments.',
    fields: [
      { key: 'late_cancel_hours', label: 'Late cancellation window (hours)', type: 'number', hint: 'Cancelling closer than this to the start is a late cancellation (decision D3)' },
      { key: 'recurring_weeks', label: 'Book weekly slots ahead (weeks)', type: 'number', hint: 'Nightly job and the “Generate” button book this far ahead' },
      { key: 'portal_requests_enabled', label: 'Parents may request appointments from the parent portal', type: 'bool' },
    ],
  },
  pdf: {
    group: 'pdf',
    title: 'PDF',
    intro: 'Look of every PDF the system prints.',
    fields: [
      { key: 'paper_size', label: 'Paper size', type: 'select', options: [['A4', 'A4'], ['Letter', 'Letter'], ['Legal', 'Legal']] },
      { key: 'brand_color', label: 'Heading colour', type: 'color' },
      { key: 'footer_note', label: 'Footer note', type: 'textarea' },
      { key: 'show_printed_date', label: 'Show the printed date in the footer', type: 'bool' },
    ],
  },
  security: {
    group: 'security',
    title: 'Security',
    intro: 'Sign-in rules for all staff and parents. Changes apply from the next sign-in or password change.',
    fields: [
      { key: 'session_timeout_minutes', label: 'Sign out after this many idle minutes', type: 'number' },
      { key: 'password_min_length', label: 'Minimum password length', type: 'number', hint: 'Passwords always need letters and numbers' },
      { key: 'password_require_symbol', label: 'Passwords must also contain a symbol', type: 'bool' },
      { key: 'login_attempts_per_minute', label: 'Sign-in attempts allowed per minute', type: 'number', hint: 'Per account and address — slows down password guessing' },
    ],
  },
  backup: {
    group: 'backup',
    title: 'Backup schedule',
    intro: 'The database is backed up every night at 2:30 AM. Uploaded documents are covered by the cPanel account backup.',
    fields: [
      { key: 'daily_enabled', label: 'Back up the database every night', type: 'bool' },
      { key: 'keep_days', label: 'Keep backups for (days)', type: 'number' },
    ],
  },
}

/** Settings (Sprint 16) — Super Admin. Billing, accounts and notification settings reuse their own forms. */
export default function SettingsPage() {
  const [params] = useSearchParams()
  const tab = params.get('tab') ?? 'general'
  const { data, isLoading } = useQuery({
    queryKey: ['settings'],
    queryFn: async () => (await api.get<{ data: { groups: Groups; patient_id_example: string | null } }>('/settings')).data.data,
  })

  return (
    <>
      <PageHeader title="Settings" description="How C-STAR works for everyone. Every change is recorded in the activity log." />
      <UrlTabs tabs={tabs} fallback="general" />
      {isLoading || !data ? (
        <Spinner className="text-brand-600" />
      ) : forms[tab] ? (
        <div className="space-y-4">
          <GroupForm key={tab} def={forms[tab]} values={data.groups[forms[tab].group]} example={tab === 'patient-id' ? data.patient_id_example : null} />
          {tab === 'backup' && <Backups />}
        </div>
      ) : tab === 'branch' ? (
        <BranchSettings />
      ) : tab === 'billing' ? (
        <Card className="max-w-2xl p-5">
          <BillingSettingsForm />
        </Card>
      ) : tab === 'accounts' ? (
        <div className="max-w-md">
          <ApprovalLimitCard />
        </div>
      ) : tab === 'notification' ? (
        <div className="max-w-xl">
          <NotificationSettingsCard />
        </div>
      ) : tab === 'language' ? (
        <LanguageInfo />
      ) : (
        <SystemInfo />
      )}
    </>
  )
}

function GroupForm({ def, values, example }: { def: (typeof forms)[string]; values: Record<string, string>; example: string | null }) {
  const qc = useQueryClient()
  const [v, setV] = useState(values)
  const [errors, setErrors] = useState<Record<string, string>>({})
  const save = useMutation({
    mutationFn: async () => (await api.put<{ data: { groups: Groups; patient_id_example: string | null } }>(`/settings/${def.group}`, v)).data.data,
    onSuccess: (d) => (qc.setQueryData(['settings'], d), setErrors({})),
    onError: (e) => setErrors(validationErrors(e)),
  })
  const dirty = def.fields.some((f) => v[f.key] !== values[f.key])

  return (
    <Card className="max-w-2xl space-y-4 p-5">
      <div>
        <h2 className="font-semibold text-slate-900">{def.title}</h2>
        <p className="text-sm text-slate-500">{def.intro}</p>
      </div>
      {save.isError && !Object.keys(errors).length && <Alert>{errorMessage(save.error)}</Alert>}
      {save.isSuccess && !dirty && <Alert tone="green">Saved.</Alert>}
      {def.fields.map((f) =>
        f.type === 'bool' ? (
          <label key={f.key} className="flex items-start gap-2 text-sm text-slate-700">
            <input type="checkbox" className="mt-0.5 size-4 accent-brand-600" checked={v[f.key] === '1'} onChange={(e) => setV({ ...v, [f.key]: e.target.checked ? '1' : '0' })} />
            {f.label}
          </label>
        ) : (
          <Field key={f.key} label={f.label} htmlFor={`s_${f.key}`} hint={f.hint} error={errors[f.key]}>
            {f.type === 'textarea' ? (
              <Textarea id={`s_${f.key}`} rows={2} value={v[f.key] ?? ''} onChange={(e) => setV({ ...v, [f.key]: e.target.value })} />
            ) : f.type === 'select' ? (
              <Select id={`s_${f.key}`} value={v[f.key]} onChange={(e) => setV({ ...v, [f.key]: e.target.value })}>
                {f.options?.map(([value, label]) => (
                  <option key={value} value={value}>
                    {label}
                  </option>
                ))}
              </Select>
            ) : f.type === 'color' ? (
              <div className="flex items-center gap-2">
                <input type="color" aria-label={f.label} value={v[f.key]} onChange={(e) => setV({ ...v, [f.key]: e.target.value })} className="h-10 w-14 rounded border border-slate-300" />
                <Input id={`s_${f.key}`} value={v[f.key]} onChange={(e) => setV({ ...v, [f.key]: e.target.value })} className="w-32" />
              </div>
            ) : (
              <Input id={`s_${f.key}`} type={f.type === 'number' ? 'number' : 'text'} value={v[f.key] ?? ''} onChange={(e) => setV({ ...v, [f.key]: e.target.value })} invalid={!!errors[f.key]} />
            )}
          </Field>
        ),
      )}
      {example && (
        <p className="rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-600">
          Next ID will look like <b className="font-mono text-slate-900">{example}</b> {dirty && <span className="text-slate-400">(after saving)</span>}
        </p>
      )}
      <div className="flex justify-end gap-2">
        {dirty && (
          <Button variant="ghost" onClick={() => (setV(values), setErrors({}))}>
            Undo changes
          </Button>
        )}
        <Button loading={save.isPending} disabled={!dirty} onClick={() => save.mutate()}>
          Save
        </Button>
      </div>
    </Card>
  )
}

function BranchSettings() {
  const { data: branches, isLoading } = useBranches()

  return (
    <Card className="max-w-3xl overflow-hidden">
      <div className="flex items-center justify-between gap-2 border-b border-slate-100 px-5 py-4">
        <div>
          <h2 className="font-semibold text-slate-900">Branches</h2>
          <p className="text-sm text-slate-500">Code (used in patient IDs when turned on), contact details, opening hours and website visibility.</p>
        </div>
        <Link to="/app/branches" className="whitespace-nowrap text-sm font-medium text-brand-700 hover:underline">
          Edit branches →
        </Link>
      </div>
      {isLoading ? (
        <div className="p-5">
          <Spinner className="text-brand-600" />
        </div>
      ) : (
        <ul className="divide-y divide-slate-100">
          {branches?.map((b) => (
            <li key={b.id} className="flex flex-wrap items-center justify-between gap-2 px-5 py-3 text-sm">
              <div>
                <p className="font-medium text-slate-900">
                  {b.name} <span className="font-mono text-xs text-slate-500">{b.code}</span>
                </p>
                <p className="text-xs text-slate-500">{[b.phone, b.email, b.address].filter(Boolean).join(' · ') || 'No contact details yet'}</p>
              </div>
              <span className="flex gap-1">
                <Badge tone={b.is_active ? 'green' : 'gray'}>{b.is_active ? 'Active' : 'Inactive'}</Badge>
                {b.show_on_website && <Badge tone="blue">On website</Badge>}
              </span>
            </li>
          ))}
        </ul>
      )}
    </Card>
  )
}

function LanguageInfo() {
  const rows = [
    ['Staff panel, trainer and therapist apps', 'English', 'Clinical and accounting terms stay in English so staff and reports match.'],
    ['Parent portal', 'বাংলা', 'Decision D7 — parents read everything in Bangla, with Bangla numbers and dates.'],
    ['Messages to parents (in-app, email)', 'বাংলা', 'Appointment, bill, payment and report messages.'],
    ['Public website', 'English', 'Bangla page content can be written in Website / CMS.'],
    ['PDFs', 'English + বাংলা', 'Bangla text (names, notes) prints with correct conjuncts.'],
  ]

  return (
    <Card className="max-w-3xl p-5">
      <h2 className="font-semibold text-slate-900">Language</h2>
      <p className="text-sm text-slate-500">Languages are fixed by the approved plan, so everyone sees the same wording.</p>
      <table className="mt-3 w-full text-sm">
        <tbody className="divide-y divide-slate-100">
          {rows.map(([where, lang, note]) => (
            <tr key={where}>
              <td className="py-2.5 pr-3 font-medium text-slate-800">{where}</td>
              <td className="py-2.5 pr-3">
                <Badge tone="blue">{lang}</Badge>
              </td>
              <td className="py-2.5 text-slate-500">{note}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </Card>
  )
}

const kb = (bytes: number) => (bytes > 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${(bytes / 1024).toFixed(1)} KB`)
const when = (iso: string) => new Date(iso).toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short' })

function Backups() {
  const qc = useQueryClient()
  const { data } = useQuery({ queryKey: ['backups'], queryFn: async () => (await api.get<{ data: { name: string; size: number; created_at: string }[] }>('/settings/backups')).data.data })
  const create = useMutation({ mutationFn: () => api.post('/settings/backups'), onSuccess: () => qc.invalidateQueries({ queryKey: ['backups'] }) })
  const remove = useMutation({ mutationFn: (name: string) => api.delete(`/settings/backups/${name}`), onSuccess: () => qc.invalidateQueries({ queryKey: ['backups'] }) })

  return (
    <Card className="max-w-2xl overflow-hidden">
      <div className="flex items-center justify-between gap-2 border-b border-slate-100 px-5 py-4">
        <div>
          <h2 className="font-semibold text-slate-900">Database backups</h2>
          <p className="text-sm text-slate-500">To restore, import the file in cPanel → phpMyAdmin. Downloads are recorded in the activity log.</p>
        </div>
        <Button loading={create.isPending} onClick={() => create.mutate()}>
          <HardDriveDownload className="size-4" /> Back up now
        </Button>
      </div>
      {(create.isError || remove.isError) && (
        <div className="p-4">
          <Alert>{errorMessage(create.error ?? remove.error)}</Alert>
        </div>
      )}
      <ul className="divide-y divide-slate-100">
        {data?.map((b) => (
          <li key={b.name} className="flex flex-wrap items-center justify-between gap-2 px-5 py-3 text-sm">
            <div>
              <p className="font-mono text-slate-900">{b.name}</p>
              <p className="text-xs text-slate-500">
                {when(b.created_at)} · {kb(b.size)}
              </p>
            </div>
            <span className="flex gap-1">
              <a href={apiUrl(`/settings/backups/${b.name}`)} className="inline-flex items-center gap-1 rounded-lg px-3 py-2 text-slate-600 hover:bg-slate-100" aria-label={`Download ${b.name}`}>
                <Download className="size-4" /> Download
              </a>
              <Button variant="ghost" aria-label={`Delete ${b.name}`} disabled={remove.isPending} onClick={() => confirm(`Delete ${b.name}?`) && remove.mutate(b.name)}>
                <Trash2 className="size-4" />
              </Button>
            </span>
          </li>
        ))}
        {data?.length === 0 && <li className="px-5 py-4 text-sm text-slate-500">No backups yet.</li>}
      </ul>
    </Card>
  )
}

interface SystemData {
  app: Record<string, string | boolean>
  scheduler_heartbeat: string | null
  last_backup: { name: string; created_at: string } | null
  storage: { documents_bytes: number; backups_bytes: number }
  checks: { label: string; ok: boolean; hint: string }[]
}

function SystemInfo() {
  const { data, refetch, isFetching } = useQuery({ queryKey: ['settings-system'], queryFn: async () => (await api.get<{ data: SystemData }>('/settings/system')).data.data })
  const clear = useMutation({ mutationFn: () => api.post('/settings/system/clear-cache') })
  if (!data) return <Spinner className="text-brand-600" />
  const passed = data.checks.filter((c) => c.ok).length

  return (
    <div className="grid gap-4 lg:grid-cols-[1fr_340px]">
      <Card className="p-5">
        <div className="flex items-center justify-between gap-2">
          <h2 className="font-semibold text-slate-900">Go-live checklist</h2>
          <Badge tone={passed === data.checks.length ? 'green' : 'amber'}>
            {passed} of {data.checks.length} ready
          </Badge>
        </div>
        <ul className="mt-3 space-y-2.5">
          {data.checks.map((c) => (
            <li key={c.label} className="flex gap-2 text-sm">
              {c.ok ? <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-brand-600" /> : <XCircle className="mt-0.5 size-4 shrink-0 text-amber-600" />}
              <span>
                <span className="text-slate-800">{c.label}</span>
                {!c.ok && <span className="block text-xs text-slate-500">{c.hint}</span>}
              </span>
            </li>
          ))}
        </ul>
      </Card>
      <div className="space-y-4">
        <Card className="p-5">
          <h2 className="font-semibold text-slate-900">System</h2>
          <dl className="mt-2 grid grid-cols-[110px_1fr] gap-y-1 text-sm">
            {(['environment', 'url', 'timezone', 'laravel', 'php', 'database', 'mail', 'queue', 'cache'] as const).map((k) => (
              <div key={k} className="contents">
                <dt className="capitalize text-slate-500">{k}</dt>
                <dd className="truncate text-slate-800">{String(data.app[k])}</dd>
              </div>
            ))}
            <dt className="text-slate-500">Scheduler</dt>
            <dd className="text-slate-800">{data.scheduler_heartbeat ? `last ran ${when(data.scheduler_heartbeat)}` : 'never seen'}</dd>
            <dt className="text-slate-500">Last backup</dt>
            <dd className="text-slate-800">{data.last_backup ? when(data.last_backup.created_at) : 'none'}</dd>
            <dt className="text-slate-500">Documents</dt>
            <dd className="text-slate-800">{kb(data.storage.documents_bytes)}</dd>
            <dt className="text-slate-500">Backups</dt>
            <dd className="text-slate-800">{kb(data.storage.backups_bytes)}</dd>
          </dl>
        </Card>
        <Card className="space-y-2 p-5">
          <h2 className="font-semibold text-slate-900">Maintenance</h2>
          <p className="text-sm text-slate-500">Clear caches after uploading a new version to the server.</p>
          {clear.isSuccess && <Alert tone="green">Caches cleared.</Alert>}
          <div className="flex gap-2">
            <Button variant="secondary" loading={clear.isPending} onClick={() => clear.mutate()}>
              Clear caches
            </Button>
            <Button variant="ghost" loading={isFetching} onClick={() => refetch()}>
              <RefreshCw className="size-4" /> Recheck
            </Button>
          </div>
        </Card>
      </div>
    </div>
  )
}
