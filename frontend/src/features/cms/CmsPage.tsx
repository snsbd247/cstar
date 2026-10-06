import { siteUrl } from '../../api/client'
import { useSearchParams } from 'react-router'
import { PageHeader } from '../../components/ui/Card'
import { useAuth } from '../../contexts/useAuth'
import { cn } from '../../utils/cn'
import { ContentManager } from './ContentManager'
import { MessagesTab } from './MessagesTab'
import { ServicesTab } from './ServicesTab'
import { SettingsTab } from './SettingsTab'
import { TeamTab } from './TeamTab'

const tabs = [
  ['settings', 'Website', 'cms.manage'],
  ['services', 'Services', 'cms.manage'],
  ['team', 'Team', 'cms.manage'],
  ['testimonials', 'Testimonials', 'cms.manage'],
  ['faqs', 'FAQ', 'cms.manage'],
  ['gallery', 'Gallery', 'cms.manage'],
  ['notices', 'Notices', 'cms.manage'],
  ['messages', 'Messages', 'appointment_requests.manage'],
] as const

const published = (item: Record<string, unknown>) => (item.is_published ? { label: 'Published', tone: 'green' as const } : { label: 'Hidden', tone: 'gray' as const })

export default function CmsPage() {
  const { can } = useAuth()
  const [params, setParams] = useSearchParams()
  const visible = tabs.filter(([, , permission]) => can(permission))
  const tab = params.get('tab') ?? visible[0]?.[0]

  return (
    <>
      <PageHeader
        title="Website CMS"
        description="Content of the public website. Changes appear on the site immediately."
        actions={
          <a href={siteUrl()} target="_blank" rel="noopener" className="text-sm font-medium text-sky-brand-600 hover:underline">
            View website ↗
          </a>
        }
      />

      <div className="mb-5 overflow-x-auto border-b border-slate-200">
        <nav className="flex min-w-max gap-1">
          {visible.map(([key, label]) => (
            <button
              key={key}
              onClick={() => setParams({ tab: key }, { replace: true })}
              className={cn('border-b-2 px-3 py-2.5 text-sm font-medium', tab === key ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-800')}
            >
              {label}
            </button>
          ))}
        </nav>
      </div>

      {tab === 'settings' && <SettingsTab />}
      {tab === 'services' && <ServicesTab />}
      {tab === 'team' && <TeamTab />}
      {tab === 'testimonials' && (
        <ContentManager
          type="testimonials"
          addLabel="Add testimonial"
          title={(i) => `${i.name} — ${String(i.content).slice(0, 60)}`}
          subtitle={(i) => i.relation as string}
          badge={published}
          fields={[
            { name: 'name', label: 'Parent name', type: 'text' },
            { name: 'relation', label: 'Relation', type: 'text', hint: 'e.g. Mother of a 6-year-old' },
            { name: 'content', label: 'Quote', type: 'textarea' },
            { name: 'sort_order', label: 'Order', type: 'number' },
            { name: 'is_published', label: 'Publish on website', type: 'checkbox', hint: 'Only with the family\'s permission.' },
          ]}
        />
      )}
      {tab === 'faqs' && (
        <ContentManager
          type="faqs"
          addLabel="Add question"
          title={(i) => String(i.question)}
          subtitle={(i) => i.category as string}
          badge={published}
          fields={[
            { name: 'question', label: 'Question', type: 'text' },
            { name: 'answer', label: 'Answer', type: 'textarea' },
            { name: 'category', label: 'Category', type: 'text', hint: 'Groups questions on the FAQ page' },
            { name: 'sort_order', label: 'Order', type: 'number' },
            { name: 'is_published', label: 'Published', type: 'checkbox' },
          ]}
        />
      )}
      {tab === 'gallery' && (
        <ContentManager
          type="gallery"
          addLabel="Add photo"
          title={(i) => String(i.title)}
          subtitle={(i) => (i.consent_confirmed ? 'Consent confirmed' : 'Consent NOT confirmed')}
          badge={(i) => (i.is_published && i.consent_confirmed ? { label: 'Published', tone: 'green' } : { label: 'Hidden', tone: 'gray' })}
          fields={[
            { name: 'title', label: 'Caption', type: 'text' },
            { name: 'image', label: 'Photo', type: 'image', hint: 'JPG, PNG or WebP, max 5 MB' },
            { name: 'category', label: 'Category', type: 'text' },
            { name: 'sort_order', label: 'Order', type: 'number' },
            { name: 'consent_confirmed', label: 'Families of every child in this photo gave photo/media consent', type: 'checkbox' },
            { name: 'is_published', label: 'Publish on website', type: 'checkbox' },
          ]}
        />
      )}
      {tab === 'notices' && (
        <ContentManager
          type="notices"
          addLabel="Add notice"
          title={(i) => String(i.title)}
          subtitle={(i) => `Audience: ${i.audience}${i.publish_at ? ` · from ${String(i.publish_at).slice(0, 10)}` : ''}`}
          badge={published}
          fields={[
            { name: 'title', label: 'Title', type: 'text' },
            { name: 'body', label: 'Notice', type: 'textarea' },
            { name: 'audience', label: 'Audience', type: 'select', options: [['all', 'Everyone (website)'], ['parents', 'Parents only'], ['staff', 'Staff only']] },
            { name: 'publish_at', label: 'Publish from', type: 'datetime-local' },
            { name: 'expires_at', label: 'Expires', type: 'datetime-local' },
            { name: 'show_on_website', label: 'Show on website', type: 'checkbox' },
            { name: 'is_published', label: 'Published', type: 'checkbox' },
          ]}
        />
      )}
      {tab === 'messages' && <MessagesTab />}
    </>
  )
}
