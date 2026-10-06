import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Pencil } from 'lucide-react'
import { useState, type FormEvent } from 'react'
import { api, errorMessage, validationErrors } from '../../api/client'
import { Button } from '../../components/ui/Button'
import { Alert, Badge, Card } from '../../components/ui/Card'
import { Field, Input, Textarea } from '../../components/ui/Field'
import { Modal } from '../../components/ui/Modal'
import { Spinner } from '../../components/ui/Spinner'

interface Member {
  kind: 'therapist' | 'trainer'
  id: number
  name: string
  designation: string | null
  qualification: string | null
  experience_years: number | null
  bio: string | null
  show_on_website: boolean
  sort_order: number
  photo_url: string | null
  status: string
}

/** Public profiles. Therapists and trainers are separate lists (TRAINER ≠ THERAPIST). */
export function TeamTab() {
  const { data, isLoading } = useQuery({
    queryKey: ['cms', 'team'],
    queryFn: async () => (await api.get<{ data: { therapists: Member[]; trainers: Member[] } }>('/cms/team')).data.data,
  })
  const [editing, setEditing] = useState<Member | null>(null)

  if (isLoading || !data) return <Spinner className="text-brand-600" />

  return (
    <div className="space-y-6">
      <p className="text-sm text-slate-500">Staff records are managed in Trainers / Therapists (Sprint 7–8). Here you only control what the website shows.</p>
      {[
        ['Therapists', data.therapists],
        ['Trainers', data.trainers],
      ].map(([title, list]) => (
        <section key={title as string}>
          <h2 className="mb-2 font-semibold text-slate-900">{title as string}</h2>
          <Card className="overflow-hidden">
            <ul className="divide-y divide-slate-100">
              {(list as Member[]).length === 0 && <li className="px-4 py-3 text-sm text-slate-500">None yet.</li>}
              {(list as Member[]).map((m) => (
                <li key={`${m.kind}-${m.id}`} className="flex items-center gap-3 px-4 py-3">
                  {m.photo_url ? (
                    <img src={m.photo_url} alt="" className="size-10 rounded-full object-cover" />
                  ) : (
                    <span className="flex size-10 items-center justify-center rounded-full bg-brand-100 text-sm font-semibold text-brand-700">{m.name[0]}</span>
                  )}
                  <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-medium text-slate-900">{m.name}</p>
                    <p className="truncate text-xs text-slate-500">{m.designation ?? m.qualification ?? ''}</p>
                  </div>
                  <Badge tone={m.show_on_website ? 'green' : 'gray'}>{m.show_on_website ? 'On website' : 'Hidden'}</Badge>
                  <button onClick={() => setEditing(m)} className="rounded-md p-2 text-slate-500 hover:bg-slate-100" aria-label={`Edit ${m.name}`}>
                    <Pencil className="size-4" />
                  </button>
                </li>
              ))}
            </ul>
          </Card>
        </section>
      ))}
      {editing && <MemberForm member={editing} onClose={() => setEditing(null)} />}
    </div>
  )
}

function MemberForm({ member, onClose }: { member: Member; onClose: () => void }) {
  const queryClient = useQueryClient()
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [error, setError] = useState<string | null>(null)
  const save = useMutation({
    mutationFn: async ({ body, photo }: { body: Record<string, unknown>; photo?: File }) => {
      await api.put(`/cms/team/${member.kind}/${member.id}`, body)
      if (photo) {
        const fd = new FormData()
        fd.append('photo', photo)
        await api.post(`/cms/team/${member.kind}/${member.id}/photo`, fd)
      }
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['cms', 'team'] })
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
    const get = (n: string) => f.elements.namedItem(n) as HTMLInputElement | null
    save.mutate({
      body: {
        ...(member.kind === 'therapist' ? { designation: get('designation')?.value || null } : {}),
        qualification: get('qualification')?.value || null,
        experience_years: Number(get('experience_years')?.value) || null,
        bio: get('bio')?.value || null,
        sort_order: Number(get('sort_order')?.value) || 0,
        show_on_website: !!get('show_on_website')?.checked,
      },
      photo: get('photo')?.files?.[0],
    })
  }

  return (
    <Modal open title={`${member.name} — public profile`} onClose={onClose}>
      <form onSubmit={onSubmit} className="space-y-4" noValidate>
        {error && <Alert>{error}</Alert>}
        {member.kind === 'therapist' && (
          <Field label="Designation" htmlFor="m_designation" error={errors.designation}>
            <Input id="m_designation" name="designation" defaultValue={member.designation ?? ''} />
          </Field>
        )}
        <div className="grid gap-3 sm:grid-cols-[1fr_120px]">
          <Field label="Qualification" htmlFor="m_qualification">
            <Input id="m_qualification" name="qualification" defaultValue={member.qualification ?? ''} />
          </Field>
          <Field label="Experience (yrs)" htmlFor="m_exp" error={errors.experience_years}>
            <Input id="m_exp" name="experience_years" type="number" defaultValue={member.experience_years ?? ''} />
          </Field>
        </div>
        <Field label="Short bio" htmlFor="m_bio">
          <Textarea id="m_bio" name="bio" rows={4} defaultValue={member.bio ?? ''} />
        </Field>
        <div className="grid grid-cols-[1fr_120px] gap-3">
          <Field label="Photo" htmlFor="m_photo" hint="Square photo works best · max 4 MB">
            <input id="m_photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" className="block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-2" />
          </Field>
          <Field label="Order" htmlFor="m_order">
            <Input id="m_order" name="sort_order" type="number" defaultValue={member.sort_order} />
          </Field>
        </div>
        <label className="flex items-center gap-2 text-sm text-slate-700">
          <input type="checkbox" name="show_on_website" defaultChecked={member.show_on_website} className="size-4" /> Show on website
        </label>
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
