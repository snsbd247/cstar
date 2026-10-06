import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { api, errorMessage, validationErrors } from '../../api/client'
import { Button } from '../../components/ui/Button'
import { Alert, Card } from '../../components/ui/Card'
import { Field, Input, Textarea } from '../../components/ui/Field'
import { Spinner } from '../../components/ui/Spinner'

const groups: { title: string; hint?: string; fields: [string, string, 'text' | 'textarea', string?][] }[] = [
  {
    title: 'Homepage',
    fields: [
      ['tagline', 'Tagline', 'text'],
      ['hero_title', 'Headline', 'textarea'],
      ['hero_subtitle', 'Sub-headline', 'textarea'],
    ],
  },
  {
    title: 'About',
    fields: [
      ['about_title', 'Title', 'text'],
      ['about_body', 'Text', 'textarea', 'Leave an empty line between paragraphs'],
      ['mission', 'Mission', 'textarea'],
      ['vision', 'Vision', 'textarea'],
    ],
  },
  {
    title: 'Contact',
    hint: 'Empty fields are hidden on the website.',
    fields: [
      ['phone', 'Phone', 'text'],
      ['whatsapp', 'WhatsApp number', 'text', 'Shows a WhatsApp chat button, e.g. 8801711000000'],
      ['email', 'Email', 'text'],
      ['address', 'Address', 'textarea'],
      ['opening_hours', 'Opening hours', 'text'],
      ['facebook_url', 'Facebook page URL', 'text'],
      ['youtube_url', 'YouTube URL', 'text'],
      ['map_embed_url', 'Google Maps embed URL', 'text', 'Google Maps → Share → Embed a map → copy the src link'],
    ],
  },
  {
    title: 'Statistics',
    hint: 'Only real figures. Leave empty to hide. Therapist and branch counts are calculated automatically.',
    fields: [
      ['stat_children', 'Children supported', 'text', 'e.g. 500+'],
      ['stat_years', 'Years of experience', 'text', 'e.g. 10+'],
    ],
  },
]

export function SettingsTab() {
  const queryClient = useQueryClient()
  const { data, isLoading } = useQuery({ queryKey: ['cms', 'settings'], queryFn: async () => (await api.get<{ data: Record<string, string> }>('/cms/settings')).data.data })
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [message, setMessage] = useState<{ tone: 'green' | 'red'; text: string } | null>(null)
  const save = useMutation({
    mutationFn: (body: Record<string, string>) => api.put('/cms/settings', body),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['cms', 'settings'] })
      setMessage({ tone: 'green', text: 'Website settings saved.' })
    },
    onError: (e) => {
      setErrors(validationErrors(e))
      setMessage({ tone: 'red', text: Object.keys(validationErrors(e)).length ? 'Please correct the highlighted fields.' : errorMessage(e) })
    },
  })

  if (isLoading || !data) return <Spinner className="text-brand-600" />

  const onSubmit = (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault()
    setErrors({})
    setMessage(null)
    save.mutate(Object.fromEntries(new FormData(e.currentTarget)) as Record<string, string>)
  }

  return (
    <form onSubmit={onSubmit} className="space-y-5">
      {message && <Alert tone={message.tone}>{message.text}</Alert>}
      {groups.map((g) => (
        <Card key={g.title} className="p-5">
          <h2 className="font-semibold text-slate-900">{g.title}</h2>
          {g.hint && <p className="text-sm text-slate-500">{g.hint}</p>}
          <div className="mt-4 grid gap-4 sm:grid-cols-2">
            {g.fields.map(([name, label, type, hint]) => (
              <div key={name} className={type === 'textarea' ? 'sm:col-span-2' : undefined}>
                <Field label={label} htmlFor={`s_${name}`} hint={hint} error={errors[name]}>
                  {type === 'textarea' ? (
                    <Textarea id={`s_${name}`} name={name} rows={name === 'about_body' ? 6 : 2} defaultValue={data[name] ?? ''} />
                  ) : (
                    <Input id={`s_${name}`} name={name} defaultValue={data[name] ?? ''} />
                  )}
                </Field>
              </div>
            ))}
          </div>
        </Card>
      ))}
      <div className="flex justify-end">
        <Button type="submit" loading={save.isPending}>
          Save website settings
        </Button>
      </div>
    </form>
  )
}
