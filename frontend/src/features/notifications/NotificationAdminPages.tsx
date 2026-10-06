import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { CheckCheck, RotateCcw, Search } from 'lucide-react'
import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router'
import { api, errorMessage, validationErrors } from '../../api/client'
import { Button } from '../../components/ui/Button'
import { Alert, Badge, Card, PageHeader } from '../../components/ui/Card'
import { Field, Input, Select, Textarea } from '../../components/ui/Field'
import { Pager } from '../../components/ui/Pager'
import { Spinner } from '../../components/ui/Spinner'
import { useAuth } from '../../contexts/useAuth'
import { cn } from '../../utils/cn'

type Meta = { current_page: number; last_page: number; total: number }
const when = (iso: string) => new Date(iso).toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short' })

interface MyNotification {
  id: string
  kind: string
  title: string
  body: string
  url: string
  read_at: string | null
  created_at: string
}

/** Notifications → Notification Center: everything the bell has shown you, page by page. */
export function NotificationCenterPage() {
  const qc = useQueryClient()
  const navigate = useNavigate()
  const [filter, setFilter] = useState('')
  const [page, setPage] = useState(1)
  const { data, isLoading } = useQuery({
    queryKey: ['notification-center', filter, page],
    queryFn: async () => (await api.get<{ data: MyNotification[]; meta: Meta; unread: number }>('/notifications/center', { params: { filter: filter || undefined, page } })).data,
  })
  const refresh = () => ['notification-center', 'notifications'].forEach((k) => qc.invalidateQueries({ queryKey: [k] }))
  const readAll = useMutation({ mutationFn: () => api.post('/notifications/read-all'), onSuccess: refresh })
  const open = async (n: MyNotification) => {
    if (!n.read_at) await api.post(`/notifications/${n.id}/read`).then(refresh)
    if (n.url?.startsWith('/app')) navigate(n.url)
  }

  return (
    <>
      <PageHeader
        title="Notification Center"
        description="Approvals, renewals and other work waiting for you."
        actions={
          data && data.unread > 0 && (
            <Button variant="secondary" loading={readAll.isPending} onClick={() => readAll.mutate()}>
              <CheckCheck className="size-4" /> Mark all read ({data.unread})
            </Button>
          )
        }
      />
      <Card className="mb-4 p-3">
        <Select value={filter} onChange={(e) => (setFilter(e.target.value), setPage(1))} className="sm:w-48" aria-label="Show">
          <option value="">All notifications</option>
          <option value="unread">Unread only</option>
        </Select>
      </Card>
      <Card className="overflow-hidden">
        {isLoading ? (
          <Spinner className="m-5 text-brand-600" />
        ) : !data?.data.length ? (
          <p className="p-5 text-sm text-slate-500">{filter ? 'All read.' : 'No notifications yet.'}</p>
        ) : (
          <ul className="divide-y divide-slate-100">
            {data.data.map((n) => (
              <li key={n.id}>
                <button onClick={() => open(n)} className={cn('flex w-full items-start gap-3 px-4 py-3 text-left hover:bg-slate-50', !n.read_at && 'bg-brand-50/40')}>
                  <span className={cn('mt-1.5 size-2 shrink-0 rounded-full', n.read_at ? 'bg-transparent' : 'bg-brand-500')} />
                  <span className="min-w-0 flex-1">
                    <span className={cn('block text-sm', n.read_at ? 'text-slate-700' : 'font-semibold text-slate-900')}>{n.title}</span>
                    <span className="block text-sm text-slate-600">{n.body}</span>
                    <span className="block text-xs text-slate-400">{when(n.created_at)}</span>
                  </span>
                </button>
              </li>
            ))}
          </ul>
        )}
        <Pager meta={data?.meta} onPage={setPage} />
      </Card>
    </>
  )
}

interface Template {
  key: string
  label: string
  audience: 'parents' | 'staff'
  title: string
  body: string
  default_title: string
  default_body: string
  placeholders: string[]
}

/** Notifications → Templates: the wording of each automatic message. Empty = the built-in wording. */
export function TemplatesPage() {
  const { can } = useAuth()
  const { data, isLoading } = useQuery({ queryKey: ['notification-templates'], queryFn: async () => (await api.get<{ data: Template[] }>('/notification-templates')).data.data })

  return (
    <>
      <PageHeader title="Templates" description="Messages parents receive are in Bangla, staff messages in English. Words in {braces} are filled in when the message is sent." />
      {isLoading ? (
        <Spinner className="text-brand-600" />
      ) : (
        <div className="grid gap-4 lg:grid-cols-2">
          {data?.map((t) => <TemplateCard key={t.key + t.title + t.body} template={t} editable={can('settings.manage')} />)}
        </div>
      )}
    </>
  )
}

function TemplateCard({ template: t, editable }: { template: Template; editable: boolean }) {
  const qc = useQueryClient()
  const [title, setTitle] = useState(t.title)
  const [body, setBody] = useState(t.body)
  const [errors, setErrors] = useState<Record<string, string>>({})
  const save = useMutation({
    mutationFn: (values: { title: string; body: string }) => api.put(`/notification-templates/${t.key}`, values),
    onSuccess: () => (setErrors({}), qc.invalidateQueries({ queryKey: ['notification-templates'] })),
    onError: (e) => setErrors(validationErrors(e)),
  })
  const dirty = title !== t.title || body !== t.body
  const custom = t.title || t.body

  return (
    <Card className="space-y-3 p-4">
      <div className="flex items-start justify-between gap-2">
        <div>
          <p className="font-semibold text-slate-900">{t.label}</p>
          <p className="text-xs text-slate-500">
            {t.key} · to {t.audience}
          </p>
        </div>
        {custom ? <Badge tone="blue">customised</Badge> : <Badge>built-in</Badge>}
      </div>
      {save.isError && !Object.keys(errors).length && <Alert>{errorMessage(save.error)}</Alert>}
      <Field label="Title" htmlFor={`tp_t_${t.key}`} error={errors.title}>
        <Input id={`tp_t_${t.key}`} value={title} disabled={!editable} onChange={(e) => setTitle(e.target.value)} placeholder={t.default_title} className={t.audience === 'parents' ? 'font-bn' : ''} />
      </Field>
      <Field label="Message" htmlFor={`tp_b_${t.key}`} error={errors.body}>
        <Textarea id={`tp_b_${t.key}`} rows={2} value={body} disabled={!editable} onChange={(e) => setBody(e.target.value)} placeholder={t.default_body} className={t.audience === 'parents' ? 'font-bn' : ''} />
      </Field>
      <p className="text-xs text-slate-500">
        Can use:{' '}
        {t.placeholders.map((p) => (
          <code key={p} className="mr-1 rounded bg-slate-100 px-1">{`{${p}}`}</code>
        ))}
      </p>
      {editable && (
        <div className="flex justify-end gap-2">
          {custom && (
            <Button variant="ghost" className="min-h-8 px-3 text-xs" onClick={() => (setTitle(''), setBody(''), save.mutate({ title: '', body: '' }))}>
              <RotateCcw className="size-3.5" /> Back to built-in
            </Button>
          )}
          <Button className="min-h-8 px-3 text-xs" disabled={!dirty} loading={save.isPending} onClick={() => save.mutate({ title, body })}>
            Save
          </Button>
        </div>
      )}
    </Card>
  )
}

interface LogRow {
  id: string
  kind: string | null
  title: string
  body: string
  to: { id: number; name: string; type: 'parent' | 'staff'; has_email: boolean }
  read_at: string | null
  created_at: string
}

/** Notifications → Notification Logs: every automatic message and announcement sent, and whether it was read. */
export function NotificationLogsPage() {
  const [search, setSearch] = useState('')
  const [q, setQ] = useState('')
  const [audience, setAudience] = useState('')
  const [kind, setKind] = useState('')
  const [read, setRead] = useState('')
  const [page, setPage] = useState(1)

  useEffect(() => {
    const t = setTimeout(() => (setQ(search.trim()), setPage(1)), 300)
    return () => clearTimeout(t)
  }, [search])

  const { data, isLoading } = useQuery({
    queryKey: ['notification-logs', q, audience, kind, read, page],
    queryFn: async () =>
      (await api.get<{ data: LogRow[]; meta: Meta; kinds: string[] }>('/notification-logs', { params: { q: q || undefined, audience: audience || undefined, kind: kind || undefined, read: read || undefined, page } })).data,
  })
  const reset = (set: (v: string) => void) => (v: string) => (set(v), setPage(1))

  return (
    <>
      <PageHeader title="Notification Logs" description="In-app messages sent to parents and staff. Email copies go to people who have an address (Notification Settings)." />
      <Card className="mb-4 grid gap-2 p-3 sm:grid-cols-2 lg:grid-cols-4">
        <div className="relative">
          <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
          <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Recipient name or mobile" className="pl-9" aria-label="Search recipient" />
        </div>
        <Select value={audience} onChange={(e) => reset(setAudience)(e.target.value)} aria-label="Audience">
          <option value="">Parents and staff</option>
          <option value="parents">Parents</option>
          <option value="staff">Staff</option>
        </Select>
        <Select value={kind} onChange={(e) => reset(setKind)(e.target.value)} aria-label="Kind">
          <option value="">Any message</option>
          {data?.kinds.map((k) => (
            <option key={k} value={k}>
              {k}
            </option>
          ))}
          <option value="announcement">announcement</option>
        </Select>
        <Select value={read} onChange={(e) => reset(setRead)(e.target.value)} aria-label="Read">
          <option value="">Read or not</option>
          <option value="unread">Not read yet</option>
        </Select>
      </Card>
      <Card className="overflow-hidden">
        {isLoading ? (
          <Spinner className="m-5 text-brand-600" />
        ) : !data?.data.length ? (
          <p className="p-5 text-sm text-slate-500">Nothing sent yet.</p>
        ) : (
          <ul className="divide-y divide-slate-100">
            {data.data.map((n) => (
              <li key={n.id} className="grid gap-1 px-4 py-3 text-sm sm:grid-cols-[1fr_220px]">
                <div>
                  <p className={cn('font-medium text-slate-900', n.to.type === 'parent' && 'font-bn')}>{n.title}</p>
                  <p className={cn('text-slate-600', n.to.type === 'parent' && 'font-bn')}>{n.body}</p>
                  <p className="text-xs text-slate-400">{n.kind}</p>
                </div>
                <div className="text-xs text-slate-500 sm:text-right">
                  <p className="text-sm text-slate-800">
                    {n.to.name} <Badge tone={n.to.type === 'parent' ? 'blue' : 'gray'}>{n.to.type}</Badge>
                  </p>
                  <p>{when(n.created_at)}</p>
                  <p>{n.read_at ? `read ${when(n.read_at)}` : 'not read yet'}</p>
                </div>
              </li>
            ))}
          </ul>
        )}
        <Pager meta={data?.meta} onPage={setPage} />
      </Card>
    </>
  )
}
