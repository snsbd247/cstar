import { Check, Plus, Trash2, Undo2, X } from 'lucide-react'
import { useState } from 'react'
import { useSearchParams } from 'react-router'
import { errorMessage, validationErrors } from '../../api/client'
import { Button } from '../../components/ui/Button'
import { Alert, Badge, Card, PageHeader } from '../../components/ui/Card'
import { Field, Input, Select } from '../../components/ui/Field'
import { Modal } from '../../components/ui/Modal'
import { Spinner } from '../../components/ui/Spinner'
import { useAuth } from '../../contexts/useAuth'
import { cn } from '../../utils/cn'
import { todayISO } from '../../utils/format'
import { taka } from '../billing/api'
import { useAccountsMutations, useChart, useVouchers, voucherStatusTone, voucherTypeLabel, type Voucher, type VoucherType } from './api'

/** Accounts §৪: PV / RV / JV / CV with maker-checker. Posted vouchers are reversed, never deleted. */
export default function VouchersPage() {
  const { can } = useAuth()
  const [params] = useSearchParams()
  const [status, setStatus] = useState(params.get('status') ?? '')
  const [type, setType] = useState(params.get('type') ?? '')
  const [source, setSource] = useState(params.get('source') ?? '')
  const [page, setPage] = useState(1)
  const { data, isLoading } = useVouchers({ status: status || undefined, type: type || undefined, source: source || undefined, page })
  const [open, setOpen] = useState<Voucher | 'new' | null>(null)

  return (
    <>
      <PageHeader
        title="Vouchers"
        description={data ? `Up to ${taka(data.approval_limit)} posts at once; above that a second person approves.` : undefined}
        actions={
          can('accounts.voucher.create') && (
            <Button onClick={() => setOpen('new')}>
              <Plus className="size-4" /> New voucher
            </Button>
          )
        }
      />
      <Card className="mb-4 flex flex-col gap-2 p-3 sm:flex-row">
        <Select value={status} onChange={(e) => (setStatus(e.target.value), setPage(1))} className="sm:w-48" aria-label="Status">
          <option value="">All statuses</option>
          <option value="submitted">Waiting approval</option>
          <option value="posted">Posted</option>
          <option value="draft">Draft</option>
          <option value="rejected">Rejected</option>
          <option value="reversed">Reversed</option>
        </Select>
        <Select value={type} onChange={(e) => (setType(e.target.value), setPage(1))} className="sm:w-48" aria-label="Type">
          <option value="">All types</option>
          {Object.entries(voucherTypeLabel).map(([k, l]) => (
            <option key={k} value={k}>
              {l}
            </option>
          ))}
        </Select>
        <Select value={source} onChange={(e) => (setSource(e.target.value), setPage(1))} className="sm:w-48" aria-label="Source">
          <option value="">All sources</option>
          <option value="expense">From expenses</option>
          <option value="manual">Written by hand</option>
        </Select>
      </Card>
      {isLoading ? (
        <Spinner className="text-brand-600" />
      ) : !data?.data.length ? (
        <Card className="p-5 text-sm text-slate-500">No vouchers.</Card>
      ) : (
        <Card className="divide-y divide-slate-100">
          {data.data.map((v) => (
            <button key={v.id} onClick={() => setOpen(v)} className="flex w-full flex-wrap items-center justify-between gap-2 px-4 py-3 text-left hover:bg-slate-50">
              <div className="min-w-0">
                <p className="font-medium text-slate-900">
                  {v.voucher_no} <span className="font-normal text-slate-500">· {v.date}</span>
                </p>
                <p className="truncate text-sm text-slate-600">{v.narration}</p>
                <p className="text-xs text-slate-500">
                  by {v.prepared_by?.name}
                  {v.approved_by && v.status === 'posted' && ` · approved by ${v.approved_by.name}`}
                </p>
              </div>
              <div className="flex items-center gap-3">
                <Badge tone={voucherStatusTone[v.status]}>{v.status === 'submitted' ? 'waiting approval' : v.status}</Badge>
                <span className="w-28 text-right font-semibold">{taka(v.amount)}</span>
              </div>
            </button>
          ))}
          {data.meta.last_page > 1 && (
            <div className="flex items-center justify-between px-4 py-2 text-sm">
              <span className="text-slate-500">
                Page {data.meta.current_page} of {data.meta.last_page}
              </span>
              <div className="flex gap-2">
                <Button variant="secondary" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
                  Previous
                </Button>
                <Button variant="secondary" disabled={page >= data.meta.last_page} onClick={() => setPage((p) => p + 1)}>
                  Next
                </Button>
              </div>
            </div>
          )}
        </Card>
      )}
      {open === 'new' && <VoucherForm onClose={() => setOpen(null)} />}
      {open && open !== 'new' && (open.can.edit ? <VoucherForm voucher={open} onClose={() => setOpen(null)} /> : <VoucherView voucher={open} onClose={() => setOpen(null)} />)}
    </>
  )
}

type Line = { key: number; account_id: number | ''; debit: string; credit: string; memo: string }

function VoucherForm({ voucher, onClose }: { voucher?: Voucher; onClose: () => void }) {
  const { user } = useAuth()
  const { data: chart } = useChart()
  const { saveVoucher, deleteVoucher } = useAccountsMutations()
  const accounts = chart?.data.filter((a) => !a.is_group && a.is_active) ?? []
  const branches = user?.branches ?? []
  const [type, setType] = useState<VoucherType>(voucher?.type ?? 'payment')
  const [date, setDate] = useState(voucher?.date ?? todayISO())
  const [branchId, setBranchId] = useState(voucher?.branch?.id ?? branches[0]?.id ?? 0)
  const [narration, setNarration] = useState(voucher?.narration ?? '')
  const [lines, setLines] = useState<Line[]>(
    () =>
      voucher?.lines.map((l, i) => ({ key: i, account_id: l.account_id, debit: l.debit ? String(l.debit) : '', credit: l.credit ? String(l.credit) : '', memo: l.memo ?? '' })) ?? [
        { key: 1, account_id: '', debit: '', credit: '', memo: '' },
        { key: 2, account_id: '', debit: '', credit: '', memo: '' },
      ],
  )
  const [error, setError] = useState<string | null>(null)
  const dr = lines.reduce((s, l) => s + (Number(l.debit) || 0), 0)
  const cr = lines.reduce((s, l) => s + (Number(l.credit) || 0), 0)
  const balanced = dr > 0 && Math.abs(dr - cr) < 0.005
  const set = (key: number, patch: Partial<Line>) => setLines((ls) => ls.map((l) => (l.key === key ? { ...l, ...patch } : l)))

  const save = async (submit: boolean) => {
    setError(null)
    try {
      await saveVoucher.mutateAsync({
        id: voucher?.id,
        ...(voucher ? {} : { type }),
        date,
        branch_id: branchId,
        narration,
        lines: lines.filter((l) => l.account_id).map((l) => ({ account_id: l.account_id, debit: Number(l.debit) || 0, credit: Number(l.credit) || 0, memo: l.memo || null })),
        submit,
      })
      onClose()
    } catch (e) {
      setError(Object.values(validationErrors(e))[0] ?? errorMessage(e))
    }
  }

  return (
    <Modal open wide title={voucher ? `Edit ${voucher.voucher_no}` : 'New voucher'} onClose={onClose}>
      <div className="space-y-4">
        {error && <Alert>{error}</Alert>}
        {voucher?.status === 'rejected' && <Alert>Rejected: {voucher.reject_reason}</Alert>}
        <div className="grid gap-3 sm:grid-cols-3">
          <Field label="Type" htmlFor="v_type">
            <Select id="v_type" value={type} disabled={!!voucher} onChange={(e) => setType(e.target.value as VoucherType)}>
              {Object.entries(voucherTypeLabel).map(([k, l]) => (
                <option key={k} value={k}>
                  {l}
                </option>
              ))}
            </Select>
          </Field>
          <Field label="Date" htmlFor="v_date">
            <Input id="v_date" type="date" max={todayISO()} value={date} onChange={(e) => setDate(e.target.value)} />
          </Field>
          <Field label="Branch" htmlFor="v_branch">
            <Select id="v_branch" value={branchId} onChange={(e) => setBranchId(Number(e.target.value))}>
              {branches.map((b) => (
                <option key={b.id} value={b.id}>
                  {b.name}
                </option>
              ))}
            </Select>
          </Field>
        </div>
        <Field label="Narration" htmlFor="v_narration">
          <Input id="v_narration" value={narration} onChange={(e) => setNarration(e.target.value)} placeholder="e.g. Cash deposited to bank" />
        </Field>

        <div className="space-y-2">
          <div className="hidden grid-cols-[1fr_110px_110px_1fr_32px] gap-2 px-1 text-xs font-medium text-slate-500 sm:grid">
            <span>Account</span>
            <span>Debit</span>
            <span>Credit</span>
            <span>Memo</span>
            <span />
          </div>
          {lines.map((l) => (
            <div key={l.key} className="grid grid-cols-2 gap-2 rounded-lg border border-slate-100 p-2 sm:grid-cols-[1fr_110px_110px_1fr_32px] sm:border-0 sm:p-0">
              <Select aria-label="Account" className="col-span-2 sm:col-span-1" value={l.account_id} onChange={(e) => set(l.key, { account_id: Number(e.target.value) || '' })}>
                <option value="">Account…</option>
                {accounts.map((a) => (
                  <option key={a.id} value={a.id}>
                    {a.code} {a.name}
                  </option>
                ))}
              </Select>
              <Input aria-label="Debit" type="number" min={0} value={l.debit} onChange={(e) => set(l.key, { debit: e.target.value, credit: e.target.value ? '' : l.credit })} />
              <Input aria-label="Credit" type="number" min={0} value={l.credit} onChange={(e) => set(l.key, { credit: e.target.value, debit: e.target.value ? '' : l.debit })} />
              <Input aria-label="Memo" value={l.memo} onChange={(e) => set(l.key, { memo: e.target.value })} />
              <button type="button" aria-label="Remove line" disabled={lines.length <= 2} onClick={() => setLines((ls) => ls.filter((x) => x.key !== l.key))} className="justify-self-end p-2 text-slate-400 hover:text-red-600 disabled:opacity-30">
                <Trash2 className="size-4" />
              </button>
            </div>
          ))}
          <Button variant="ghost" type="button" onClick={() => setLines((ls) => [...ls, { key: Date.now(), account_id: '', debit: '', credit: '', memo: '' }])}>
            <Plus className="size-4" /> Add line
          </Button>
        </div>

        <div className={cn('flex justify-between rounded-lg p-3 text-sm font-medium', balanced ? 'bg-brand-50 text-brand-800' : 'bg-red-50 text-red-700')}>
          <span>Debit {taka(dr)}</span>
          <span>{balanced ? 'Balanced' : `Difference ${taka(Math.abs(dr - cr))}`}</span>
          <span>Credit {taka(cr)}</span>
        </div>

        <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-between">
          <div>
            {voucher && (
              <Button variant="ghost" onClick={() => confirm('Delete this draft?') && deleteVoucher.mutate(voucher.id, { onSuccess: onClose })}>
                <Trash2 className="size-4" /> Delete draft
              </Button>
            )}
          </div>
          <div className="flex gap-2">
            <Button variant="secondary" loading={saveVoucher.isPending} disabled={!balanced || !narration} onClick={() => save(false)}>
              Save draft
            </Button>
            <Button loading={saveVoucher.isPending} disabled={!balanced || !narration} onClick={() => save(true)}>
              Submit
            </Button>
          </div>
        </div>
      </div>
    </Modal>
  )
}

function VoucherView({ voucher: v, onClose }: { voucher: Voucher; onClose: () => void }) {
  const { voucherAction } = useAccountsMutations()
  const act = (action: 'approve' | 'reject' | 'reverse') => {
    const reason = action === 'approve' ? undefined : prompt(action === 'reject' ? 'Why is it rejected?' : 'Why reverse this voucher?') ?? ''
    if (action !== 'approve' && (!reason || reason.trim().length < 5)) return
    voucherAction.mutate({ id: v.id, action, reason }, { onSuccess: onClose })
  }

  return (
    <Modal open wide title={`${v.voucher_no} — ${voucherTypeLabel[v.type]}`} onClose={onClose}>
      <div className="space-y-4">
        {voucherAction.isError && <Alert>{errorMessage(voucherAction.error)}</Alert>}
        <div className="flex flex-wrap items-center gap-2 text-sm text-slate-600">
          <Badge tone={voucherStatusTone[v.status]}>{v.status === 'submitted' ? 'waiting approval' : v.status}</Badge>
          {v.date} · {v.branch?.name} · prepared by {v.prepared_by?.name}
          {v.approved_by && ` · ${v.status === 'rejected' ? 'rejected' : 'approved'} by ${v.approved_by.name}`}
        </div>
        <p className="text-slate-800">{v.narration}</p>
        {v.reject_reason && <Alert>Rejected: {v.reject_reason}</Alert>}
        <table className="w-full text-sm">
          <thead className="text-left text-xs text-slate-500">
            <tr>
              <th className="py-1 font-medium">Account</th>
              <th className="py-1 text-right font-medium">Debit</th>
              <th className="py-1 text-right font-medium">Credit</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {v.lines.map((l, i) => (
              <tr key={i}>
                <td className={cn('py-1.5', l.credit ? 'pl-6' : '')}>
                  <span className="text-slate-400">{l.account?.code}</span> {l.account?.name}
                  {l.memo && <span className="block text-xs text-slate-500">{l.memo}</span>}
                </td>
                <td className="py-1.5 text-right">{l.debit ? taka(l.debit) : ''}</td>
                <td className="py-1.5 text-right">{l.credit ? taka(l.credit) : ''}</td>
              </tr>
            ))}
          </tbody>
        </table>
        <div className="flex flex-wrap justify-end gap-2">
          {v.can.approve && (
            <>
              <Button variant="secondary" onClick={() => act('reject')} disabled={voucherAction.isPending}>
                <X className="size-4" /> Reject
              </Button>
              <Button onClick={() => act('approve')} loading={voucherAction.isPending}>
                <Check className="size-4" /> Approve & post
              </Button>
            </>
          )}
          {v.can.reverse && (
            <Button variant="secondary" onClick={() => act('reverse')} disabled={voucherAction.isPending}>
              <Undo2 className="size-4" /> Reverse
            </Button>
          )}
          <Button variant="ghost" onClick={onClose}>
            Close
          </Button>
        </div>
      </div>
    </Modal>
  )
}
