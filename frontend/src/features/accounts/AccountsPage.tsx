import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Plus } from 'lucide-react'
import { useNavigate, useSearchParams } from 'react-router'
import { api, errorMessage, validationErrors } from '../../api/client'
import { Button } from '../../components/ui/Button'
import { Alert, Badge, Card, PageHeader } from '../../components/ui/Card'
import { Field, Input, Select } from '../../components/ui/Field'
import { Modal } from '../../components/ui/Modal'
import { Spinner } from '../../components/ui/Spinner'
import { cn } from '../../utils/cn'
import { useAuth } from '../../contexts/useAuth'
import { todayISO } from '../../utils/format'
import { taka } from '../billing/api'
import { useAccountSettings, useAccountsMutations, useChart, usePeriods } from './api'

interface AccountRow {
  id: number
  code: string
  name: string
  name_bn: string | null
  type: 'asset' | 'liability' | 'equity' | 'income' | 'expense'
  parent_id: number | null
  is_group: boolean
  is_system: boolean
  balance: number
}

interface JournalEntryRow {
  id: number
  voucher_no: string
  event: string | null
  narration: string | null
  status: 'posted' | 'reversed'
  reversal_of_id: number | null
  date: string
  branch: { id: number; name: string } | null
  prepared_by: string | null
  lines: { account: { id: number; code: string; name: string }; debit: number; credit: number; memo: string | null }[]
}

const eventLabel: Record<string, string> = {
  'invoice.issued': 'Invoice',
  'payment.received': 'Payment',
  'advance.applied': 'Advance applied',
  'refund.paid': 'Refund',
  'package.consumed': 'Package session',
  'package.expired': 'Package expired',
  'allocation.released': 'Payment released',
}

/** Read-only books until Accounts A (vouchers, expenses, reports) — Plan Accounts §৩: billing posts here automatically. */
export default function AccountsPage() {
  const [params, setParams] = useSearchParams()
  const navigate = useNavigate()
  const { can } = useAuth()
  const tab = params.get('tab') ?? 'chart'

  return (
    <>
      <PageHeader
        title="Books"
        description="Chart of accounts, the journal (billing posts here automatically) and month locking."
        actions={
          can('accounts.coa.manage') &&
          tab === 'chart' && (
            <Button variant="secondary" onClick={() => setParams({ tab: 'chart', add: '1' })}>
              <Plus className="size-4" /> Account
            </Button>
          )
        }
      />
      <div className="mb-4 flex gap-1 border-b border-slate-200">
        {[
          ['chart', 'Chart of accounts'],
          ['journal', 'Journal'],
          ['periods', 'Months & settings'],
        ].map(([key, label]) => (
          <button
            key={key}
            onClick={() => setParams({ tab: key }, { replace: true })}
            className={cn('border-b-2 px-3 py-2.5 text-sm font-medium', tab === key ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-800')}
          >
            {label}
          </button>
        ))}
      </div>
      {tab === 'journal' ? <Journal /> : tab === 'periods' ? <Periods /> : <Chart onOpen={(id) => navigate(`/app/accounts/reports?r=ledger&account=${id}`)} />}
      {params.get('add') && <AddAccountModal onClose={() => setParams({ tab: 'chart' })} />}
    </>
  )
}

/** Accounts §১১: close a month once it ends (in order); reopening needs a reason. Plus the A5 approval limit. */
function Periods() {
  const { can } = useAuth()
  const [yearStart, setYearStart] = useState<number | undefined>()
  const { data: periodData } = usePeriods(yearStart)
  const periods = periodData?.data
  const year = periodData?.year
  const { periodAction } = useAccountsMutations()
  const qc = useQueryClient()
  const closeYear = useMutation({
    mutationFn: (id: number) => api.post(`/accounts/fiscal-years/${id}/close`),
    onSuccess: () => ['periods', 'ledger', 'report'].forEach((k) => qc.invalidateQueries({ queryKey: [k] })),
  })

  return (
    <div className="grid gap-4 lg:grid-cols-[1fr_320px]">
      <Card className="p-5">
        <div className="flex flex-wrap items-center justify-between gap-2">
          <h3 className="font-semibold text-slate-900">Months (fiscal year July–June)</h3>
          <Select value={yearStart ?? ''} onChange={(e) => setYearStart(Number(e.target.value) || undefined)} className="w-36" aria-label="Fiscal year">
            <option value="">This year</option>
            {periodData?.years.map((y) => (
              <option key={y.id} value={y.start_year}>
                {y.name}
              </option>
            ))}
          </Select>
        </div>
        <p className="text-sm text-slate-500">A closed month takes no new vouchers or expenses. Billing corrections after closing post in the current month.</p>
        {periodAction.isError && <div className="mt-2"><Alert>{errorMessage(periodAction.error)}</Alert></div>}
        {year && (
          <div className="mt-3 flex flex-wrap items-center justify-between gap-2 rounded-lg bg-slate-50 p-3 text-sm">
            <span>
              Year {year.name}: <Badge tone={year.status === 'closed' ? 'gray' : 'green'}>{year.status}</Badge>
              {year.status === 'closed' && <span className="ml-1 text-xs text-slate-500">profit moved to Retained Earnings</span>}
            </span>
            {year.status === 'open' && can('accounts.period.close') && periods?.every((p) => p.status === 'closed') && (
              <Button
                variant="danger"
                loading={closeYear.isPending}
                onClick={() => confirm(`Close the year ${year.name}? Income and expenses move to Retained Earnings. This cannot be undone.`) && closeYear.mutate(year.id)}
              >
                Close the year
              </Button>
            )}
          </div>
        )}
        {closeYear.isError && <div className="mt-2"><Alert>{errorMessage(closeYear.error)}</Alert></div>}
        <ul className="mt-3 divide-y divide-slate-100">
          {periods?.map((p) => (
            <li key={p.id} className="flex items-center justify-between py-2 text-sm">
              <span className="font-medium text-slate-800">{p.label}</span>
              <span className="flex items-center gap-2">
                <Badge tone={p.status === 'open' ? 'green' : 'gray'}>{p.status}</Badge>
                {can('accounts.period.close') &&
                  (p.status === 'open' ? (
                    p.end_date < todayISO() && (
                      <Button variant="secondary" disabled={periodAction.isPending} onClick={() => confirm(`Close ${p.label}?`) && periodAction.mutate({ id: p.id, action: 'close' })}>
                        Close month
                      </Button>
                    )
                  ) : (
                    <Button
                      variant="ghost"
                      disabled={periodAction.isPending}
                      onClick={() => {
                        const reason = prompt(`Why reopen ${p.label}?`)
                        if (reason && reason.trim().length >= 5) periodAction.mutate({ id: p.id, action: 'reopen', reason })
                      }}
                    >
                      Reopen
                    </Button>
                  ))}
              </span>
            </li>
          ))}
        </ul>
      </Card>
      <ApprovalLimitCard />
    </div>
  )
}

/** Maker-checker limit (Accounts §৪) — also shown under Settings → Accounts. */
export function ApprovalLimitCard() {
  const { can } = useAuth()
  const { data: settings } = useAccountSettings()
  const { saveSettings } = useAccountsMutations()
  const [limit, setLimit] = useState<string | null>(null)

  return (
    <Card className="h-fit space-y-3 p-5">
      <h3 className="font-semibold text-slate-900">Approval limit</h3>
      <p className="text-sm text-slate-500">Vouchers and expenses up to this amount post without a second person.</p>
      <Input type="number" min={0} disabled={!can('settings.manage')} value={limit ?? settings?.approval_limit ?? ''} onChange={(e) => setLimit(e.target.value)} aria-label="Approval limit" />
      {saveSettings.isSuccess && <Alert tone="green">Saved.</Alert>}
      {can('settings.manage') && (
        <Button loading={saveSettings.isPending} disabled={limit === null} onClick={() => saveSettings.mutate({ approval_limit: Number(limit) })}>
          Save
        </Button>
      )}
    </Card>
  )
}

function AddAccountModal({ onClose }: { onClose: () => void }) {
  const { data: chart } = useChart()
  const { saveAccount } = useAccountsMutations()
  const [v, setV] = useState({ code: '', name: '', name_bn: '', parent_id: '' })
  const [error, setError] = useState<string | null>(null)

  const save = async () => {
    setError(null)
    try {
      await saveAccount.mutateAsync({ ...v, parent_id: Number(v.parent_id), name_bn: v.name_bn || null })
      onClose()
    } catch (e) {
      setError(Object.values(validationErrors(e))[0] ?? errorMessage(e))
    }
  }

  return (
    <Modal open title="New account" onClose={onClose}>
      <div className="space-y-4">
        {error && <Alert>{error}</Alert>}
        <Field label="Under (group)" htmlFor="ac_parent">
          <Select id="ac_parent" value={v.parent_id} onChange={(e) => setV({ ...v, parent_id: e.target.value })}>
            <option value="">Choose…</option>
            {chart?.data
              .filter((a) => a.is_group)
              .map((a) => (
                <option key={a.id} value={a.id}>
                  {a.code} {a.name}
                </option>
              ))}
          </Select>
        </Field>
        <div className="grid grid-cols-3 gap-3">
          <Field label="Code" htmlFor="ac_code">
            <Input id="ac_code" inputMode="numeric" value={v.code} onChange={(e) => setV({ ...v, code: e.target.value })} placeholder="5435" />
          </Field>
          <div className="col-span-2">
            <Field label="Name" htmlFor="ac_name">
              <Input id="ac_name" value={v.name} onChange={(e) => setV({ ...v, name: e.target.value })} />
            </Field>
          </div>
        </div>
        <Field label="Name (Bangla)" htmlFor="ac_name_bn">
          <Input id="ac_name_bn" value={v.name_bn} onChange={(e) => setV({ ...v, name_bn: e.target.value })} />
        </Field>
        <div className="flex justify-end gap-2">
          <Button variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button loading={saveAccount.isPending} disabled={!v.code || !v.name || !v.parent_id} onClick={save}>
            Add account
          </Button>
        </div>
      </div>
    </Modal>
  )
}

function Chart({ onOpen }: { onOpen: (id: number) => void }) {
  const { data, isLoading } = useQuery({
    queryKey: ['ledger', 'chart'],
    queryFn: async () => (await api.get<{ data: AccountRow[]; totals: { debit: number; credit: number } }>('/accounts/chart')).data,
  })
  if (isLoading || !data) return <Spinner className="text-brand-600" />

  const depth = (a: AccountRow): number => {
    const parent = data.data.find((p) => p.id === a.parent_id)
    return parent ? depth(parent) + 1 : 0
  }

  return (
    <>
      <Card className="mb-4 flex flex-wrap gap-6 p-4 text-sm">
        <span>
          Total debit <b>{taka(data.totals.debit)}</b>
        </span>
        <span>
          Total credit <b>{taka(data.totals.credit)}</b>
        </span>
        <Badge tone={Math.abs(data.totals.debit - data.totals.credit) < 0.01 ? 'green' : 'red'}>
          {Math.abs(data.totals.debit - data.totals.credit) < 0.01 ? 'Books balance' : 'Out of balance'}
        </Badge>
      </Card>
      <Card className="overflow-x-auto">
        <table className="w-full min-w-[520px] text-sm">
          <thead className="bg-slate-50 text-left text-xs text-slate-500">
            <tr>
              <th className="px-4 py-2.5 font-medium">Account</th>
              <th className="px-4 py-2.5 text-right font-medium">Balance</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {data.data.map((a) => (
              <tr key={a.id} className={a.is_group ? 'bg-slate-50/60' : 'hover:bg-slate-50'}>
                <td className="px-4 py-2" style={{ paddingLeft: 16 + depth(a) * 20 }}>
                  {a.is_group ? (
                    <span className="font-semibold text-slate-800">
                      {a.code} {a.name}
                    </span>
                  ) : (
                    <button onClick={() => onOpen(a.id)} className="text-left text-slate-700 hover:text-brand-700">
                      <span className="text-slate-400">{a.code}</span> {a.name}
                    </button>
                  )}
                </td>
                <td className={cn('px-4 py-2 text-right tabular-nums', a.is_group ? 'font-semibold' : '', a.balance < 0 && 'text-red-600', !a.balance && 'text-slate-300')}>
                  {a.balance ? taka(a.balance) : '—'}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>
    </>
  )
}

function Journal() {
  const [params] = useSearchParams()
  const accountId = params.get('account') ?? undefined
  const [event, setEvent] = useState('')
  const [page, setPage] = useState(1)
  const { data, isLoading } = useQuery({
    queryKey: ['ledger', 'journal', accountId, event, page],
    queryFn: async () =>
      (await api.get<{ data: JournalEntryRow[]; meta: { current_page: number; last_page: number; total: number } }>('/accounts/journal', { params: { account_id: accountId, event: event || undefined, page } })).data,
  })

  return (
    <>
      <Card className="mb-4 p-3">
        <Select value={event} onChange={(e) => (setEvent(e.target.value), setPage(1))} className="sm:w-56" aria-label="Type">
          <option value="">All entries</option>
          {Object.entries(eventLabel).map(([k, l]) => (
            <option key={k} value={k}>
              {l}
            </option>
          ))}
        </Select>
      </Card>
      {isLoading || !data ? (
        <Spinner className="text-brand-600" />
      ) : (
        <div className="space-y-3">
          {data.data.map((e) => (
            <Card key={e.id} className={cn('p-4', e.status === 'reversed' && 'opacity-60')}>
              <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="text-sm font-medium text-slate-900">
                  {e.voucher_no} <span className="font-normal text-slate-500">· {e.date}</span>
                </p>
                <div className="flex gap-2">
                  <Badge tone="blue">{eventLabel[e.event?.replace('.reversed', '') ?? ''] ?? e.event}</Badge>
                  {e.event?.endsWith('.reversed') && <Badge tone="amber">reversal</Badge>}
                  {e.status === 'reversed' && <Badge>reversed</Badge>}
                </div>
              </div>
              <p className="mt-1 text-sm text-slate-600">{e.narration}</p>
              <table className="mt-2 w-full text-xs">
                <tbody>
                  {e.lines.map((l, i) => (
                    <tr key={i}>
                      <td className={cn('py-0.5', l.credit ? 'pl-6' : '')}>
                        <span className="text-slate-400">{l.account.code}</span> {l.account.name}
                      </td>
                      <td className="w-24 text-right tabular-nums">{l.debit ? taka(l.debit) : ''}</td>
                      <td className="w-24 text-right tabular-nums">{l.credit ? taka(l.credit) : ''}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </Card>
          ))}
          {data.meta.last_page > 1 && (
            <div className="flex items-center justify-between text-sm">
              <span className="text-slate-500">
                Page {data.meta.current_page} of {data.meta.last_page}
              </span>
              <div className="flex gap-2">
                <Button variant="secondary" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
                  Previous
                </Button>
                <Button variant="secondary" disabled={page >= data.meta.last_page} onClick={() => setPage((p) => p + 1)}>
                  Next
                </Button>
              </div>
            </div>
          )}
        </div>
      )}
    </>
  )
}
