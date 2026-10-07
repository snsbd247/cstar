import { CheckCircle2 } from 'lucide-react'
import { useState } from 'react'
import { errorMessage, validationErrors } from '../../api/client'
import { Button } from '../../components/ui/Button'
import { Alert, Badge, Card, PageHeader } from '../../components/ui/Card'
import { Field, Input, Select } from '../../components/ui/Field'
import { Spinner } from '../../components/ui/Spinner'
import { useAuth } from '../../contexts/useAuth'
import { cn } from '../../utils/cn'
import { todayISO } from '../../utils/format'
import { methodLabel, taka, type PaymentMethod } from '../billing/api'
import { useAccountsMutations, useCashClosings, useCashExpected } from './api'

/** Accounts §৮ "Close My Cash": system total by method → count the notes → explain any difference → hand over. */
export default function CashClosingPage() {
  const { user, can } = useAuth()
  const branches = user?.branches ?? []
  const [branchId, setBranchId] = useState(branches[0]?.id ?? 0)
  const { data: closings } = useCashClosings({})
  const { receiveCash } = useAccountsMutations()

  return (
    <>
      <PageHeader title="Cash closing" description="At the end of the day, count your cash and hand it over." />
      <div className="grid gap-5 lg:grid-cols-[420px_1fr]">
        <div className="space-y-3">
          {branches.length > 1 && (
            <Select value={branchId} onChange={(e) => setBranchId(Number(e.target.value))} aria-label="Branch">
              {branches.map((b) => (
                <option key={b.id} value={b.id}>
                  {b.name}
                </option>
              ))}
            </Select>
          )}
          <CloseMyCash key={branchId} branchId={branchId} />
        </div>

        <Card className="p-5">
          <h3 className="font-semibold text-slate-900">{can('accounts.view') ? 'Closings in the branch' : 'My closings'}</h3>
          {receiveCash.isError && <div className="mt-2"><Alert>{errorMessage(receiveCash.error)}</Alert></div>}
          <ul className="mt-3 divide-y divide-slate-100">
            {closings?.length === 0 && <li className="text-sm text-slate-500">None yet.</li>}
            {closings?.map((c) => (
              <li key={c.id} className="flex flex-wrap items-center justify-between gap-2 py-3 text-sm">
                <div>
                  <p className="font-medium text-slate-900">
                    {c.user.name} <span className="font-normal text-slate-500">· {c.date}</span>
                  </p>
                  <p className="text-xs text-slate-500">
                    Expected {taka(c.expected_cash)} · counted {taka(c.counted_cash)}
                    {c.difference !== 0 && (
                      <span className={c.difference < 0 ? 'text-red-600' : 'text-amber-700'}>
                        {' '}
                        · {c.difference < 0 ? 'short' : 'over'} {taka(Math.abs(c.difference))}
                      </span>
                    )}
                    {c.received_by && ` · received by ${c.received_by.name}`}
                  </p>
                  {c.reason && <p className="text-xs text-slate-600">{c.reason}</p>}
                </div>
                {c.can_receive ? (
                  <Button loading={receiveCash.isPending} onClick={() => confirm(`Receive ${taka(c.counted_cash)} from ${c.user.name}?`) && receiveCash.mutate(c.id)}>
                    Receive cash
                  </Button>
                ) : (
                  <Badge tone={c.status === 'received' ? 'green' : 'amber'}>{c.status === 'received' ? 'received' : 'waiting'}</Badge>
                )}
              </li>
            ))}
          </ul>
        </Card>
      </div>
    </>
  )
}

function CloseMyCash({ branchId }: { branchId: number }) {
  const [date, setDate] = useState(todayISO())
  const { data: exp, isLoading } = useCashExpected(branchId, date)
  const { closeCash } = useAccountsMutations()
  const [notes, setNotes] = useState<Record<string, string>>({})
  const [reason, setReason] = useState('')
  const [error, setError] = useState<string | null>(null)
  const counted = Object.entries(notes).reduce((s, [n, c]) => s + Number(n) * (Number(c) || 0), 0)
  const diff = exp ? Math.round((counted - exp.expected_cash) * 100) / 100 : 0

  if (isLoading || !exp) return <Spinner className="text-brand-600" />

  const submit = async () => {
    setError(null)
    try {
      await closeCash.mutateAsync({ branch_id: branchId, date, denominations: Object.fromEntries(Object.entries(notes).filter(([, c]) => Number(c) > 0)), counted_cash: counted, reason: reason || null })
    } catch (e) {
      setError(Object.values(validationErrors(e))[0] ?? errorMessage(e))
    }
  }

  return (
    <Card className="space-y-4 p-5">
      <div className="flex items-center justify-between gap-2">
        <h3 className="font-semibold text-slate-900">Close my cash</h3>
        <Input type="date" max={todayISO()} value={date} onChange={(e) => setDate(e.target.value)} className="w-40" aria-label="Date" />
      </div>
      <div className="grid grid-cols-3 gap-2 text-center text-sm">
        {(Object.keys(methodLabel) as PaymentMethod[]).filter((m) => m !== 'online').map((m) => (
          <div key={m} className="rounded-lg bg-slate-50 p-2">
            <p className="text-xs text-slate-500">{methodLabel[m]}</p>
            <p className="font-semibold text-slate-900">{taka(exp.by_method[m] ?? 0)}</p>
          </div>
        ))}
        <div className="rounded-lg bg-slate-50 p-2">
          <p className="text-xs text-slate-500">Cash expenses</p>
          <p className="font-semibold text-slate-900">−{taka(exp.cash_expenses)}</p>
        </div>
      </div>
      <p className="rounded-lg bg-brand-50 p-3 text-sm text-brand-900">
        Cash that should be in your drawer: <b>{taka(exp.expected_cash)}</b> <span className="text-xs">({exp.payments} payments)</span>
      </p>

      {exp.already_closed ? (
        <Alert tone="green">
          <span className="inline-flex items-center gap-1.5">
            <CheckCircle2 className="size-4" /> You have closed your cash for this day.
          </span>
        </Alert>
      ) : (
        <>
          <div>
            <p className="mb-2 text-sm font-medium text-slate-700">Count the notes</p>
            <div className="grid grid-cols-2 gap-2">
              {exp.notes.map((n) => (
                <label key={n} className="flex items-center gap-2 text-sm">
                  <span className="w-14 text-right text-slate-600">৳{n} ×</span>
                  <Input type="number" min={0} inputMode="numeric" value={notes[n] ?? ''} onChange={(e) => setNotes({ ...notes, [n]: e.target.value })} aria-label={`${n} taka notes`} />
                </label>
              ))}
            </div>
          </div>
          <div className="flex items-center justify-between rounded-lg border border-slate-200 p-3 text-sm">
            <span>
              Counted <b>{taka(counted)}</b>
            </span>
            <span className={cn('font-semibold', diff === 0 ? 'text-brand-700' : diff < 0 ? 'text-red-600' : 'text-amber-700')}>
              {diff === 0 ? 'Matches' : `${diff < 0 ? 'Short' : 'Over'} ${taka(Math.abs(diff))}`}
            </span>
          </div>
          {diff !== 0 && (
            <Field label="Why is there a difference?" htmlFor="cc_reason">
              <Input id="cc_reason" value={reason} onChange={(e) => setReason(e.target.value)} />
            </Field>
          )}
          {error && <Alert>{error}</Alert>}
          <Button className="w-full" loading={closeCash.isPending} disabled={diff !== 0 && !reason} onClick={submit}>
            Close cash for {date}
          </Button>
        </>
      )}
    </Card>
  )
}
