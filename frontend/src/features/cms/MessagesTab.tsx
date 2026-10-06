import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Mail, Phone } from 'lucide-react'
import { api } from '../../api/client'
import { Badge, Card } from '../../components/ui/Card'
import { Select } from '../../components/ui/Field'
import { Spinner } from '../../components/ui/Spinner'

interface ContactMessage {
  id: number
  name: string
  phone: string | null
  email: string | null
  subject: string | null
  message: string
  status: 'new' | 'read' | 'replied' | 'spam'
  created_at: string
  branch: string | null
}

const tone = { new: 'amber', read: 'gray', replied: 'green', spam: 'red' } as const

/** Contact-form messages from the website. */
export function MessagesTab() {
  const queryClient = useQueryClient()
  const { data, isLoading } = useQuery({ queryKey: ['contact-messages'], queryFn: async () => (await api.get<{ data: ContactMessage[] }>('/contact-messages')).data.data })
  const update = useMutation({
    mutationFn: ({ id, status }: { id: number; status: string }) => api.put(`/contact-messages/${id}`, { status }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['contact-messages'] }),
  })

  if (isLoading) return <Spinner className="text-brand-600" />
  if (!data?.length) return <Card className="p-5 text-sm text-slate-500">No messages yet.</Card>

  return (
    <div className="space-y-3">
      {data.map((m) => (
        <Card key={m.id} className="p-4">
          <div className="flex flex-wrap items-start justify-between gap-2">
            <div>
              <p className="font-medium text-slate-900">
                {m.name}
                {m.subject && <span className="font-normal text-slate-500"> — {m.subject}</span>}
              </p>
              <p className="text-xs text-slate-400">
                {new Date(m.created_at).toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Dhaka' })}
                {m.branch && ` · ${m.branch}`}
              </p>
            </div>
            <div className="flex items-center gap-2">
              <Badge tone={tone[m.status]}>{m.status}</Badge>
              <Select value={m.status} onChange={(e) => update.mutate({ id: m.id, status: e.target.value })} className="w-28 py-1 text-xs" aria-label="Status">
                <option value="new">New</option>
                <option value="read">Read</option>
                <option value="replied">Replied</option>
                <option value="spam">Spam</option>
              </Select>
            </div>
          </div>
          <p className="mt-2 whitespace-pre-line text-sm text-slate-700">{m.message}</p>
          <div className="mt-2 flex flex-wrap gap-4 text-sm">
            {m.phone && (
              <a href={`tel:${m.phone}`} className="inline-flex items-center gap-1 text-sky-brand-600">
                <Phone className="size-3.5" /> {m.phone}
              </a>
            )}
            {m.email && (
              <a href={`mailto:${m.email}`} className="inline-flex items-center gap-1 text-sky-brand-600">
                <Mail className="size-3.5" /> {m.email}
              </a>
            )}
          </div>
        </Card>
      ))}
    </div>
  )
}
