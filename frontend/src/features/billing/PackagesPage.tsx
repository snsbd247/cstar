import { useQuery } from '@tanstack/react-query'
import { Pencil, Plus } from 'lucide-react'
import { useState } from 'react'
import { Link, useSearchParams } from 'react-router'
import { api, errorMessage, validationErrors } from '../../api/client'
import { Button } from '../../components/ui/Button'
import { Alert, Badge, Card, PageHeader } from '../../components/ui/Card'
import { Field, Input, Select } from '../../components/ui/Field'
import { Modal } from '../../components/ui/Modal'
import { Spinner } from '../../components/ui/Spinner'
import { useAuth } from '../../contexts/useAuth'
import { cn } from '../../utils/cn'
import { taka, useBillingMutations, useBillingSettings, usePackages, usePatientPackages, type PackageRow } from './api'

export default function PackagesPage() {
  const [params, setParams] = useSearchParams()
  const { can } = useAuth()
  const tab = params.get('tab') ?? 'packages'
  const tabs = [['packages', 'Packages'], ['sold', 'Children\'s packages'], ...(can('settings.manage') ? [['settings', 'Billing settings']] : [])]

  return (
    <>
      <PageHeader title="Packages" description="Package money counts as income only as sessions happen (unearned until then)." />
      <div className="mb-4 flex gap-1 overflow-x-auto border-b border-slate-200">
        {tabs.map(([key, label]) => (
          <button
            key={key}
            onClick={() => setParams({ tab: key }, { replace: true })}
            className={cn('whitespace-nowrap border-b-2 px-3 py-2.5 text-sm font-medium', tab === key ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-800')}
          >
            {label}
          </button>
        ))}
      </div>
      {tab === 'sold' ? <SoldPackages /> : tab === 'settings' ? <BillingSettingsForm /> : <PackageList />}
    </>
  )
}

function PackageList() {
  const { can } = useAuth()
  const { data, isLoading } = usePackages(true)
  const [editing, setEditing] = useState<PackageRow | 'new' | null>(null)

  return (
    <>
      {can('packages.manage') && (
        <div className="mb-3 flex justify-end">
          <Button onClick={() => setEditing('new')}>
            <Plus className="size-4" /> New package
          </Button>
        </div>
      )}
      {isLoading ? (
        <Spinner className="text-brand-600" />
      ) : !data?.length ? (
        <Card className="p-5 text-sm text-slate-500">No packages yet.</Card>
      ) : (
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
          {data.map((p) => (
            <Card key={p.id} className={cn('p-4', !p.is_active && 'opacity-60')}>
              <div className="flex items-start justify-between gap-2">
                <div>
                  <p className="font-semibold text-slate-900">{p.name}</p>
                  {p.name_bn && <p className="font-bn text-sm text-slate-500">{p.name_bn}</p>}
                </div>
                {can('packages.manage') && (
                  <button aria-label={`Edit ${p.name}`} onClick={() => setEditing(p)} className="text-slate-400 hover:text-slate-700">
                    <Pencil className="size-4" />
                  </button>
                )}
              </div>
              <p className="mt-2 text-2xl font-semibold text-brand-700">{taka(p.price)}</p>
              <p className="text-sm text-slate-500">
                {p.sessions_count} sessions · {taka(p.per_session)}/session · valid {p.validity_days} days
              </p>
              <div className="mt-2 flex gap-2">
                <Badge tone="blue">{p.service.name}</Badge>
                {!p.is_active && <Badge>inactive</Badge>}
              </div>
            </Card>
          ))}
        </div>
      )}
      {editing && <PackageModal pkg={editing === 'new' ? undefined : editing} onClose={() => setEditing(null)} />}
    </>
  )
}

function PackageModal({ pkg, onClose }: { pkg?: PackageRow; onClose: () => void }) {
  const { savePackage } = useBillingMutations()
  const { data: services } = useQuery({
    queryKey: ['billable-services'],
    queryFn: async () => (await api.get<{ data: { id: number; name: string; category: string; default_price: string | null }[] }>('/lookups/bookable-services')).data.data,
  })
  const [v, setV] = useState({
    name: pkg?.name ?? '',
    name_bn: pkg?.name_bn ?? '',
    service_id: pkg?.service_id ?? 0,
    sessions_count: pkg?.sessions_count ?? 8,
    validity_days: pkg?.validity_days ?? 45,
    price: pkg?.price ?? 0,
    is_active: pkg?.is_active ?? true,
  })
  const [error, setError] = useState<string | null>(null)

  const save = async () => {
    setError(null)
    try {
      await savePackage.mutateAsync({ id: pkg?.id, ...v, name_bn: v.name_bn || null })
      onClose()
    } catch (e) {
      setError(Object.values(validationErrors(e))[0] ?? errorMessage(e))
    }
  }

  return (
    <Modal open title={pkg ? 'Edit package' : 'New package'} onClose={onClose}>
      <div className="space-y-4">
        {error && <Alert>{error}</Alert>}
        {pkg && <p className="text-xs text-slate-500">A new price applies to future sales only — packages already sold keep their price.</p>}
        <Field label="Name" htmlFor="pk_name">
          <Input id="pk_name" value={v.name} onChange={(e) => setV({ ...v, name: e.target.value })} placeholder="Speech Therapy — 8 sessions" />
        </Field>
        <Field label="Name (Bangla)" htmlFor="pk_name_bn">
          <Input id="pk_name_bn" value={v.name_bn} onChange={(e) => setV({ ...v, name_bn: e.target.value })} />
        </Field>
        <Field label="Therapy service" htmlFor="pk_service">
          <Select id="pk_service" value={v.service_id} onChange={(e) => setV({ ...v, service_id: Number(e.target.value) })}>
            <option value={0}>Choose…</option>
            {services
              ?.filter((s) => s.category === 'therapy')
              .map((s) => (
                <option key={s.id} value={s.id}>
                  {s.name}
                  {s.default_price ? ` (per session ${taka(s.default_price)})` : ''}
                </option>
              ))}
          </Select>
        </Field>
        <div className="grid grid-cols-3 gap-3">
          <Field label="Sessions" htmlFor="pk_sessions">
            <Input id="pk_sessions" type="number" min={1} value={v.sessions_count} onChange={(e) => setV({ ...v, sessions_count: Number(e.target.value) })} />
          </Field>
          <Field label="Valid (days)" htmlFor="pk_days">
            <Input id="pk_days" type="number" min={1} value={v.validity_days} onChange={(e) => setV({ ...v, validity_days: Number(e.target.value) })} />
          </Field>
          <Field label="Price (৳)" htmlFor="pk_price">
            <Input id="pk_price" type="number" min={0} value={v.price} onChange={(e) => setV({ ...v, price: Number(e.target.value) })} />
          </Field>
        </div>
        <label className="flex items-center gap-2 text-sm text-slate-700">
          <input type="checkbox" className="size-4 accent-brand-600" checked={v.is_active} onChange={(e) => setV({ ...v, is_active: e.target.checked })} /> Offered for sale
        </label>
        <div className="flex justify-end gap-2">
          <Button variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button loading={savePackage.isPending} disabled={!v.name || !v.service_id} onClick={save}>
            Save
          </Button>
        </div>
      </div>
    </Modal>
  )
}

function SoldPackages() {
  const [status, setStatus] = useState('active')
  const { data, isLoading } = usePatientPackages(status || undefined)

  return (
    <>
      <Card className="mb-4 p-3">
        <Select value={status} onChange={(e) => setStatus(e.target.value)} className="sm:w-56" aria-label="Status">
          <option value="active">Active</option>
          <option value="exhausted">Used up</option>
          <option value="expired">Expired</option>
          <option value="">All</option>
        </Select>
      </Card>
      {isLoading ? (
        <Spinner className="text-brand-600" />
      ) : !data?.length ? (
        <Card className="p-5 text-sm text-slate-500">None.</Card>
      ) : (
        <Card className="divide-y divide-slate-100">
          {data.map((p) => (
            <Link key={p.id} to={`/app/patients/${p.patient?.id}?tab=billing`} className="flex flex-wrap items-center justify-between gap-2 px-4 py-3 hover:bg-slate-50">
              <div>
                <p className="font-medium text-slate-900">{p.patient?.name}</p>
                <p className="text-xs text-slate-500">
                  {p.name} · expires {p.expiry_date}
                </p>
              </div>
              <div className="flex items-center gap-2 text-sm">
                <span>
                  <b>{p.remaining}</b> of {p.total_sessions} left
                </span>
                {p.renewal_due && <Badge tone="amber">renewal due</Badge>}
                {p.status !== 'active' && <Badge>{p.status}</Badge>}
              </div>
            </Link>
          ))}
        </Card>
      )}
    </>
  )
}

function BillingSettingsForm() {
  const { data } = useBillingSettings()
  const { saveSettings } = useBillingMutations()
  if (!data) return <Spinner className="text-brand-600" />
  return <SettingsBody key={JSON.stringify(data)} data={data} save={saveSettings} />
}

function SettingsBody({ data, save }: { data: Record<string, string>; save: ReturnType<typeof useBillingMutations>['saveSettings'] }) {
  const [v, setV] = useState(data)
  const set = (k: string, val: string) => setV((x) => ({ ...x, [k]: val }))

  return (
    <Card className="max-w-xl space-y-4 p-5">
      {save.isSuccess && <Alert tone="green">Saved.</Alert>}
      {save.isError && <Alert>{errorMessage(save.error)}</Alert>}
      <div className="grid gap-3 sm:grid-cols-2">
        <Field label="Admission fee (৳)" htmlFor="bs_adm">
          <Input id="bs_adm" type="number" min={0} value={v.admission_fee} onChange={(e) => set('admission_fee', e.target.value)} />
        </Field>
        <Field label="Training fee / month (৳)" htmlFor="bs_tr" hint="Used when an enrollment has no fee of its own">
          <Input id="bs_tr" type="number" min={0} value={v.training_monthly_fee} onChange={(e) => set('training_monthly_fee', e.target.value)} />
        </Field>
        <Field label="Invoice due after (days)" htmlFor="bs_due">
          <Input id="bs_due" type="number" min={0} value={v.invoice_due_days} onChange={(e) => set('invoice_due_days', e.target.value)} />
        </Field>
        <Field label="Receptionist discount limit (%)" htmlFor="bs_disc" hint="Larger discounts need a branch admin or accountant">
          <Input id="bs_disc" type="number" min={0} max={100} value={v.receptionist_discount_limit_percent} onChange={(e) => set('receptionist_discount_limit_percent', e.target.value)} />
        </Field>
      </div>
      <label className="flex items-center gap-2 text-sm text-slate-700">
        <input type="checkbox" className="size-4 accent-brand-600" checked={v.no_show_deducts_package === '1'} onChange={(e) => set('no_show_deducts_package', e.target.checked ? '1' : '0')} />
        A no-show uses a package session
      </label>
      <label className="flex items-center gap-2 text-sm text-slate-700">
        <input type="checkbox" className="size-4 accent-brand-600" checked={v.late_cancel_deducts_package === '1'} onChange={(e) => set('late_cancel_deducts_package', e.target.checked ? '1' : '0')} />
        Cancelling within 24 hours uses a package session
      </label>
      <div className="flex justify-end">
        <Button
          loading={save.isPending}
          onClick={() =>
            save.mutate({
              ...v,
              no_show_deducts_package: v.no_show_deducts_package === '1',
              late_cancel_deducts_package: v.late_cancel_deducts_package === '1',
              admission_fee: v.admission_fee || null,
              training_monthly_fee: v.training_monthly_fee || null,
            })
          }
        >
          Save settings
        </Button>
      </div>
    </Card>
  )
}
