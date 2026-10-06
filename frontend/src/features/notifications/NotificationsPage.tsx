import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Send, Users } from 'lucide-react'
import { useState } from 'react'
import { api, errorMessage, validationErrors } from '../../api/client'
import { Button } from '../../components/ui/Button'
import { Alert, Badge, Card, PageHeader } from '../../components/ui/Card'
import { Field, Input, Select, Textarea } from '../../components/ui/Field'
import { useAuth } from '../../contexts/useAuth'

interface Announcement {
  id: number
  title: string
  body: string
  audience: 'parents' | 'staff' | 'everyone'
  recipients_count: number
  sent_by: string | null
  created_at: string
}

const audienceLabel = { parents: 'Parents', staff: 'Staff', everyone: 'Everyone' }

/** Announcements (e.g. holiday closure) and automatic-notification settings (Plan §৩৪). */
export default function NotificationsPage() {
  const { can, user } = useAuth()
  const qc = useQueryClient()
  const { data: history } = useQuery({ queryKey: ['announcements'], queryFn: async () => (await api.get<{ data: Announcement[] }>('/announcements')).data.data })
  const [v, setV] = useState({ title: '', body: '', audience: 'parents', branch_id: '' })
  const [preview, setPreview] = useState<number | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [sent, setSent] = useState<string | null>(null)
  const body = { ...v, branch_id: Number(v.branch_id) || null }

  const check = useMutation({ mutationFn: async () => (await api.post<{ data: { recipients: number } }>('/announcements', { ...body, preview: true })).data.data.recipients })
  const send = useMutation({
    mutationFn: async () => (await api.post<{ data: Announcement }>('/announcements', body)).data.data,
    onSuccess: (a) => {
      setSent(`Sent to ${a.recipients_count} people.`)
      setV({ ...v, title: '', body: '' })
      setPreview(null)
      qc.invalidateQueries({ queryKey: ['announcements'] })
    },
  })
  const run = async (fn: () => Promise<unknown>) => {
    setError(null)
    setSent(null)
    try {
      await fn()
    } catch (e) {
      setError(Object.values(validationErrors(e))[0] ?? errorMessage(e))
    }
  }

  return (
    <>
      <PageHeader title="Notifications" description="Send an announcement to parents or staff. Appointment, bill and payment messages go out automatically." />
      <div className="grid gap-5 lg:grid-cols-[1fr_360px]">
        <Card className="space-y-4 p-5">
          <h2 className="font-semibold text-slate-900">New announcement</h2>
          {error && <Alert>{error}</Alert>}
          {sent && <Alert tone="green">{sent}</Alert>}
          <div className="grid gap-3 sm:grid-cols-2">
            <Field label="To" htmlFor="an_aud">
              <Select id="an_aud" value={v.audience} onChange={(e) => (setV({ ...v, audience: e.target.value }), setPreview(null))}>
                <option value="parents">Parents (portal users)</option>
                <option value="staff">Staff</option>
                <option value="everyone">Everyone</option>
              </Select>
            </Field>
            <Field label="Branch" htmlFor="an_branch">
              <Select id="an_branch" value={v.branch_id} onChange={(e) => (setV({ ...v, branch_id: e.target.value }), setPreview(null))}>
                <option value="">All my branches</option>
                {user?.branches?.map((b) => (
                  <option key={b.id} value={b.id}>
                    {b.name}
                  </option>
                ))}
              </Select>
            </Field>
          </div>
          <Field label="Title" htmlFor="an_title" hint="Parents read the portal in Bangla — write in Bangla for them">
            <Input id="an_title" value={v.title} onChange={(e) => setV({ ...v, title: e.target.value })} placeholder="বৃহস্পতিবার কেন্দ্র বন্ধ" />
          </Field>
          <Field label="Message" htmlFor="an_body">
            <Textarea id="an_body" rows={4} value={v.body} onChange={(e) => setV({ ...v, body: e.target.value })} />
          </Field>
          <div className="flex flex-wrap items-center justify-end gap-2">
            {preview !== null && (
              <span className="mr-auto inline-flex items-center gap-1 text-sm text-slate-600">
                <Users className="size-4" /> {preview} people will receive it
              </span>
            )}
            <Button variant="secondary" loading={check.isPending} onClick={() => run(async () => setPreview(await check.mutateAsync()))}>
              Check recipients
            </Button>
            <Button loading={send.isPending} disabled={!v.title || !v.body} onClick={() => run(() => send.mutateAsync())}>
              <Send className="size-4" /> Send
            </Button>
          </div>
        </Card>

        <div className="space-y-4">
          {can('settings.manage') && <NotificationSettingsCard />}
          <Card className="p-5">
            <h2 className="font-semibold text-slate-900">Sent</h2>
            <ul className="mt-2 divide-y divide-slate-100">
              {history?.map((a) => (
                <li key={a.id} className="py-2.5">
                  <div className="flex items-center justify-between gap-2">
                    <p className="text-sm font-medium text-slate-900">{a.title}</p>
                    <Badge tone="blue">{audienceLabel[a.audience]}</Badge>
                  </div>
                  <p className="line-clamp-2 text-xs text-slate-600">{a.body}</p>
                  <p className="text-[11px] text-slate-400">
                    {new Date(a.created_at).toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short' })} · {a.recipients_count} people · {a.sent_by}
                  </p>
                </li>
              ))}
              {history?.length === 0 && <li className="py-2 text-sm text-slate-500">Nothing sent yet.</li>}
            </ul>
          </Card>
        </div>
      </div>
    </>
  )
}

export function NotificationSettingsCard() {
  const qc = useQueryClient()
  const { data } = useQuery({ queryKey: ['notification-settings'], queryFn: async () => (await api.get<{ data: Record<string, string> }>('/notification-settings')).data.data })
  const save = useMutation({
    mutationFn: (body: Record<string, boolean>) => api.put('/notification-settings', body),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['notification-settings'] }),
  })
  const rows = [
    ['parent_reminders', 'Remind parents the evening before an appointment'],
    ['staff_reminders', 'Remind therapists each morning about open session notes'],
    ['email_enabled', 'Also send by email when the person has an address'],
  ] as const

  return (
    <Card className="space-y-3 p-5">
      <h2 className="font-semibold text-slate-900">Automatic messages</h2>
      {rows.map(([k, label]) => (
        <label key={k} className="flex items-start gap-2 text-sm text-slate-700">
          <input
            type="checkbox"
            className="mt-0.5 size-4 accent-brand-600"
            checked={data?.[k] === '1'}
            disabled={!data || save.isPending}
            onChange={(e) => save.mutate({ [k]: e.target.checked })}
          />
          {label}
        </label>
      ))}
      <p className="text-xs text-slate-500">SMS and WhatsApp can be added once a Bangladeshi gateway is chosen.</p>
    </Card>
  )
}
