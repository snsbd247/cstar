import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Pencil } from 'lucide-react'
import { useState, type FormEvent } from 'react'
import { api, errorMessage, validationErrors } from '../../api/client'
import { Button } from '../../components/ui/Button'
import { Alert, Badge, Card } from '../../components/ui/Card'
import { Field, Input, Textarea } from '../../components/ui/Field'
import { Modal } from '../../components/ui/Modal'
import { Spinner } from '../../components/ui/Spinner'

interface CmsService {
  id: number
  category: 'therapy' | 'training' | 'assessment' | 'consultation'
  name: string
  name_bn: string | null
  slug: string
  short_description: string | null
  description: string | null
  default_duration_min: number | null
  is_bookable_online: boolean
  show_on_website: boolean
  sort_order: number
  image_url: string | null
}

/** Website pages for services. Therapy and Training programs are listed separately (Plan §২৮). */
export function ServicesTab() {
  const { data, isLoading } = useQuery({ queryKey: ['cms', 'services'], queryFn: async () => (await api.get<{ data: CmsService[] }>('/cms/services')).data.data })
  const [editing, setEditing] = useState<CmsService | null>(null)

  if (isLoading || !data) return <Spinner className="text-brand-600" />

  return (
    <div className="space-y-6">
      {[
        ['Therapy services', data.filter((s) => s.category !== 'training')],
        ['Training programs', data.filter((s) => s.category === 'training')],
      ].map(([title, list]) => (
        <section key={title as string}>
          <h2 className="mb-2 font-semibold text-slate-900">{title as string}</h2>
          <Card className="overflow-hidden">
            <ul className="divide-y divide-slate-100">
              {(list as CmsService[]).map((s) => (
                <li key={s.id} className="flex items-center gap-3 px-4 py-3">
                  {s.image_url ? <img src={s.image_url} alt="" className="size-10 rounded-lg object-cover" /> : <span className="size-10 rounded-lg bg-slate-100" />}
                  <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-medium text-slate-900">{s.name}</p>
                    <p className="truncate text-xs text-slate-500">{s.short_description || 'No summary yet'}</p>
                  </div>
                  {s.is_bookable_online && <Badge tone="blue">Bookable</Badge>}
                  <Badge tone={s.show_on_website ? 'green' : 'gray'}>{s.show_on_website ? 'On website' : 'Hidden'}</Badge>
                  <button onClick={() => setEditing(s)} className="rounded-md p-2 text-slate-500 hover:bg-slate-100" aria-label={`Edit ${s.name}`}>
                    <Pencil className="size-4" />
                  </button>
                </li>
              ))}
            </ul>
          </Card>
        </section>
      ))}
      {editing && <ServiceForm service={editing} onClose={() => setEditing(null)} />}
    </div>
  )
}

function ServiceForm({ service, onClose }: { service: CmsService; onClose: () => void }) {
  const queryClient = useQueryClient()
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [error, setError] = useState<string | null>(null)
  const save = useMutation({
    mutationFn: async ({ body, image }: { body: Record<string, unknown>; image?: File }) => {
      await api.put(`/cms/services/${service.id}`, body)
      if (image) {
        const fd = new FormData()
        fd.append('image', image)
        await api.post(`/cms/services/${service.id}/image`, fd)
      }
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['cms', 'services'] })
      onClose()
    },
    onError: (e) => {
      setErrors(validationErrors(e))
      if (!Object.keys(validationErrors(e)).length) setError(errorMessage(e))
    },
  })

  const onSubmit = (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault()
    const f = e.currentTarget
    const get = (n: string) => (f.elements.namedItem(n) as HTMLInputElement)
    save.mutate({
      body: {
        name: get('name').value,
        name_bn: get('name_bn').value || null,
        short_description: get('short_description').value || null,
        description: get('description').value || null,
        default_duration_min: Number(get('default_duration_min').value) || null,
        sort_order: Number(get('sort_order').value) || 0,
        show_on_website: get('show_on_website').checked,
        is_bookable_online: get('is_bookable_online').checked,
      },
      image: get('image').files?.[0],
    })
  }

  return (
    <Modal open title={`Edit ${service.name}`} onClose={onClose}>
      <form onSubmit={onSubmit} className="space-y-4" noValidate>
        {error && <Alert>{error}</Alert>}
        <div className="grid gap-3 sm:grid-cols-2">
          <Field label="Name" htmlFor="sv_name" error={errors.name}>
            <Input id="sv_name" name="name" defaultValue={service.name} />
          </Field>
          <Field label="Name (Bangla)" htmlFor="sv_name_bn">
            <Input id="sv_name_bn" name="name_bn" className="font-bn" defaultValue={service.name_bn ?? ''} />
          </Field>
        </div>
        <Field label="Summary" htmlFor="sv_short" hint="One or two sentences for cards" error={errors.short_description}>
          <Textarea id="sv_short" name="short_description" rows={2} defaultValue={service.short_description ?? ''} />
        </Field>
        <Field label="Page text" htmlFor="sv_desc" hint="Leave an empty line between paragraphs" error={errors.description}>
          <Textarea id="sv_desc" name="description" rows={8} defaultValue={service.description ?? ''} />
        </Field>
        <div className="grid grid-cols-2 gap-3">
          <Field label="Session length (min)" htmlFor="sv_dur" error={errors.default_duration_min}>
            <Input id="sv_dur" name="default_duration_min" type="number" defaultValue={service.default_duration_min ?? ''} />
          </Field>
          <Field label="Order" htmlFor="sv_order">
            <Input id="sv_order" name="sort_order" type="number" defaultValue={service.sort_order} />
          </Field>
        </div>
        <Field label="Picture" htmlFor="sv_image" hint="Optional · JPG/PNG/WebP · max 5 MB">
          <input id="sv_image" name="image" type="file" accept="image/jpeg,image/png,image/webp" className="block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-2" />
        </Field>
        <div className="space-y-2 text-sm text-slate-700">
          <label className="flex items-center gap-2">
            <input type="checkbox" name="show_on_website" defaultChecked={service.show_on_website} className="size-4" /> Show on website
          </label>
          <label className="flex items-center gap-2">
            <input type="checkbox" name="is_bookable_online" defaultChecked={service.is_bookable_online} className="size-4" /> Can be chosen in the online appointment form
          </label>
        </div>
        <div className="flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" loading={save.isPending}>
            Save
          </Button>
        </div>
      </form>
    </Modal>
  )
}
