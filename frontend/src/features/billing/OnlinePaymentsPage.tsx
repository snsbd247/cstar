import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { FileText, RefreshCw } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router'
import { api, errorMessage, validationErrors } from '../../api/client'
import { Button } from '../../components/ui/Button'
import { Alert, Badge, Card, PageHeader } from '../../components/ui/Card'
import { Field, Input, Select } from '../../components/ui/Field'
import { Pager } from '../../components/ui/Pager'
import { Spinner } from '../../components/ui/Spinner'
import { Stat } from '../../components/ui/Stat'
import { useAuth } from '../../contexts/useAuth'
import { receiptPdfUrl, taka } from './api'

interface Row {
  id: number
  tran_id: string
  gateway: 'bkash' | 'sslcommerz' | 'test'
  amount: number
  status: 'initiated' | 'paid' | 'failed' | 'cancelled' | 'review'
  patient: { id: number; name: string; patient_code: string }
  parent: string | null
  instrument: string | null
  gateway_trx: string | null
  receipt: { id: number; receipt_no: string } | null
  error: string | null
  created_at: string
  paid_at: string | null
}

const tone = { paid: 'green', review: 'amber', initiated: 'gray', failed: 'red', cancelled: 'gray' } as const
const label = { paid: 'Paid', review: 'Needs checking', initiated: 'Not finished', failed: 'Failed', cancelled: 'Cancelled' }
const gw = { bkash: 'bKash', sslcommerz: 'SSLCommerz', test: 'Test gateway' }

/** Billing & Payments → Online Payments (Sprint 19): what parents paid from the portal. */
export default function OnlinePaymentsPage() {
  const { can } = useAuth()
  const qc = useQueryClient()
  const [status, setStatus] = useState('')
  const [page, setPage] = useState(1)
  const { data, isLoading } = useQuery({
    queryKey: ['online-payments', status, page],
    queryFn: async () =>
      (await api.get<{ data: Row[]; meta: { current_page: number; last_page: number; total: number }; summary: { paid_count: number; paid_total: number; review: number } }>('/online-payments', {
        params: { status: status || undefined, page },
      })).data,
  })
  const recheck = useMutation({ mutationFn: (id: number) => api.post(`/online-payments/${id}/recheck`), onSuccess: () => qc.invalidateQueries({ queryKey: ['online-payments'] }) })

  return (
    <>
      <PageHeader
        title="Online Payments"
        description="Paid by families from the parent portal. A receipt is made automatically once the gateway confirms the money."
        actions={
          can('settings.manage') && (
            <Link to="/app/settings?tab=online-payment" className="text-sm font-medium text-brand-700 hover:underline">
              Gateway settings →
            </Link>
          )
        }
      />
      {data && (
        <div className="mb-4 grid gap-3 sm:grid-cols-3">
          <Stat label="Paid online this month" value={taka(data.summary.paid_total)} tone="green" />
          <Stat label="Payments this month" value={data.summary.paid_count} />
          <Stat label="Need checking" value={data.summary.review} tone={data.summary.review ? 'amber' : undefined} />
        </div>
      )}
      <Card className="mb-4 p-3">
        <Select value={status} onChange={(e) => (setStatus(e.target.value), setPage(1))} className="sm:w-56" aria-label="Status">
          <option value="">Any status</option>
          {Object.entries(label).map(([k, l]) => (
            <option key={k} value={k}>
              {l}
            </option>
          ))}
        </Select>
      </Card>
      {recheck.isError && <Alert>{errorMessage(recheck.error)}</Alert>}
      <Card className="overflow-hidden">
        {isLoading ? (
          <Spinner className="m-5 text-brand-600" />
        ) : !data?.data.length ? (
          <p className="p-5 text-sm text-slate-500">No online payments yet.</p>
        ) : (
          <ul className="divide-y divide-slate-100">
            {data.data.map((p) => (
              <li key={p.id} className="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm">
                <div>
                  <Link to={`/app/patients/${p.patient.id}?tab=billing`} className="font-medium text-slate-900 hover:text-brand-700">
                    {p.patient.name}
                  </Link>{' '}
                  <span className="text-xs text-slate-500">
                    · {p.parent} · {gw[p.gateway]}
                    {p.instrument && ` (${p.instrument})`}
                  </span>
                  <p className="text-xs text-slate-500">
                    {p.tran_id} · {new Date(p.created_at).toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short' })}
                    {p.gateway_trx && ` · ref ${p.gateway_trx}`}
                  </p>
                  {p.error && p.status !== 'paid' && <p className="text-xs text-red-700">{p.error}</p>}
                </div>
                <span className="flex items-center gap-3">
                  <b>{taka(p.amount)}</b>
                  <Badge tone={tone[p.status]}>{label[p.status]}</Badge>
                  {p.receipt && (
                    <a href={receiptPdfUrl(p.receipt.id)} target="_blank" rel="noreferrer" className="text-slate-500 hover:text-slate-800" aria-label={`Receipt ${p.receipt.receipt_no}`}>
                      <FileText className="size-4" />
                    </a>
                  )}
                  {can('payments.create') && (p.status === 'review' || p.status === 'initiated') && p.gateway !== 'test' && (
                    <Button variant="ghost" className="min-h-8 px-2 text-xs" loading={recheck.isPending && recheck.variables === p.id} onClick={() => recheck.mutate(p.id)}>
                      <RefreshCw className="size-3.5" /> Check again
                    </Button>
                  )}
                </span>
              </li>
            ))}
          </ul>
        )}
        <Pager meta={data?.meta} onPage={setPage} />
      </Card>
    </>
  )
}

interface Settings {
  sslcommerz_enabled: string
  sslcommerz_sandbox: string
  sslcommerz_store_id: string
  sslcommerz_store_password: string
  sslcommerz_store_password_saved: boolean
  bkash_enabled: string
  bkash_sandbox: string
  bkash_app_key: string
  bkash_app_secret: string
  bkash_app_secret_saved: boolean
  bkash_username: string
  bkash_password: string
  bkash_password_saved: boolean
  test_enabled: string
  min_amount: string
  production: boolean
}

/** Settings → Online Payment: SSLCommerz and bKash merchant details, sandbox switches, the test gateway. */
export function OnlinePaymentSettingsTab() {
  const { data } = useQuery({ queryKey: ['online-payment-settings'], queryFn: async () => (await api.get<{ data: Settings }>('/online-payment-settings')).data.data })
  return data ? <SettingsForm initial={data} /> : <Spinner className="text-brand-600" />
}

function SettingsForm({ initial }: { initial: Settings }) {
  const qc = useQueryClient()
  const [v, setV] = useState(initial)
  const [errors, setErrors] = useState<Record<string, string>>({})
  const set = <K extends keyof Settings>(k: K, value: Settings[K]) => setV((prev) => ({ ...prev, [k]: value }))
  const flag = (k: keyof Settings, text: string) => (
    <label className="flex items-center gap-2 text-sm text-slate-700">
      <input type="checkbox" className="size-4 accent-brand-600" checked={v[k] === '1'} onChange={(e) => set(k, (e.target.checked ? '1' : '0') as never)} /> {text}
    </label>
  )
  const save = useMutation({
    mutationFn: async () => (await api.put<{ data: Settings }>('/online-payment-settings', v)).data.data,
    onSuccess: (d) => (setErrors({}), setV(d), qc.setQueryData(['online-payment-settings'], d)),
    onError: (e) => setErrors(validationErrors(e)),
  })
  const secret = (k: keyof Settings, saved: boolean, labelText: string) => (
    <Field label={labelText} htmlFor={`opg_${k}`} hint={saved ? 'Saved — leave empty to keep' : undefined} error={errors[k]}>
      <Input id={`opg_${k}`} type="password" autoComplete="off" value={v[k] as string} placeholder={saved ? '••••••••' : ''} onChange={(e) => set(k, e.target.value.trim() as never)} />
    </Field>
  )

  return (
    <Card className="max-w-3xl space-y-5 p-5">
      <p className="text-sm text-slate-500">
        Parents pay from the portal’s bill page. Money is booked only after the gateway confirms it. Keep <b>Sandbox</b> on until the merchant account is approved — sandbox payments are not real
        money.
      </p>
      {save.isError && !Object.keys(errors).length && <Alert>{errorMessage(save.error)}</Alert>}
      {save.isSuccess && <Alert tone="green">Saved.</Alert>}

      <section className="space-y-3">
        <h2 className="font-semibold text-slate-900">bKash (Tokenized Checkout)</h2>
        {flag('bkash_enabled', 'Parents can pay with bKash')}
        {flag('bkash_sandbox', 'Sandbox (bKash test server)')}
        <div className="grid gap-3 sm:grid-cols-2">
          <Field label="App key" htmlFor="opg_bkash_app_key" error={errors.bkash_app_key}>
            <Input id="opg_bkash_app_key" value={v.bkash_app_key} onChange={(e) => set('bkash_app_key', e.target.value.trim())} />
          </Field>
          {secret('bkash_app_secret', v.bkash_app_secret_saved, 'App secret')}
          <Field label="Username" htmlFor="opg_bkash_username">
            <Input id="opg_bkash_username" value={v.bkash_username} onChange={(e) => set('bkash_username', e.target.value.trim())} />
          </Field>
          {secret('bkash_password', v.bkash_password_saved, 'Password')}
        </div>
        <p className="text-xs text-slate-500">Money lands in the bKash Merchant account in the books.</p>
      </section>

      <section className="space-y-3 border-t border-slate-100 pt-4">
        <h2 className="font-semibold text-slate-900">SSLCommerz (cards, Nagad, Rocket, bank)</h2>
        {flag('sslcommerz_enabled', 'Parents can pay through SSLCommerz')}
        {flag('sslcommerz_sandbox', 'Sandbox (SSLCommerz test server)')}
        <div className="grid gap-3 sm:grid-cols-2">
          <Field label="Store ID" htmlFor="opg_store" error={errors.sslcommerz_store_id}>
            <Input id="opg_store" value={v.sslcommerz_store_id} onChange={(e) => set('sslcommerz_store_id', e.target.value.trim())} />
          </Field>
          {secret('sslcommerz_store_password', v.sslcommerz_store_password_saved, 'Store password')}
        </div>
        <p className="text-xs text-slate-500">
          Booked to “Online Payments in Transit” until SSLCommerz pays out to the bank — match the payout (less its fee) in Bank Reconciliation.
        </p>
      </section>

      <section className="space-y-3 border-t border-slate-100 pt-4">
        {!v.production && flag('test_enabled', 'Test gateway (training copy only — no real money, never on the live site)')}
        <Field label="Smallest amount a parent can pay (৳)" htmlFor="opg_min" error={errors.min_amount}>
          <Input id="opg_min" type="number" className="w-32" value={v.min_amount} onChange={(e) => set('min_amount', e.target.value)} />
        </Field>
      </section>

      <div className="flex justify-end">
        <Button loading={save.isPending} onClick={() => save.mutate()}>
          Save
        </Button>
      </div>
    </Card>
  )
}
