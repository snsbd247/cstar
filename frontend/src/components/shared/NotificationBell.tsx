import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Bell } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router'
import { api } from '../../api/client'
import { cn } from '../../utils/cn'

interface AppNotification {
  id: string
  title: string
  body: string
  url: string
  read_at: string | null
  created_at: string
}

/** In-app notifications (e.g. new website appointment requests). Polls every minute. */
export function NotificationBell() {
  const [open, setOpen] = useState(false)
  const box = useRef<HTMLDivElement>(null)
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { data } = useQuery({
    queryKey: ['notifications'],
    queryFn: async () => (await api.get<{ data: AppNotification[]; unread: number }>('/notifications')).data,
    refetchInterval: 60_000,
  })
  const refresh = () => queryClient.invalidateQueries({ queryKey: ['notifications'] })
  const markRead = useMutation({ mutationFn: (id: string) => api.post(`/notifications/${id}/read`), onSuccess: refresh })
  const markAll = useMutation({ mutationFn: () => api.post('/notifications/read-all'), onSuccess: refresh })

  useEffect(() => {
    const close = (e: MouseEvent) => !box.current?.contains(e.target as Node) && setOpen(false)
    document.addEventListener('mousedown', close)
    return () => document.removeEventListener('mousedown', close)
  }, [])

  return (
    <div ref={box} className="relative">
      <button onClick={() => setOpen((v) => !v)} className="relative rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-800" aria-label={`Notifications${data?.unread ? `, ${data.unread} unread` : ''}`}>
        <Bell className="size-5" />
        {!!data?.unread && (
          <span className="absolute top-1 right-1 flex min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-semibold text-white">{data.unread}</span>
        )}
      </button>
      {open && (
        <div className="absolute right-0 z-50 mt-1 w-80 max-w-[calc(100vw-2rem)] overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg">
          <div className="flex items-center justify-between border-b border-slate-100 px-4 py-2.5">
            <p className="text-sm font-semibold text-slate-900">Notifications</p>
            {!!data?.unread && (
              <button onClick={() => markAll.mutate()} className="text-xs text-sky-brand-600 hover:underline">
                Mark all read
              </button>
            )}
          </div>
          <ul className="max-h-96 overflow-y-auto">
            {!data?.data.length && <li className="px-4 py-6 text-center text-sm text-slate-500">Nothing yet.</li>}
            {data?.data.map((n) => (
              <li key={n.id}>
                <button
                  onClick={() => {
                    if (!n.read_at) markRead.mutate(n.id)
                    setOpen(false)
                    navigate(n.url)
                  }}
                  className={cn('block w-full px-4 py-3 text-left hover:bg-slate-50', !n.read_at && 'bg-brand-50/60')}
                >
                  <p className="text-sm font-medium text-slate-900">{n.title}</p>
                  <p className="truncate text-xs text-slate-500">{n.body}</p>
                  <p className="mt-0.5 text-[11px] text-slate-400">{new Date(n.created_at).toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Dhaka' })}</p>
                </button>
              </li>
            ))}
          </ul>
        </div>
      )}
    </div>
  )
}
