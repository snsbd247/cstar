import { FileDown } from 'lucide-react'
import { useState } from 'react'
import { useSearchParams } from 'react-router'
import { Button } from '../../components/ui/Button'
import { Badge, Card, PageHeader } from '../../components/ui/Card'
import { Input, Select } from '../../components/ui/Field'
import { Spinner } from '../../components/ui/Spinner'
import { cn } from '../../utils/cn'
import { todayISO } from '../../utils/format'
import { taka } from '../billing/api'
import { reportPdfUrl, useChart, useReport } from './api'

type Row = { account: { id: number; code: string; name: string }; amount: number }
type Section = { rows: Row[]; total: number }

const tabs = [
  ['income-statement', 'Profit & Loss'],
  ['balance-sheet', 'Balance Sheet'],
  ['trial-balance', 'Trial Balance'],
  ['ledger', 'Ledger / Cash book'],
  ['day-book', 'Day Book'],
  ['cash-flow', 'Cash Flow'],
] as const

/** Accounts §১২ financial reports, each printable as PDF. */
export default function ReportsPage() {
  const [params, setParams] = useSearchParams()
  const tab = params.get('r') ?? 'income-statement'
  const monthStart = todayISO().slice(0, 8) + '01'
  const [from, setFrom] = useState(monthStart)
  const [to, setTo] = useState(todayISO())
  const [accountId, setAccountId] = useState<number | ''>(Number(params.get('account')) || '')
  const { data: chart } = useChart()
  const query: Record<string, string | number | undefined> =
    tab === 'balance-sheet' ? { as_of: to } : tab === 'trial-balance' ? { to } : tab === 'day-book' ? { date: to } : tab === 'ledger' ? { from, to, account_id: accountId || undefined } : { from, to }
  const ready = tab !== 'ledger' || !!accountId
  const { data, isLoading } = useReport<Record<string, unknown>>(tab, query, ready)

  return (
    <>
      <PageHeader title="Financial reports" description="Every figure comes straight from the posted journal." />
      <div className="mb-4 flex gap-1 overflow-x-auto border-b border-slate-200">
        {tabs.map(([key, label]) => (
          <button
            key={key}
            onClick={() => setParams({ r: key }, { replace: true })}
            className={cn('whitespace-nowrap border-b-2 px-3 py-2.5 text-sm font-medium', tab === key ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-800')}
          >
            {label}
          </button>
        ))}
      </div>
      <Card className="mb-4 flex flex-col gap-2 p-3 sm:flex-row sm:items-center">
        {tab === 'ledger' && (
          <Select value={accountId} onChange={(e) => setAccountId(Number(e.target.value) || '')} className="sm:w-80" aria-label="Account">
            <option value="">Choose an account…</option>
            {chart?.data
              .filter((a) => !a.is_group)
              .map((a) => (
                <option key={a.id} value={a.id}>
                  {a.code} {a.name}
                </option>
              ))}
          </Select>
        )}
        {['income-statement', 'ledger', 'cash-flow'].includes(tab) && <Input type="date" value={from} onChange={(e) => setFrom(e.target.value)} className="sm:w-44" aria-label="From" />}
        <Input type="date" value={to} max={todayISO()} onChange={(e) => setTo(e.target.value)} className="sm:w-44" aria-label={tab === 'day-book' ? 'Date' : 'To'} />
        {ready && (
          <a href={reportPdfUrl(tab, query)} target="_blank" rel="noreferrer" className="sm:ml-auto">
            <Button variant="secondary">
              <FileDown className="size-4" /> PDF
            </Button>
          </a>
        )}
      </Card>

      {!ready ? (
        <Card className="p-5 text-sm text-slate-500">Choose an account — e.g. "Cash in Hand" for the cash book, or bKash for the MFS book.</Card>
      ) : isLoading || !data ? (
        <Spinner className="text-brand-600" />
      ) : tab === 'income-statement' ? (
        <IncomeStatement data={data as never} />
      ) : tab === 'balance-sheet' ? (
        <BalanceSheet data={data as never} />
      ) : tab === 'trial-balance' ? (
        <TrialBalance data={data as never} />
      ) : tab === 'cash-flow' ? (
        <CashFlow data={data as never} />
      ) : tab === 'ledger' ? (
        <Ledger data={data as never} />
      ) : (
        <DayBook data={data as never} />
      )}
    </>
  )
}

function SectionTable({ title, section, extra }: { title: string; section: Section; extra?: [string, number] }) {
  return (
    <Card className="mb-4 overflow-hidden">
      <p className="bg-slate-50 px-4 py-2 text-sm font-semibold text-slate-700">{title}</p>
      <table className="w-full text-sm">
        <tbody className="divide-y divide-slate-100">
          {section.rows.map((r) => (
            <tr key={r.account.id}>
              <td className="px-4 py-1.5">
                <span className="text-slate-400">{r.account.code}</span> {r.account.name}
              </td>
              <td className={cn('px-4 py-1.5 text-right tabular-nums', r.amount < 0 && 'text-red-600')}>{taka(r.amount)}</td>
            </tr>
          ))}
          {extra && (
            <tr>
              <td className="px-4 py-1.5 italic text-slate-600">{extra[0]}</td>
              <td className="px-4 py-1.5 text-right tabular-nums">{taka(extra[1])}</td>
            </tr>
          )}
          {section.rows.length === 0 && !extra && (
            <tr>
              <td className="px-4 py-2 text-slate-400">Nothing yet</td>
            </tr>
          )}
          <tr className="font-semibold">
            <td className="px-4 py-2">Total {title.toLowerCase()}</td>
            <td className="px-4 py-2 text-right tabular-nums">{taka(section.total)}</td>
          </tr>
        </tbody>
      </table>
    </Card>
  )
}

function IncomeStatement({ data }: { data: { income: Section; expense: Section; net_profit: number } }) {
  return (
    <div className="max-w-3xl">
      <SectionTable title="Income" section={data.income} />
      <SectionTable title="Expenses" section={data.expense} />
      <Card className="flex items-center justify-between p-4">
        <span className="font-semibold text-slate-900">Net {data.net_profit >= 0 ? 'profit' : 'loss'}</span>
        <span className={cn('text-xl font-semibold', data.net_profit >= 0 ? 'text-brand-700' : 'text-red-600')}>{taka(Math.abs(data.net_profit))}</span>
      </Card>
    </div>
  )
}

function BalanceSheet({ data }: { data: { assets: Section; liabilities: Section; equity: Section & { profit_to_date: number }; liabilities_and_equity: number; balanced: boolean } }) {
  return (
    <div className="grid gap-4 lg:grid-cols-2">
      <div>
        <SectionTable title="Assets" section={data.assets} />
      </div>
      <div>
        <SectionTable title="Liabilities" section={data.liabilities} />
        <SectionTable title="Equity" section={data.equity} extra={['Profit / (loss) to date', data.equity.profit_to_date]} />
        <Card className="flex items-center justify-between p-4 text-sm">
          <span>Liabilities + equity {taka(data.liabilities_and_equity)}</span>
          <Badge tone={data.balanced ? 'green' : 'red'}>{data.balanced ? 'Balances with assets' : 'Does not balance'}</Badge>
        </Card>
      </div>
    </div>
  )
}

function TrialBalance({ data }: { data: { rows: { account: { id: number; code: string; name: string }; debit: number; credit: number }[]; total_debit: number; total_credit: number } }) {
  return (
    <Card className="max-w-3xl overflow-x-auto">
      <table className="w-full text-sm">
        <thead className="bg-slate-50 text-left text-xs text-slate-500">
          <tr>
            <th className="px-4 py-2 font-medium">Account</th>
            <th className="px-4 py-2 text-right font-medium">Debit</th>
            <th className="px-4 py-2 text-right font-medium">Credit</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-100">
          {data.rows.map((r) => (
            <tr key={r.account.id}>
              <td className="px-4 py-1.5">
                <span className="text-slate-400">{r.account.code}</span> {r.account.name}
              </td>
              <td className="px-4 py-1.5 text-right tabular-nums">{r.debit ? taka(r.debit) : ''}</td>
              <td className="px-4 py-1.5 text-right tabular-nums">{r.credit ? taka(r.credit) : ''}</td>
            </tr>
          ))}
          <tr className="font-semibold">
            <td className="px-4 py-2">
              Total <Badge tone={Math.abs(data.total_debit - data.total_credit) < 0.01 ? 'green' : 'red'}>{Math.abs(data.total_debit - data.total_credit) < 0.01 ? 'balanced' : 'not balanced'}</Badge>
            </td>
            <td className="px-4 py-2 text-right tabular-nums">{taka(data.total_debit)}</td>
            <td className="px-4 py-2 text-right tabular-nums">{taka(data.total_credit)}</td>
          </tr>
        </tbody>
      </table>
    </Card>
  )
}

function Ledger({ data }: { data: { opening: number; closing: number; total_debit: number; total_credit: number; rows: { date: string; voucher_no: string; narration: string; memo: string | null; debit: number; credit: number; balance: number }[] } }) {
  return (
    <Card className="overflow-x-auto">
      <table className="w-full min-w-[640px] text-sm">
        <thead className="bg-slate-50 text-left text-xs text-slate-500">
          <tr>
            <th className="px-4 py-2 font-medium">Date</th>
            <th className="px-4 py-2 font-medium">Particulars</th>
            <th className="px-4 py-2 text-right font-medium">Debit</th>
            <th className="px-4 py-2 text-right font-medium">Credit</th>
            <th className="px-4 py-2 text-right font-medium">Balance</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-100">
          <tr className="bg-slate-50/60">
            <td className="px-4 py-1.5" />
            <td className="px-4 py-1.5 font-medium">Opening balance</td>
            <td />
            <td />
            <td className="px-4 py-1.5 text-right font-medium tabular-nums">{taka(data.opening)}</td>
          </tr>
          {data.rows.map((r, i) => (
            <tr key={i}>
              <td className="whitespace-nowrap px-4 py-1.5 text-slate-500">{r.date}</td>
              <td className="px-4 py-1.5">
                {r.narration}
                <span className="block text-xs text-slate-400">{r.voucher_no}</span>
              </td>
              <td className="px-4 py-1.5 text-right tabular-nums">{r.debit ? taka(r.debit) : ''}</td>
              <td className="px-4 py-1.5 text-right tabular-nums">{r.credit ? taka(r.credit) : ''}</td>
              <td className={cn('px-4 py-1.5 text-right tabular-nums', r.balance < 0 && 'text-red-600')}>{taka(r.balance)}</td>
            </tr>
          ))}
          <tr className="font-semibold">
            <td />
            <td className="px-4 py-2">Closing balance</td>
            <td className="px-4 py-2 text-right tabular-nums">{taka(data.total_debit)}</td>
            <td className="px-4 py-2 text-right tabular-nums">{taka(data.total_credit)}</td>
            <td className="px-4 py-2 text-right tabular-nums">{taka(data.closing)}</td>
          </tr>
        </tbody>
      </table>
    </Card>
  )
}

function DayBook({ data }: { data: { entries: { voucher_no: string; narration: string; status: string; lines: { account: { code: string; name: string }; debit: number; credit: number }[] }[] } }) {
  if (!data.entries.length) return <Card className="p-5 text-sm text-slate-500">No entries on this day.</Card>
  return (
    <div className="space-y-3">
      {data.entries.map((e) => (
        <Card key={e.voucher_no} className={cn('p-4', e.status === 'reversed' && 'opacity-60')}>
          <p className="text-sm font-medium text-slate-900">
            {e.voucher_no} <span className="font-normal text-slate-600">— {e.narration}</span>
          </p>
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
    </div>
  )
}

function CashFlow({ data }: { data: { opening: number; closing: number; net_change: number; sections: Record<'operating' | 'investing' | 'financing', { rows: { label: string; amount: number }[]; total: number }> } }) {
  const titles = { operating: 'Operating activities', investing: 'Investing (equipment, furniture)', financing: 'Financing (owner, loans)' } as const
  return (
    <div className="max-w-3xl space-y-4">
      <Card className="flex justify-between p-4 text-sm">
        <span>Cash, bank & mobile money at start</span>
        <b className="tabular-nums">{taka(data.opening)}</b>
      </Card>
      {(Object.keys(titles) as (keyof typeof titles)[]).map((k) => (
        <Card key={k} className="overflow-hidden">
          <p className="bg-slate-50 px-4 py-2 text-sm font-semibold text-slate-700">{titles[k]}</p>
          <table className="w-full text-sm">
            <tbody className="divide-y divide-slate-100">
              {data.sections[k].rows.map((r) => (
                <tr key={r.label}>
                  <td className="px-4 py-1.5">{r.label}</td>
                  <td className={cn('px-4 py-1.5 text-right tabular-nums', r.amount < 0 && 'text-red-600')}>{taka(r.amount)}</td>
                </tr>
              ))}
              {data.sections[k].rows.length === 0 && (
                <tr>
                  <td className="px-4 py-2 text-slate-400">None</td>
                </tr>
              )}
              <tr className="font-semibold">
                <td className="px-4 py-2">Net cash</td>
                <td className="px-4 py-2 text-right tabular-nums">{taka(data.sections[k].total)}</td>
              </tr>
            </tbody>
          </table>
        </Card>
      ))}
      <Card className="space-y-1 p-4 text-sm">
        <p className="flex justify-between">
          <span>Net change</span>
          <span className="tabular-nums">{taka(data.net_change)}</span>
        </p>
        <p className="flex justify-between font-semibold">
          <span>Cash, bank & mobile money at end</span>
          <span className="tabular-nums">{taka(data.closing)}</span>
        </p>
      </Card>
    </div>
  )
}
