import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowDown, ArrowUp, Eye, EyeOff, ExternalLink } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router'
import { api, errorMessage, siteUrl, validationErrors } from '../../api/client'
import { Button } from '../../components/ui/Button'
import { Alert, Badge, Card, PageHeader } from '../../components/ui/Card'
import { Field, Input, Textarea } from '../../components/ui/Field'
import { Spinner } from '../../components/ui/Spinner'
import { Stat } from '../../components/ui/Stat'
import { cn } from '../../utils/cn'

interface PageRow {
  key: string
  label: string
  path: string
  in_menu_option: boolean
  in_menu: boolean
  title: string
  description: string
}

interface Structure {
  pages: PageRow[]
  sections: { key: string; label: string; visible: boolean }[]
  seo: { seo_default_description: string; seo_noindex: string; google_site_verification: string; google_analytics_id: string }
  branches: { id: number; name: string; name_bn: string | null; address: string | null; phone: string | null; show_on_website: boolean; sort_order: number; is_active: boolean }[]
  site_url: string
}

const KEY = ['cms-structure']
const useStructure = () => useQuery({ queryKey: KEY, queryFn: async () => (await api.get<{ data: Structure }>('/cms/structure')).data.data })

function useSave<T>(url: string) {
  const qc = useQueryClient()
  const [errors, setErrors] = useState<Record<string, string>>({})
  const save = useMutation({
    mutationFn: async (body: T) => (await api.put<{ data: Structure }>(url, body)).data.data,
    onSuccess: (d) => (qc.setQueryData(KEY, d), setErrors({})),
    onError: (e) => setErrors(validationErrors(e)),
  })

  return { save, errors }
}

/** Website / CMS → Pages: menu and search-engine wording of every public page. */
export function PagesTab() {
  const { data } = useStructure()
  return data ? <PagesForm key={JSON.stringify(data.pages)} pages={data.pages} /> : <Spinner className="text-brand-600" />
}

function PagesForm({ pages: initial }: { pages: PageRow[] }) {
  const [pages, setPages] = useState(initial)
  const { save, errors } = useSave<{ pages: PageRow[] }>('/cms/pages')
  const set = (i: number, patch: Partial<PageRow>) => setPages(pages.map((p, j) => (j === i ? { ...p, ...patch } : p)))

  return (
    <Card className="overflow-hidden">
      <p className="border-b border-slate-100 px-4 py-3 text-sm text-slate-600">
        Leave the title or description empty to use the page’s own wording. Google shows about 60 characters of a title and 155 of a description.
      </p>
      {save.isError && !Object.keys(errors).length && (
        <div className="p-4">
          <Alert>{errorMessage(save.error)}</Alert>
        </div>
      )}
      <ul className="divide-y divide-slate-100">
        {pages.map((p, i) => (
          <li key={p.key} className="grid gap-3 px-4 py-3 lg:grid-cols-[200px_1fr_1fr]">
            <div>
              <p className="font-medium text-slate-900">{p.label}</p>
              <a href={siteUrl(p.path)} target="_blank" rel="noopener" className="inline-flex items-center gap-1 text-xs text-sky-brand-600 hover:underline">
                {p.path} <ExternalLink className="size-3" />
              </a>
              {p.in_menu_option && (
                <label className="mt-1 flex items-center gap-2 text-xs text-slate-600">
                  <input type="checkbox" className="size-3.5 accent-brand-600" checked={p.in_menu} onChange={(e) => set(i, { in_menu: e.target.checked })} /> In the top menu
                </label>
              )}
            </div>
            <Field label={`Title (${p.title.length}/70)`} htmlFor={`pg_t_${p.key}`} error={errors[`pages.${i}.title`]}>
              <Input id={`pg_t_${p.key}`} value={p.title} onChange={(e) => set(i, { title: e.target.value })} placeholder="Default title" />
            </Field>
            <Field label={`Description (${p.description.length}/160)`} htmlFor={`pg_d_${p.key}`} error={errors[`pages.${i}.description`]}>
              <Textarea id={`pg_d_${p.key}`} rows={2} value={p.description} onChange={(e) => set(i, { description: e.target.value })} placeholder="Default description" />
            </Field>
          </li>
        ))}
      </ul>
      <div className="flex items-center justify-end gap-3 border-t border-slate-100 px-4 py-3">
        {save.isSuccess && <span className="text-sm text-brand-700">Saved — live on the website.</span>}
        <Button loading={save.isPending} onClick={() => save.mutate({ pages })}>
          Save pages
        </Button>
      </div>
    </Card>
  )
}

/** Website / CMS → Page Sections: order and visibility of the home page sections. */
export function SectionsTab() {
  const { data } = useStructure()
  return data ? <SectionsForm key={JSON.stringify(data.sections)} initial={data.sections} siteUrl={data.site_url} /> : <Spinner className="text-brand-600" />
}

function SectionsForm({ initial, siteUrl: home }: { initial: Structure['sections']; siteUrl: string }) {
  const [sections, setSections] = useState(initial)
  const { save } = useSave<{ sections: Structure['sections'] }>('/cms/home-sections')
  const move = (i: number, dir: -1 | 1) => {
    const next = [...sections]
    ;[next[i], next[i + dir]] = [next[i + dir], next[i]]
    setSections(next)
  }

  return (
    <Card className="max-w-2xl overflow-hidden">
      <p className="border-b border-slate-100 px-4 py-3 text-sm text-slate-600">
        Home page sections from top to bottom. A section with nothing published (e.g. no testimonials yet) stays hidden on its own.
      </p>
      <ul className="divide-y divide-slate-100">
        {sections.map((s, i) => (
          <li key={s.key} className={cn('flex items-center gap-2 px-4 py-2', !s.visible && 'bg-slate-50 text-slate-400')}>
            <span className="w-6 text-right text-xs text-slate-400">{i + 1}</span>
            <span className="flex-1 text-sm font-medium">{s.label}</span>
            <Button variant="ghost" className="min-h-8 px-2" disabled={i === 0} onClick={() => move(i, -1)} aria-label={`Move ${s.label} up`}>
              <ArrowUp className="size-4" />
            </Button>
            <Button variant="ghost" className="min-h-8 px-2" disabled={i === sections.length - 1} onClick={() => move(i, 1)} aria-label={`Move ${s.label} down`}>
              <ArrowDown className="size-4" />
            </Button>
            <Button variant="ghost" className="min-h-8 px-2" onClick={() => setSections(sections.map((x, j) => (j === i ? { ...x, visible: !x.visible } : x)))} aria-label={s.visible ? `Hide ${s.label}` : `Show ${s.label}`}>
              {s.visible ? <Eye className="size-4 text-brand-600" /> : <EyeOff className="size-4" />}
            </Button>
          </li>
        ))}
      </ul>
      <div className="flex items-center justify-end gap-3 border-t border-slate-100 px-4 py-3">
        {save.isSuccess && (
          <a href={home} target="_blank" rel="noopener" className="text-sm text-brand-700 hover:underline">
            Saved — view the home page ↗
          </a>
        )}
        <Button loading={save.isPending} onClick={() => save.mutate({ sections })}>
          Save order
        </Button>
      </div>
    </Card>
  )
}

/** Website / CMS → Branches: which branches the public site lists, and in which order. */
export function WebsiteBranchesTab() {
  const { data } = useStructure()
  const qc = useQueryClient()
  const update = useMutation({
    mutationFn: ({ id, ...body }: { id: number; show_on_website: boolean; sort_order: number }) => api.put(`/cms/branches/${id}`, body),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  })
  if (!data) return <Spinner className="text-brand-600" />

  return (
    <Card className="max-w-3xl overflow-hidden">
      <p className="border-b border-slate-100 px-4 py-3 text-sm text-slate-600">
        Address, phone, map and opening hours come from{' '}
        <Link to="/app/branches" className="text-brand-700 hover:underline">
          Branches
        </Link>
        .
      </p>
      <ul className="divide-y divide-slate-100">
        {data.branches.map((b) => (
          <li key={b.id} className="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm">
            <div>
              <p className="font-medium text-slate-900">
                {b.name} {!b.is_active && <Badge>inactive</Badge>}
              </p>
              <p className="text-xs text-slate-500">{[b.address, b.phone].filter(Boolean).join(' · ') || 'No address or phone yet — add them before showing it'}</p>
            </div>
            <span className="flex items-center gap-3">
              <Input
                type="number"
                defaultValue={b.sort_order}
                aria-label={`Order of ${b.name}`}
                className="w-20"
                onBlur={(e) => Number(e.target.value) !== b.sort_order && update.mutate({ id: b.id, show_on_website: b.show_on_website, sort_order: Number(e.target.value) })}
              />
              <label className="flex items-center gap-2">
                <input type="checkbox" className="size-4 accent-brand-600" checked={b.show_on_website} onChange={(e) => update.mutate({ id: b.id, show_on_website: e.target.checked, sort_order: b.sort_order })} /> On website
              </label>
            </span>
          </li>
        ))}
      </ul>
    </Card>
  )
}

/** Website / CMS → SEO Settings. */
export function SeoTab() {
  const { data } = useStructure()
  return data ? <SeoForm key={JSON.stringify(data.seo)} initial={data.seo} /> : <Spinner className="text-brand-600" />
}

function SeoForm({ initial }: { initial: Structure['seo'] }) {
  const [v, setV] = useState(initial)
  const { save, errors } = useSave<Structure['seo']>('/cms/seo')

  return (
    <Card className="max-w-2xl space-y-4 p-5">
      {save.isError && !Object.keys(errors).length && <Alert>{errorMessage(save.error)}</Alert>}
      {save.isSuccess && <Alert tone="green">Saved.</Alert>}
      <label className={cn('flex items-start gap-2 rounded-lg p-3 text-sm', v.seo_noindex === '1' ? 'bg-amber-50 text-amber-900' : 'bg-slate-50 text-slate-700')}>
        <input type="checkbox" className="mt-0.5 size-4 accent-amber-600" checked={v.seo_noindex === '1'} onChange={(e) => setV({ ...v, seo_noindex: e.target.checked ? '1' : '0' })} />
        <span>
          <b>Hide the website from Google and other search engines</b>
          <span className="block text-xs">Use while testing with demo content. Turn off at go-live so families can find the center.</span>
        </span>
      </label>
      <Field label="Default description" htmlFor="seo_desc" hint={`Used by pages without their own description (${v.seo_default_description.length}/160)`} error={errors.seo_default_description}>
        <Textarea id="seo_desc" rows={2} value={v.seo_default_description} onChange={(e) => setV({ ...v, seo_default_description: e.target.value })} />
      </Field>
      <Field label="Google Search Console verification code" htmlFor="seo_gsc" hint="Search Console → Settings → Ownership → HTML tag; paste only the content value" error={errors.google_site_verification}>
        <Input id="seo_gsc" value={v.google_site_verification} onChange={(e) => setV({ ...v, google_site_verification: e.target.value })} />
      </Field>
      <Field label="Google Analytics ID" htmlFor="seo_ga" hint="G-XXXXXXXXXX. Not loaded while the site is hidden from search engines." error={errors.google_analytics_id}>
        <Input id="seo_ga" value={v.google_analytics_id} onChange={(e) => setV({ ...v, google_analytics_id: e.target.value.trim() })} />
      </Field>
      <p className="text-xs text-slate-500">The sitemap (sitemap.xml) and robots.txt are created automatically. Page titles are set under Pages.</p>
      <div className="flex justify-end">
        <Button loading={save.isPending} onClick={() => save.mutate(v)}>
          Save
        </Button>
      </div>
    </Card>
  )
}

interface CmsDashboard {
  content: Record<string, { shown: number; total: number }>
  inbox: { new_requests: number; requests_week: number; new_messages: number }
  latest_requests: { id: number; reference: string; name: string | null; status: string; at: string }[]
  seo: { noindex: boolean }
}

const contentLinks: Record<string, [string, string]> = {
  services: ['Services', '/app/cms?tab=services'],
  therapists: ['Therapists', '/app/cms?tab=team'],
  trainers: ['Trainers', '/app/cms?tab=team'],
  testimonials: ['Testimonials', '/app/cms?tab=testimonials'],
  gallery: ['Gallery photos', '/app/cms?tab=gallery'],
  faqs: ['FAQs', '/app/cms?tab=faqs'],
  notices: ['Notices', '/app/cms?tab=notices'],
  branches: ['Branches', '/app/cms?tab=branches'],
}

/** Website / CMS → Website Dashboard. */
export function WebsiteDashboardPage() {
  const { data, isLoading } = useQuery({ queryKey: ['cms-dashboard'], queryFn: async () => (await api.get<{ data: CmsDashboard }>('/cms/dashboard')).data.data })

  return (
    <>
      <PageHeader
        title="Website Dashboard"
        description="What the public website shows and what visitors sent."
        actions={
          <a href={siteUrl()} target="_blank" rel="noopener" className="text-sm font-medium text-sky-brand-600 hover:underline">
            View website ↗
          </a>
        }
      />
      {isLoading || !data ? (
        <Spinner className="text-brand-600" />
      ) : (
        <>
          {data.seo.noindex && (
            <div className="mb-4">
              <Alert>
                The website is hidden from search engines (testing period).{' '}
                <Link to="/app/cms?tab=seo" className="underline">
                  Change in SEO Settings
                </Link>
              </Alert>
            </div>
          )}
          <div className="mb-4 grid gap-3 sm:grid-cols-3">
            <Stat label="New appointment requests" value={data.inbox.new_requests} to="/app/online-requests" tone={data.inbox.new_requests ? 'amber' : undefined} />
            <Stat label="Requests in the last 7 days" value={data.inbox.requests_week} to="/app/online-requests" />
            <Stat label="Unread contact messages" value={data.inbox.new_messages} to="/app/cms?tab=messages" tone={data.inbox.new_messages ? 'amber' : undefined} />
          </div>
          <div className="grid gap-4 lg:grid-cols-[1fr_360px]">
            <Card className="overflow-hidden">
              <h2 className="border-b border-slate-100 px-4 py-3 font-semibold text-slate-900">On the website</h2>
              <ul className="grid divide-y divide-slate-100 sm:grid-cols-2 sm:divide-y-0">
                {Object.entries(data.content).map(([k, c]) => (
                  <li key={k} className="border-slate-100 sm:border-b">
                    <Link to={contentLinks[k]?.[1] ?? '/app/cms'} className="flex items-center justify-between px-4 py-3 text-sm hover:bg-slate-50">
                      <span className="text-slate-700">{contentLinks[k]?.[0] ?? k}</span>
                      <span>
                        <b>{c.shown}</b> <span className="text-slate-400">shown of {c.total}</span>
                      </span>
                    </Link>
                  </li>
                ))}
              </ul>
            </Card>
            <Card className="overflow-hidden">
              <h2 className="border-b border-slate-100 px-4 py-3 font-semibold text-slate-900">Latest requests</h2>
              <ul className="divide-y divide-slate-100 text-sm">
                {data.latest_requests.map((r) => (
                  <li key={r.id} className="flex justify-between gap-2 px-4 py-2.5">
                    <span>
                      <span className="text-slate-800">{r.name ?? r.reference}</span>
                      <span className="block text-xs text-slate-500">{new Date(r.at).toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short' })}</span>
                    </span>
                    <Badge tone={r.status === 'new' ? 'amber' : 'gray'}>{r.status}</Badge>
                  </li>
                ))}
                {!data.latest_requests.length && <li className="px-4 py-3 text-slate-500">None yet.</li>}
              </ul>
            </Card>
          </div>
        </>
      )}
    </>
  )
}
