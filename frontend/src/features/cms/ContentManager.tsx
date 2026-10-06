import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Pencil, Plus, Trash2 } from 'lucide-react'
import { useState, type FormEvent } from 'react'
import { api, errorMessage, validationErrors } from '../../api/client'
import { Button } from '../../components/ui/Button'
import { Alert, Badge, Card } from '../../components/ui/Card'
import { Field, Input, Select, Textarea } from '../../components/ui/Field'
import { Modal } from '../../components/ui/Modal'
import { Spinner } from '../../components/ui/Spinner'

export type FieldDef =
  | { name: string; label: string; type: 'text' | 'textarea' | 'number' | 'datetime-local'; required?: boolean; hint?: string }
  | { name: string; label: string; type: 'checkbox'; hint?: string }
  | { name: string; label: string; type: 'select'; options: [string, string][] }
  | { name: string; label: string; type: 'image'; required?: boolean; hint?: string }

type Item = Record<string, unknown> & { id: number }

/**
 * Generic list + modal editor for simple CMS content (/cms/{type}).
 * Sends multipart/form-data so image fields work; booleans go as "1"/"0".
 */
export function ContentManager({ type, fields, title, subtitle, badge, addLabel }: {
  type: 'testimonials' | 'faqs' | 'notices' | 'gallery'
  fields: FieldDef[]
  title: (item: Item) => string
  subtitle?: (item: Item) => string | null | undefined
  badge?: (item: Item) => { label: string; tone: 'green' | 'gray' | 'amber' | 'red' | 'blue' } | null
  addLabel: string
}) {
  const queryClient = useQueryClient()
  const [editing, setEditing] = useState<Item | 'new' | null>(null)
  const { data, isLoading } = useQuery({
    queryKey: ['cms', type],
    queryFn: async () => (await api.get<{ data: Item[] }>(`/cms/${type}`)).data.data,
  })
  const remove = useMutation({
    mutationFn: (id: number) => api.delete(`/cms/${type}/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['cms', type] }),
  })

  return (
    <div className="space-y-3">
      <div className="flex justify-end">
        <Button onClick={() => setEditing('new')}>
          <Plus className="size-4" /> {addLabel}
        </Button>
      </div>
      {isLoading ? (
        <Spinner className="text-brand-600" />
      ) : !data?.length ? (
        <Card className="p-5 text-sm text-slate-500">Nothing added yet.</Card>
      ) : (
        <Card className="overflow-hidden">
          <ul className="divide-y divide-slate-100">
            {data.map((item) => {
              const b = badge?.(item)
              return (
                <li key={item.id} className="flex items-center gap-3 px-4 py-3">
                  {typeof item.image_url === 'string' && <img src={item.image_url} alt="" className="size-12 shrink-0 rounded-lg object-cover" />}
                  <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-medium text-slate-900">{title(item)}</p>
                    {subtitle?.(item) && <p className="truncate text-xs text-slate-500">{subtitle(item)}</p>}
                  </div>
                  {b && <Badge tone={b.tone}>{b.label}</Badge>}
                  <button onClick={() => setEditing(item)} className="rounded-md p-2 text-slate-500 hover:bg-slate-100" aria-label="Edit">
                    <Pencil className="size-4" />
                  </button>
                  <button onClick={() => confirm('Delete this item?') && remove.mutate(item.id)} className="rounded-md p-2 text-slate-400 hover:bg-red-50 hover:text-red-600" aria-label="Delete">
                    <Trash2 className="size-4" />
                  </button>
                </li>
              )
            })}
          </ul>
        </Card>
      )}
      {editing && <ItemForm type={type} fields={fields} item={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />}
    </div>
  )
}

function ItemForm({ type, fields, item, onClose }: { type: string; fields: FieldDef[]; item: Item | null; onClose: () => void }) {
  const queryClient = useQueryClient()
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [error, setError] = useState<string | null>(null)
  const save = useMutation({
    mutationFn: (body: FormData) => (item ? api.post(`/cms/${type}/${item.id}`, body) : api.post(`/cms/${type}`, body)),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['cms', type] })
      onClose()
    },
    onError: (e) => {
      setErrors(validationErrors(e))
      if (!Object.keys(validationErrors(e)).length) setError(errorMessage(e))
    },
  })

  const onSubmit = (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault()
    setErrors({})
    setError(null)
    const form = e.currentTarget
    const body = new FormData()
    for (const f of fields) {
      const el = form.elements.namedItem(f.name) as HTMLInputElement | null
      if (!el) continue
      if (f.type === 'checkbox') body.append(f.name, el.checked ? '1' : '0')
      else if (f.type === 'image') {
        if (el.files?.[0]) body.append(f.name, el.files[0])
      } else body.append(f.name, el.value)
    }
    save.mutate(body)
  }

  const value = (name: string) => {
    const v = item?.[name]
    if (v === null || v === undefined) return ''
    return typeof v === 'string' && /^\d{4}-\d{2}-\d{2}T/.test(v) ? v.slice(0, 16) : String(v)
  }

  return (
    <Modal open title={item ? 'Edit' : 'Add'} onClose={onClose}>
      <form onSubmit={onSubmit} className="space-y-4" noValidate>
        {error && <Alert>{error}</Alert>}
        {fields.map((f) => {
          if (f.type === 'checkbox') {
            return (
              <div key={f.name}>
                <label className="flex items-start gap-2 text-sm text-slate-700">
                  <input type="checkbox" name={f.name} defaultChecked={!!item?.[f.name]} className="mt-0.5 size-4" /> {f.label}
                </label>
                {f.hint && <p className="ml-6 text-xs text-slate-500">{f.hint}</p>}
                {errors[f.name] && <p className="ml-6 text-xs text-red-600">{errors[f.name]}</p>}
              </div>
            )
          }
          return (
            <Field key={f.name} label={f.label} htmlFor={`f_${f.name}`} error={errors[f.name]} hint={'hint' in f ? f.hint : undefined}>
              {f.type === 'textarea' ? (
                <Textarea id={`f_${f.name}`} name={f.name} rows={4} defaultValue={value(f.name)} />
              ) : f.type === 'select' ? (
                <Select id={`f_${f.name}`} name={f.name} defaultValue={value(f.name) || f.options[0][0]}>
                  {f.options.map(([v, l]) => (
                    <option key={v} value={v}>
                      {l}
                    </option>
                  ))}
                </Select>
              ) : f.type === 'image' ? (
                <input id={`f_${f.name}`} name={f.name} type="file" accept="image/jpeg,image/png,image/webp" className="block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-2" />
              ) : (
                <Input id={`f_${f.name}`} name={f.name} type={f.type} defaultValue={value(f.name)} />
              )}
            </Field>
          )
        })}
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
