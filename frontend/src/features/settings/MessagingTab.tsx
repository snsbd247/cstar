import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { MessageSquare, Send, Wallet } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router'
import { api, errorMessage, validationErrors } from '../../api/client'
import { Button } from '../../components/ui/Button'
import { Alert, Badge, Card } from '../../components/ui/Card'
import { Field, Input, Select } from '../../components/ui/Field'
import { Spinner } from '../../components/ui/Spinner'

interface Values {
  sms_enabled: string
  sms_driver: 'log' | 'greenweb'
  greenweb_token: string
  greenweb_token_saved: boolean
  whatsapp_enabled: string
  whatsapp_driver: 'log' | 'whatsapp_cloud'
  whatsapp_phone_number_id: string
  whatsapp_token: string
  whatsapp_token_saved: boolean
  whatsapp_template: string
  whatsapp_language: string
  prefix: string
  kinds: string[]
  staff_sms: string
}

interface Data {
  values: Values
  kinds: { key: string; label: string; audience: 'parents' | 'staff' }[]
  month: { sent: number; segments: number; failed: number }
}

/** Settings → SMS & WhatsApp (Sprint 18): gateway, which messages also go by text, test message and balance. */
export function MessagingTab() {
  const { data, isLoading } = useQuery({ queryKey: ['messaging-settings'], queryFn: async () => (await api.get<{ data: Data }>('/messaging/settings')).data.data })
  if (isLoading || !data) return <Spinner className="text-brand-600" />

  return <MessagingForm data={data} />
}

function MessagingForm({ data }: { data: Data }) {
  const qc = useQueryClient()
  const [v, setV] = useState<Values>(data.values)
  const [errors, setErrors] = useState<Record<string, string>>({})
  const save = useMutation({
    mutationFn: async () => (await api.put<{ data: Data }>('/messaging/settings', v)).data.data,
    // Keep the form (and its "Saved." message); take the server values so typed tokens are cleared.
    onSuccess: (d) => (setErrors({}), setV(d.values), qc.setQueryData(['messaging-settings'], d)),
    onError: (e) => setErrors(validationErrors(e)),
  })
  const set = <K extends keyof Values>(key: K, value: Values[K]) => setV((prev) => ({ ...prev, [key]: value }))
  const toggleKind = (key: string) => setV((prev) => ({ ...prev, kinds: prev.kinds.includes(key) ? prev.kinds.filter((k) => k !== key) : [...prev.kinds, key] }))
  const testMode = v.sms_driver === 'log'

  return (
    <div className="grid gap-4 lg:grid-cols-[1fr_340px]">
      <Card className="space-y-5 p-5">
        <div>
          <h2 className="font-semibold text-slate-900">SMS</h2>
          <p className="text-sm text-slate-500">Parents get the same message as in the portal by SMS. Bangla SMS: 70 letters per SMS part (longer messages cost more parts).</p>
        </div>
        {save.isError && !Object.keys(errors).length && <Alert>{errorMessage(save.error)}</Alert>}
        {save.isSuccess && <Alert tone="green">Saved.</Alert>}
        <label className="flex items-center gap-2 text-sm text-slate-700">
          <input type="checkbox" className="size-4 accent-brand-600" checked={v.sms_enabled === '1'} onChange={(e) => set('sms_enabled', e.target.checked ? '1' : '0')} /> Send SMS
        </label>
        <div className="grid gap-3 sm:grid-cols-2">
          <Field label="Gateway" htmlFor="ms_driver">
            <Select id="ms_driver" value={v.sms_driver} onChange={(e) => set('sms_driver', e.target.value as Values['sms_driver'])}>
              <option value="greenweb">GreenWeb BulkSMS (bdbulksms.com)</option>
              <option value="log">Test mode — nothing is sent</option>
            </Select>
          </Field>
          {v.sms_driver === 'greenweb' && (
            <Field label="GreenWeb token" htmlFor="ms_token" hint={v.greenweb_token_saved ? 'A token is saved — leave empty to keep it' : 'From your GreenWeb SMS portal → API'} error={errors.greenweb_token}>
              <Input id="ms_token" type="password" autoComplete="off" value={v.greenweb_token} onChange={(e) => set('greenweb_token', e.target.value.trim())} placeholder={v.greenweb_token_saved ? '••••••••' : ''} />
            </Field>
          )}
          <Field label="Start every SMS with" htmlFor="ms_prefix" hint="So families know who wrote, e.g. C-STAR" error={errors.prefix}>
            <Input id="ms_prefix" value={v.prefix} onChange={(e) => set('prefix', e.target.value)} />
          </Field>
        </div>
        {testMode && v.sms_enabled === '1' && <Alert>Test mode: messages are only written to the log and to Notifications → Logs — families receive nothing.</Alert>}

        <div>
          <p className="text-sm font-medium text-slate-700">Which messages also go by SMS / WhatsApp</p>
          <div className="mt-2 grid gap-1.5 sm:grid-cols-2">
            {data.kinds.map((k) => (
              <label key={k.key} className="flex items-start gap-2 text-sm text-slate-700">
                <input type="checkbox" className="mt-0.5 size-4 accent-brand-600" checked={v.kinds.includes(k.key)} onChange={() => toggleKind(k.key)} />
                <span>
                  {k.label} {k.audience === 'staff' && <Badge>staff</Badge>}
                </span>
              </label>
            ))}
          </div>
          <label className="mt-3 flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" className="size-4 accent-brand-600" checked={v.staff_sms === '1'} onChange={(e) => set('staff_sms', e.target.checked ? '1' : '0')} /> Staff also get the ticked staff messages by SMS
          </label>
          <p className="mt-1 text-xs text-slate-500">
            Wording comes from <Link to="/app/notifications/templates" className="text-brand-700 hover:underline">Notifications → Templates</Link>.
          </p>
        </div>

        <div className="space-y-3 border-t border-slate-100 pt-4">
          <h2 className="font-semibold text-slate-900">WhatsApp (optional)</h2>
          <p className="text-sm text-slate-500">
            Needs a WhatsApp Business (Cloud API) account from Meta and one approved message template with a single text parameter — the whole C-STAR message goes into it.
          </p>
          <label className="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" className="size-4 accent-brand-600" checked={v.whatsapp_enabled === '1'} onChange={(e) => set('whatsapp_enabled', e.target.checked ? '1' : '0')} /> Send WhatsApp messages too
          </label>
          <div className="grid gap-3 sm:grid-cols-2">
            <Field label="Provider" htmlFor="wa_driver">
              <Select id="wa_driver" value={v.whatsapp_driver} onChange={(e) => set('whatsapp_driver', e.target.value as Values['whatsapp_driver'])}>
                <option value="whatsapp_cloud">WhatsApp Cloud API (Meta)</option>
                <option value="log">Test mode — nothing is sent</option>
              </Select>
            </Field>
            {v.whatsapp_driver === 'whatsapp_cloud' && (
              <>
                <Field label="Phone number ID" htmlFor="wa_pid" error={errors.whatsapp_phone_number_id}>
                  <Input id="wa_pid" value={v.whatsapp_phone_number_id} onChange={(e) => set('whatsapp_phone_number_id', e.target.value.trim())} />
                </Field>
                <Field label="Access token" htmlFor="wa_token" hint={v.whatsapp_token_saved ? 'A token is saved — leave empty to keep it' : undefined}>
                  <Input id="wa_token" type="password" autoComplete="off" value={v.whatsapp_token} onChange={(e) => set('whatsapp_token', e.target.value.trim())} placeholder={v.whatsapp_token_saved ? '••••••••' : ''} />
                </Field>
                <Field label="Template name" htmlFor="wa_tpl" error={errors.whatsapp_template}>
                  <Input id="wa_tpl" value={v.whatsapp_template} onChange={(e) => set('whatsapp_template', e.target.value.trim())} />
                </Field>
                <Field label="Template language" htmlFor="wa_lang" hint="bn for Bangla">
                  <Input id="wa_lang" value={v.whatsapp_language} onChange={(e) => set('whatsapp_language', e.target.value.trim())} />
                </Field>
              </>
            )}
          </div>
        </div>

        <div className="flex justify-end">
          <Button loading={save.isPending} onClick={() => save.mutate()}>
            Save
          </Button>
        </div>
      </Card>

      <div className="space-y-4">
        <Card className="p-4">
          <h2 className="flex items-center gap-2 font-semibold text-slate-900">
            <MessageSquare className="size-4" /> This month
          </h2>
          <dl className="mt-2 grid grid-cols-2 gap-y-1 text-sm">
            <dt className="text-slate-500">Sent</dt>
            <dd className="text-right font-medium">{data.month.sent}</dd>
            <dt className="text-slate-500">SMS parts used</dt>
            <dd className="text-right font-medium">{data.month.segments}</dd>
            <dt className="text-slate-500">Failed</dt>
            <dd className={data.month.failed ? 'text-right font-medium text-red-700' : 'text-right font-medium'}>{data.month.failed}</dd>
          </dl>
          <Link to="/app/notifications/logs?view=text" className="mt-2 inline-block text-sm text-brand-700 hover:underline">
            SMS / WhatsApp log →
          </Link>
        </Card>
        <BalanceCard />
        <TestCard />
      </div>
    </div>
  )
}

function BalanceCard() {
  const balance = useMutation({ mutationFn: async () => (await api.get<{ data: { balance: string | null; note: string | null } }>('/messaging/balance')).data.data })

  return (
    <Card className="space-y-2 p-4">
      <h2 className="flex items-center gap-2 font-semibold text-slate-900">
        <Wallet className="size-4" /> GreenWeb balance
      </h2>
      {balance.data && <p className="text-sm text-slate-700">{balance.data.balance ?? balance.data.note ?? 'Could not read the balance — check the token.'}</p>}
      <Button variant="secondary" loading={balance.isPending} onClick={() => balance.mutate()}>
        Check balance
      </Button>
    </Card>
  )
}

function TestCard() {
  const [channel, setChannel] = useState('sms')
  const [to, setTo] = useState('')
  const test = useMutation({
    mutationFn: async () => (await api.post<{ data: { status: string; error: string | null; segments: number } }>('/messaging/test', { channel, to })).data.data,
  })

  return (
    <Card className="space-y-2 p-4">
      <h2 className="flex items-center gap-2 font-semibold text-slate-900">
        <Send className="size-4" /> Send a test message
      </h2>
      <p className="text-xs text-slate-500">Uses the saved settings — save first.</p>
      <div className="flex gap-2">
        <Select value={channel} onChange={(e) => setChannel(e.target.value)} className="w-28" aria-label="Channel">
          <option value="sms">SMS</option>
          <option value="whatsapp">WhatsApp</option>
        </Select>
        <Input value={to} onChange={(e) => setTo(e.target.value)} placeholder="01XXXXXXXXX" aria-label="Mobile number" />
      </div>
      {test.isError && <Alert>{Object.values(validationErrors(test.error))[0] ?? errorMessage(test.error)}</Alert>}
      {test.data && (test.data.status === 'sent' ? <Alert tone="green">Sent ({test.data.segments} SMS part).</Alert> : <Alert>Failed: {test.data.error}</Alert>)}
      <Button loading={test.isPending} disabled={!to} onClick={() => test.mutate()}>
        Send test
      </Button>
    </Card>
  )
}
