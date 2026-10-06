import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Download, FileUp } from 'lucide-react'
import { useMemo, useState } from 'react'
import { Link } from 'react-router'
import { api, apiUrl, errorMessage, validationErrors } from '../../api/client'
import { Button } from '../../components/ui/Button'
import { Alert, Badge, Card } from '../../components/ui/Card'
import { Field, Input } from '../../components/ui/Field'
import { Spinner } from '../../components/ui/Spinner'
import { cn } from '../../utils/cn'
import { todayISO } from '../../utils/format'
import { taka } from '../billing/api'

interface OpeningAccount {
  id: number
  code: string
  name: string
  type: 'asset' | 'liability' | 'equity'
  subtype: string | null
  amount: number | null
}

interface OpeningData {
  entry: { voucher_no: string; date: string } | null
  accounts: OpeningAccount[]
}

/** Settings → Go-live: opening balances (decision A9) and the children already at the center. */
export function GoLiveTab() {
  return (
    <div className="space-y-4">
      <Card className="max-w-3xl p-5 text-sm text-slate-600">
        <h2 className="font-semibold text-slate-900">Starting with real data</h2>
        <ol className="mt-2 list-decimal space-y-1 pl-5">
          <li>
            Fill in <Link className="text-brand-700 hover:underline" to="/app/settings?tab=center">Center Information</Link>, each{' '}
            <Link className="text-brand-700 hover:underline" to="/app/branches">branch</Link>, services and prices (
            <Link className="text-brand-700 hover:underline" to="/app/therapy/services">Therapy Services</Link>,{' '}
            <Link className="text-brand-700 hover:underline" to="/app/packages">Packages</Link>), staff logins and employees.
          </li>
          <li>Enter the opening balances below — what was in the cash box, bank and bKash on the go-live date.</li>
          <li>Import the children already coming to the center (with any unpaid dues), then enroll them in their programmes.</li>
          <li>
            Add equipment under <Link className="text-brand-700 hover:underline" to="/app/accounts/assets">Fixed Assets</Link> and money owed to suppliers under{' '}
            <Link className="text-brand-700 hover:underline" to="/app/accounts/vendors">Vendors</Link> (opening balance).
          </li>
          <li>
            Check <Link className="text-brand-700 hover:underline" to="/app/settings?tab=system">System → Go-live checklist</Link> until everything is ticked.
          </li>
        </ol>
      </Card>
      <OpeningBalances />
      <ImportChildren />
    </div>
  )
}

function OpeningBalances() {
  const qc = useQueryClient()
  const { data, isLoading } = useQuery({ queryKey: ['opening-balances'], queryFn: async () => (await api.get<{ data: OpeningData }>('/go-live/opening-balances')).data.data })
  if (isLoading || !data) return <Spinner className="text-brand-600" />

  return <OpeningForm key={data.entry?.voucher_no ?? 'new'} data={data} onSaved={(d) => qc.setQueryData(['opening-balances'], d)} />
}

function OpeningForm({ data, onSaved }: { data: OpeningData; onSaved: (d: OpeningData) => void }) {
  const [date, setDate] = useState(data.entry?.date ?? todayISO())
  const [amounts, setAmounts] = useState<Record<number, string>>(() => Object.fromEntries(data.accounts.filter((a) => a.amount).map((a) => [a.id, String(a.amount)])))
  const [errors, setErrors] = useState<Record<string, string>>({})
  const save = useMutation({
    mutationFn: async () => (await api.post<{ data: OpeningData }>('/go-live/opening-balances', { date, amounts })).data.data,
    onSuccess: (d) => (setErrors({}), onSaved(d)),
    onError: (e) => setErrors(validationErrors(e)),
  })
  const totals = useMemo(() => {
    const sum = (type: OpeningAccount['type']) => data.accounts.filter((a) => a.type === type).reduce((s, a) => s + (Number(amounts[a.id]) || 0), 0)
    return { assets: sum('asset'), liabilities: sum('liability'), equity: sum('equity') }
  }, [amounts, data.accounts])
  const balancing = totals.assets - totals.liabilities - totals.equity
  const groups = [
    ['asset', 'What the center has (assets)'],
    ['liability', 'What the center owes (liabilities)'],
    ['equity', 'Owner’s capital'],
  ] as const

  return (
    <Card className="max-w-3xl overflow-hidden">
      <div className="flex flex-wrap items-end justify-between gap-3 border-b border-slate-100 px-5 py-4">
        <div>
          <h2 className="font-semibold text-slate-900">Opening balances</h2>
          <p className="text-sm text-slate-500">
            Balances on the go-live date. The difference is the owner’s equity at go-live (Retained Earnings). Entering again replaces the earlier entry.
          </p>
          {data.entry && (
            <p className="mt-1 text-xs text-brand-700">
              Posted as {data.entry.voucher_no} on {data.entry.date}
            </p>
          )}
        </div>
        <Field label="Go-live date" htmlFor="ob_date" error={errors.date}>
          <Input id="ob_date" type="date" max={todayISO()} value={date} onChange={(e) => setDate(e.target.value)} className="w-44" />
        </Field>
      </div>
      {save.isError && !Object.keys(errors).length && (
        <div className="p-4">
          <Alert>{errorMessage(save.error)}</Alert>
        </div>
      )}
      {errors.amounts && (
        <div className="p-4">
          <Alert>{errors.amounts}</Alert>
        </div>
      )}
      {groups.map(([type, title]) => (
        <div key={type}>
          <p className="bg-slate-50 px-5 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500">{title}</p>
          <ul className="divide-y divide-slate-100">
            {data.accounts
              .filter((a) => a.type === type)
              .map((a) => (
                <li key={a.id} className="flex items-center justify-between gap-3 px-5 py-2 text-sm">
                  <label htmlFor={`ob_${a.id}`} className="text-slate-700">
                    {a.name} <span className="text-xs text-slate-400">{a.code}</span>
                  </label>
                  <Input id={`ob_${a.id}`} type="number" min={0} step="0.01" className="w-40 text-right" value={amounts[a.id] ?? ''} placeholder="0" onChange={(e) => setAmounts({ ...amounts, [a.id]: e.target.value })} />
                </li>
              ))}
          </ul>
        </div>
      ))}
      <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-5 py-3 text-sm">
        <span className="text-slate-600">
          Assets {taka(totals.assets)} − owed {taka(totals.liabilities)} − capital {taka(totals.equity)} ={' '}
          <b className={cn(balancing < 0 && 'text-red-700')}>{taka(balancing)}</b> equity at go-live
        </span>
        <span className="flex items-center gap-3">
          {save.isSuccess && <span className="text-brand-700">Posted.</span>}
          <Button loading={save.isPending} onClick={() => save.mutate()}>
            {data.entry ? 'Replace opening balances' : 'Post opening balances'}
          </Button>
        </span>
      </div>
    </Card>
  )
}

interface ImportRow {
  row: number
  name: string
  status: 'ok' | 'imported' | 'duplicate' | 'error'
  messages: string[]
}

const tone = { ok: 'green', imported: 'green', duplicate: 'amber', error: 'red' } as const

function ImportChildren() {
  const [file, setFile] = useState<File | null>(null)
  const [results, setResults] = useState<ImportRow[] | null>(null)
  const [imported, setImported] = useState(false)
  const send = useMutation({
    mutationFn: async (dryRun: boolean) => {
      const body = new FormData()
      body.append('file', file as File)
      body.append('dry_run', dryRun ? '1' : '0')
      return { dryRun, rows: (await api.post<{ data: ImportRow[] }>('/go-live/import-children', body)).data.data }
    },
    onSuccess: ({ dryRun, rows }) => (setResults(rows), setImported(!dryRun)),
  })
  const count = (s: ImportRow['status']) => results?.filter((r) => r.status === s).length ?? 0

  return (
    <Card className="max-w-3xl space-y-4 p-5">
      <div>
        <h2 className="font-semibold text-slate-900">Import the children already at the center</h2>
        <p className="text-sm text-slate-500">
          Fill the template in Excel (one child per row), save as <b>CSV UTF-8</b>, then check it here before importing. Each child gets a new patient ID; a guardian whose mobile is already
          registered (a sibling’s parent) is linked, not duplicated. “opening_due” becomes a “Previous dues” invoice. Treatment consent is recorded as given on paper.
        </p>
      </div>
      <div className="flex flex-wrap items-center gap-2">
        <a href={apiUrl('/go-live/children-template')} className="inline-flex min-h-10 items-center gap-2 rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
          <Download className="size-4" /> Template (CSV)
        </a>
        <label className="inline-flex min-h-10 cursor-pointer items-center gap-2 rounded-lg border border-dashed border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">
          <FileUp className="size-4" /> {file ? file.name : 'Choose filled CSV…'}
          <input type="file" accept=".csv,text/csv" className="hidden" onChange={(e) => (setFile(e.target.files?.[0] ?? null), setResults(null), setImported(false))} />
        </label>
        <Button variant="secondary" disabled={!file} loading={send.isPending && send.variables} onClick={() => send.mutate(true)}>
          Check file
        </Button>
        <Button disabled={!file || !results || imported || count('ok') === 0} loading={send.isPending && send.variables === false} onClick={() => confirm(`Import ${count('ok')} children?`) && send.mutate(false)}>
          Import {results && !imported ? count('ok') : ''} children
        </Button>
      </div>
      {send.isError && <Alert>{Object.values(validationErrors(send.error))[0] ?? errorMessage(send.error)}</Alert>}
      {results && (
        <>
          <p className="text-sm text-slate-600">
            {imported ? (
              <>
                <b className="text-brand-700">{count('imported')} imported.</b>{' '}
              </>
            ) : (
              <>
                <b>{count('ok')}</b> ready to import.{' '}
              </>
            )}
            {count('duplicate') > 0 && `${count('duplicate')} already registered (skipped). `}
            {count('error') > 0 && <span className="text-red-700">{count('error')} with problems — fix them in the file and check again.</span>}
          </p>
          <div className="max-h-96 overflow-y-auto rounded-lg border border-slate-100">
            <table className="w-full text-sm">
              <thead className="sticky top-0 bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                  <th className="px-3 py-2 font-medium">Row</th>
                  <th className="px-3 py-2 font-medium">Child</th>
                  <th className="px-3 py-2 font-medium">Result</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {results.map((r) => (
                  <tr key={r.row}>
                    <td className="px-3 py-1.5 text-slate-500">{r.row}</td>
                    <td className="px-3 py-1.5 text-slate-800">{r.name || '—'}</td>
                    <td className="px-3 py-1.5">
                      <Badge tone={tone[r.status]}>{r.status}</Badge> <span className="text-xs text-slate-600">{r.messages.join(' · ')}</span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </>
      )}
    </Card>
  )
}
