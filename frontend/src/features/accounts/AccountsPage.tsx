import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { useSearchParams } from 'react-router'
import { api } from '../../api/client'
import { Button } from '../../components/ui/Button'
import { Badge, Card, PageHeader } from '../../components/ui/Card'
import { Select } from '../../components/ui/Field'
import { Spinner } from '../../components/ui/Spinner'
import { cn } from '../../utils/cn'
import { taka } from '../billing/api'

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
  const tab = params.get('tab') ?? 'chart'

  return (
    <>
      <PageHeader title="Accounts" description="Every invoice, payment and package session is posted here automatically (double-entry)." />
      <div className="mb-4 flex gap-1 border-b border-slate-200">
        {[
          ['chart', 'Chart of accounts'],
          ['journal', 'Journal'],
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
      {tab === 'journal' ? <Journal /> : <Chart onOpen={(id) => setParams({ tab: 'journal', account: String(id) })} />}
    </>
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
