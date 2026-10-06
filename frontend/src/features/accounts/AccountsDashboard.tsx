import { ArrowRight } from 'lucide-react'
import { Link } from 'react-router'
import { Card, PageHeader } from '../../components/ui/Card'
import { Spinner } from '../../components/ui/Spinner'
import { cn } from '../../utils/cn'
import { taka } from '../billing/api'
import { useAccountsDashboard } from './api'

/** Accounts §১৫ dashboard: money on hand, today and this month, six-month income vs expense, work waiting. */
export default function AccountsDashboard() {
  const { data, isLoading } = useAccountsDashboard()
  if (isLoading || !data) return <Spinner className="text-brand-600" />
  const peak = Math.max(1, ...data.months.flatMap((m) => [m.income, m.expense]))

  return (
    <>
      <PageHeader title="Accounts" description="Cash, bank and the month's result at a glance." />

      {(data.pending_vouchers > 0 || data.pending_closings > 0) && (
        <div className="mb-4 grid gap-3 sm:grid-cols-2">
          {data.pending_vouchers > 0 && (
            <Link to="/app/accounts/vouchers" className="flex items-center justify-between rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
              {data.pending_vouchers} voucher(s) waiting for approval <ArrowRight className="size-4" />
            </Link>
          )}
          {data.pending_closings > 0 && (
            <Link to="/app/cash-closing" className="flex items-center justify-between rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
              {data.pending_closings} cash closing(s) to receive <ArrowRight className="size-4" />
            </Link>
          )}
        </div>
      )}

      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <Card className="p-4">
          <p className="text-sm text-slate-500">This month — income</p>
          <p className="text-2xl font-semibold text-slate-900">{taka(data.month.income)}</p>
        </Card>
        <Card className="p-4">
          <p className="text-sm text-slate-500">This month — expenses</p>
          <p className="text-2xl font-semibold text-slate-900">{taka(data.month.expense)}</p>
        </Card>
        <Card className="p-4">
          <p className="text-sm text-slate-500">This month — {data.month.profit >= 0 ? 'profit' : 'loss'}</p>
          <p className={cn('text-2xl font-semibold', data.month.profit >= 0 ? 'text-brand-700' : 'text-red-600')}>{taka(Math.abs(data.month.profit))}</p>
        </Card>
        <Card className="p-4">
          <p className="text-sm text-slate-500">Owed by families</p>
          <p className="text-2xl font-semibold text-red-600">{taka(data.receivable)}</p>
        </Card>
      </div>

      <div className="mt-4 grid gap-4 lg:grid-cols-2">
        <Card className="p-5">
          <h3 className="mb-3 font-semibold text-slate-900">Cash, bank & mobile money</h3>
          <ul className="divide-y divide-slate-100 text-sm">
            {data.money.map((m) => (
              <li key={m.account.id}>
                <Link to={`/app/accounts/reports?r=ledger&account=${m.account.id}`} className="flex justify-between py-2 hover:text-brand-700">
                  <span>{m.account.name}</span>
                  <span className={cn('font-medium tabular-nums', m.balance < 0 && 'text-red-600')}>{taka(m.balance)}</span>
                </Link>
              </li>
            ))}
          </ul>
          <p className="mt-2 text-xs text-slate-500">
            Today: income {taka(data.today.income)} · expenses {taka(data.today.expense)}
          </p>
        </Card>

        <Card className="p-5">
          <h3 className="mb-3 font-semibold text-slate-900">Income vs expenses — last 6 months</h3>
          <div className="flex h-44 items-end gap-3" role="img" aria-label="Monthly income and expenses">
            {data.months.map((m) => (
              <div key={m.month} className="flex flex-1 flex-col items-center gap-1">
                <div className="flex h-36 w-full items-end justify-center gap-1">
                  <div className="w-1/3 rounded-t bg-brand-500" style={{ height: `${(m.income / peak) * 100}%` }} title={`Income ${taka(m.income)}`} />
                  <div className="w-1/3 rounded-t bg-red-400" style={{ height: `${(m.expense / peak) * 100}%` }} title={`Expenses ${taka(m.expense)}`} />
                </div>
                <span className="text-[11px] text-slate-500">{m.month.slice(0, 3)}</span>
              </div>
            ))}
          </div>
          <p className="mt-2 flex gap-4 text-xs text-slate-500">
            <span className="flex items-center gap-1">
              <span className="size-2 rounded-sm bg-brand-500" /> Income
            </span>
            <span className="flex items-center gap-1">
              <span className="size-2 rounded-sm bg-red-400" /> Expenses
            </span>
          </p>
        </Card>
      </div>
    </>
  )
}
