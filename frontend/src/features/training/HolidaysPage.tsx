import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Trash2 } from 'lucide-react'
import { useState, type FormEvent } from 'react'
import { api, errorMessage, validationErrors } from '../../api/client'
import { Button } from '../../components/ui/Button'
import { Alert, Badge, Card, PageHeader } from '../../components/ui/Card'
import { Field, Input, Select } from '../../components/ui/Field'
import { Spinner } from '../../components/ui/Spinner'
import { useAuth } from '../../contexts/useAuth'

interface Holiday {
  id: number
  date: string
  title: string
  type: 'public' | 'center'
  branch: string | null
}

/** Holiday calendar (Plan §২৬ gap #1): drives "Holiday" attendance and, later, appointment availability. */
export default function HolidaysPage() {
  const { user, can } = useAuth()
  const qc = useQueryClient()
  const [year, setYear] = useState(() => new Date().getFullYear())
  const [error, setError] = useState<string | null>(null)
  const { data, isLoading } = useQuery({ queryKey: ['holidays', year], queryFn: async () => (await api.get<{ data: Holiday[] }>('/holidays', { params: { year } })).data.data })
  const add = useMutation({
    mutationFn: (body: Record<string, unknown>) => api.post('/holidays', body),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['holidays'] }),
    onError: (e) => setError(Object.values(validationErrors(e))[0] ?? errorMessage(e)),
  })
  const remove = useMutation({ mutationFn: (id: number) => api.delete(`/holidays/${id}`), onSuccess: () => qc.invalidateQueries({ queryKey: ['holidays'] }) })

  const onSubmit = (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault()
    setError(null)
    const f = Object.fromEntries(new FormData(e.currentTarget)) as Record<string, string>
    add.mutate({ ...f, branch_id: Number(f.branch_id) || null })
    e.currentTarget.reset()
  }

  return (
    <>
      <PageHeader title="Holidays" description="Fridays are weekly off by class schedule. Add public and center holidays here." />
      <div className="grid gap-5 lg:grid-cols-[1fr_320px]">
        <Card className="overflow-hidden">
          <div className="flex items-center justify-between border-b border-slate-100 px-4 py-3">
            <h2 className="font-semibold text-slate-900">{year}</h2>
            <div className="flex gap-1">
              <Button variant="ghost" onClick={() => setYear((y) => y - 1)}>
                ‹
              </Button>
              <Button variant="ghost" onClick={() => setYear((y) => y + 1)}>
                ›
              </Button>
            </div>
          </div>
          {isLoading ? (
            <Spinner className="m-4 text-brand-600" />
          ) : !data?.length ? (
            <p className="p-4 text-sm text-slate-500">No holidays added for {year}.</p>
          ) : (
            <ul className="divide-y divide-slate-100">
              {data.map((h) => (
                <li key={h.id} className="flex items-center gap-3 px-4 py-2.5">
                  <span className="w-28 text-sm font-medium text-slate-700">{new Date(h.date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', weekday: 'short' })}</span>
                  <span className="flex-1 text-sm text-slate-800">{h.title}</span>
                  <Badge tone={h.type === 'public' ? 'blue' : 'amber'}>{h.type}</Badge>
                  <span className="text-xs text-slate-500">{h.branch ?? 'All branches'}</span>
                  {can('branches.manage') && (
                    <button onClick={() => confirm(`Remove ${h.title}?`) && remove.mutate(h.id)} className="rounded p-1.5 text-slate-400 hover:text-red-600" aria-label={`Remove ${h.title}`}>
                      <Trash2 className="size-4" />
                    </button>
                  )}
                </li>
              ))}
            </ul>
          )}
        </Card>
        {can('branches.manage') && (
          <Card className="h-fit p-5">
            <h2 className="mb-3 font-semibold text-slate-900">Add holiday</h2>
            <form onSubmit={onSubmit} className="space-y-3">
              {error && <Alert>{error}</Alert>}
              <Field label="Date" htmlFor="h_date">
                <Input id="h_date" name="date" type="date" required />
              </Field>
              <Field label="Title" htmlFor="h_title">
                <Input id="h_title" name="title" required placeholder="Victory Day" />
              </Field>
              <Field label="Type" htmlFor="h_type">
                <Select id="h_type" name="type">
                  <option value="public">Public holiday</option>
                  <option value="center">Center closed</option>
                </Select>
              </Field>
              <Field label="Branch" htmlFor="h_branch">
                <Select id="h_branch" name="branch_id">
                  {user?.is_super_admin && <option value="">All branches</option>}
                  {user?.branches?.map((b) => (
                    <option key={b.id} value={b.id}>
                      {b.name}
                    </option>
                  ))}
                </Select>
              </Field>
              <Button type="submit" className="w-full" loading={add.isPending}>
                Add holiday
              </Button>
            </form>
          </Card>
        )}
      </div>
    </>
  )
}
