import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { CheckCircle2, Clock, CreditCard, XCircle } from 'lucide-react'
import { useState } from 'react'
import { useSearchParams } from 'react-router'
import { api, errorMessage, validationErrors } from '../../api/client'
import { Button } from '../../components/ui/Button'
import { Alert, Card } from '../../components/ui/Card'
import { Input } from '../../components/ui/Field'
import { cn } from '../../utils/cn'
import { bnTaka, portalPdf } from './api'

const gatewayLabel: Record<string, { title: string; note: string }> = {
  bkash: { title: 'বিকাশ', note: 'বিকাশ অ্যাপ/নম্বর দিয়ে' },
  sslcommerz: { title: 'কার্ড / নগদ / রকেট', note: 'ভিসা, মাস্টারকার্ড, নগদ, রকেট, ইন্টারনেট ব্যাংকিং' },
  test: { title: 'পরীক্ষামূলক', note: 'শুধু প্রশিক্ষণের জন্য — আসল টাকা নয়' },
}

/** Sprint 19: pay the child's dues from the portal through bKash or SSLCommerz. */
export function OnlinePayCard({ childId, due }: { childId: number; due: number }) {
  const { data } = useQuery({
    queryKey: ['portal', 'online-options', childId],
    queryFn: async () => (await api.get<{ data: { gateways: string[]; due: number; min_amount: number } }>(`/portal/children/${childId}/online-payment`)).data.data,
  })
  const [open, setOpen] = useState(false)
  const [amount, setAmount] = useState(String(due))
  const [gateway, setGateway] = useState<string | null>(null)
  const start = useMutation({
    mutationFn: async () => (await api.post<{ data: { redirect_url: string } }>(`/portal/children/${childId}/online-payment`, { gateway, amount: Number(amount) })).data.data,
    onSuccess: (d) => window.location.assign(d.redirect_url),
  })
  if (!data || !data.gateways.length || due <= 0) {
    return due > 0 ? <p className="text-sm text-slate-600">বকেয়া পরিশোধ করতে রিসেপশনে নগদ, বিকাশ, নগদ (মোবাইল) বা কার্ডে দিন। রসিদ এখানেই পাবেন।</p> : null
  }
  const chosen = gateway ?? data.gateways[0]

  return (
    <Card className="p-4">
      {!open ? (
        <Button className="w-full" onClick={() => (setOpen(true), setGateway(data.gateways[0]))}>
          <CreditCard className="size-4" /> অনলাইনে পরিশোধ করুন
        </Button>
      ) : (
        <div className="space-y-3">
          <h2 className="font-semibold text-slate-900">অনলাইনে পরিশোধ</h2>
          <label className="block text-sm text-slate-700" htmlFor="op_amount">
            টাকার পরিমাণ (বকেয়া {bnTaka(data.due)})
          </label>
          <Input id="op_amount" type="number" inputMode="numeric" min={data.min_amount} max={data.due} value={amount} onChange={(e) => setAmount(e.target.value)} />
          <div className="grid gap-2">
            {data.gateways.map((g) => (
              <button
                key={g}
                type="button"
                onClick={() => setGateway(g)}
                className={cn('rounded-xl border p-3 text-left', chosen === g ? 'border-brand-500 bg-brand-50' : 'border-slate-200')}
              >
                <span className="block font-medium text-slate-900">{gatewayLabel[g]?.title ?? g}</span>
                <span className="block text-xs text-slate-500">{gatewayLabel[g]?.note}</span>
              </button>
            ))}
          </div>
          {start.isError && <Alert>{Object.values(validationErrors(start.error))[0] ?? errorMessage(start.error)}</Alert>}
          <Button className="w-full" loading={start.isPending} disabled={!Number(amount)} onClick={() => (setGateway(chosen), start.mutate())}>
            {bnTaka(Number(amount) || 0)} পরিশোধ করুন
          </Button>
          <button type="button" onClick={() => setOpen(false)} className="w-full text-center text-sm text-slate-500">
            বাতিল
          </button>
          <p className="text-xs text-slate-500">পরিশোধের পর এই পাতায় ফিরে আসবেন; রসিদ নিজে থেকেই তৈরি হবে।</p>
        </div>
      )}
    </Card>
  )
}

/** Shown when the parent comes back from the gateway (?online=<tran_id>). */
export function OnlineResult() {
  const qc = useQueryClient()
  const [params] = useSearchParams()
  const tran = params.get('online')
  const { data } = useQuery({
    queryKey: ['portal', 'online-status', tran],
    queryFn: async () => {
      const d = (await api.get<{ data: { status: string; amount: number; receipt_no: string | null; payment_id: number | null } }>(`/portal/online-payments/${tran}`)).data.data
      if (d.status === 'paid') qc.invalidateQueries({ queryKey: ['portal'] })
      return d
    },
    enabled: !!tran,
    // The gateway's confirmation can arrive a few seconds after the parent returns.
    refetchInterval: (q) => (q.state.data?.status === 'initiated' ? 3000 : false),
  })
  if (!tran || !data) return null

  if (data.status === 'paid') {
    return (
      <div className="flex items-start gap-3 rounded-xl bg-brand-50 p-4 text-brand-800">
        <CheckCircle2 className="mt-0.5 size-5 shrink-0" />
        <div className="text-sm">
          <p className="font-semibold">{bnTaka(data.amount)} পরিশোধ সফল হয়েছে। ধন্যবাদ!</p>
          {data.payment_id && (
            <a href={portalPdf.receipt(data.payment_id)} target="_blank" rel="noreferrer" className="underline">
              রসিদ {data.receipt_no} দেখুন
            </a>
          )}
        </div>
      </div>
    )
  }
  if (data.status === 'initiated' || data.status === 'review') {
    return (
      <div className="flex items-start gap-3 rounded-xl bg-amber-50 p-4 text-sm text-amber-900">
        <Clock className="mt-0.5 size-5 shrink-0" />
        {data.status === 'initiated' ? 'পেমেন্ট যাচাই করা হচ্ছে…' : 'পেমেন্টটি যাচাই করা হচ্ছে। টাকা কেটে থাকলে রিসেপশন থেকে নিশ্চিত করে জানানো হবে।'}
      </div>
    )
  }

  return (
    <div className="flex items-start gap-3 rounded-xl bg-red-50 p-4 text-sm text-red-800">
      <XCircle className="mt-0.5 size-5 shrink-0" />
      {data.status === 'cancelled' ? 'পেমেন্ট বাতিল করা হয়েছে। কোনো টাকা কাটা হয়নি।' : 'পেমেন্ট সম্পন্ন হয়নি। আবার চেষ্টা করুন বা রিসেপশনে যোগাযোগ করুন।'}
    </div>
  )
}
