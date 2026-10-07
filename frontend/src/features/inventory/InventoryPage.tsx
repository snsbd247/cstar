import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { AlertTriangle, Plus } from 'lucide-react'
import { useState } from 'react'
import { useSearchParams } from 'react-router'
import { api, errorMessage, validationErrors } from '../../api/client'
import { Button } from '../../components/ui/Button'
import { Alert, Badge, Card, PageHeader } from '../../components/ui/Card'
import { Field, Input, Select } from '../../components/ui/Field'
import { Modal } from '../../components/ui/Modal'
import { Spinner } from '../../components/ui/Spinner'
import { useAuth } from '../../contexts/useAuth'
import { taka } from '../billing/api'
import { useBranches } from '../branches/api'

interface Item {
  id: number
  branch_id: number
  branch: string
  name: string
  category: string
  unit: string
  stock: number
  reorder_level: number
  unit_cost: number | null
  is_active: boolean
  notes: string | null
  low: boolean
}
interface Movement {
  id: number
  date: string
  type: 'in' | 'out' | 'adjust'
  quantity: number
  balance_after: number
  unit_cost: number | null
  reference: string | null
  note: string | null
  by: string | null
}

const qty = (n: number) => (Number.isInteger(n) ? String(n) : n.toFixed(2))

/** Inventory (Sprint 20): materials and supplies per branch — stock in / out, counts, low-stock alerts. */
export default function InventoryPage() {
  const { can } = useAuth()
  const [params, setParams] = useSearchParams()
  const low = params.get('low') === '1'
  const [q, setQ] = useState('')
  const [category, setCategory] = useState('')
  const [editing, setEditing] = useState<Item | 'new' | null>(null)
  const [moving, setMoving] = useState<Item | null>(null)
  const [card, setCard] = useState<Item | null>(null)
  const { data, isLoading } = useQuery({
    queryKey: ['inventory', low, q, category],
    queryFn: async () =>
      (await api.get<{ data: Item[]; categories: Record<string, string>; totals: { items: number; low: number; value: number } }>('/inventory', { params: { low: low ? 1 : undefined, q: q || undefined, category: category || undefined } })).data,
  })
  const canEdit = can('inventory.manage')

  return (
    <>
      <PageHeader
        title={low ? 'Low Stock' : 'Stock Items'}
        description="Therapy materials and supplies per branch. Record what comes in and what is used; purchase costs are still entered under Expenses or Vendor bills."
        actions={
          canEdit && (
            <Button onClick={() => setEditing('new')}>
              <Plus className="size-4" /> Add item
            </Button>
          )
        }
      />
      {data && (
        <div className="mb-4 grid grid-cols-3 gap-3">
          <Card className="p-3">
            <p className="text-xs text-slate-500">Items</p>
            <p className="text-xl font-semibold text-slate-900">{data.totals.items}</p>
          </Card>
          <button onClick={() => setParams(low ? {} : { low: '1' })} className="text-left">
            <Card className="p-3 hover:border-amber-300">
              <p className="text-xs text-slate-500">{low ? 'Show all items' : 'At or below reorder level'}</p>
              <p className="text-xl font-semibold text-amber-700">{data.totals.low}</p>
            </Card>
          </button>
          <Card className="p-3">
            <p className="text-xs text-slate-500">Stock value (at last cost)</p>
            <p className="text-xl font-semibold text-slate-900">{taka(data.totals.value)}</p>
          </Card>
        </div>
      )}
      <Card className="mb-4 flex flex-wrap gap-3 p-3">
        <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search items…" className="sm:w-64" aria-label="Search" />
        <Select value={category} onChange={(e) => setCategory(e.target.value)} className="sm:w-52" aria-label="Category">
          <option value="">All categories</option>
          {Object.entries(data?.categories ?? {}).map(([k, l]) => (
            <option key={k} value={k}>
              {l}
            </option>
          ))}
        </Select>
      </Card>
      <Card className="overflow-x-auto">
        {isLoading ? (
          <Spinner className="m-5 text-brand-600" />
        ) : !data?.data.length ? (
          <p className="p-5 text-sm text-slate-500">{low ? 'Nothing is running low.' : 'No items yet.'}</p>
        ) : (
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-slate-100 text-left text-xs text-slate-500">
                <th className="px-4 py-2 font-medium">Item</th>
                <th className="px-4 py-2 font-medium">Branch</th>
                <th className="px-4 py-2 text-right font-medium">In stock</th>
                <th className="px-4 py-2 text-right font-medium">Reorder at</th>
                <th className="px-4 py-2 text-right font-medium">Last cost</th>
                <th className="px-4 py-2" />
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-50">
              {data.data.map((i) => (
                <tr key={i.id}>
                  <td className="px-4 py-2">
                    <button onClick={() => setCard(i)} className="font-medium text-slate-900 hover:text-brand-700">
                      {i.name}
                    </button>
                    <p className="text-xs text-slate-500">{data.categories[i.category]}</p>
                  </td>
                  <td className="px-4 py-2 text-slate-600">{i.branch}</td>
                  <td className="px-4 py-2 text-right">
                    <span className={i.low ? 'font-semibold text-amber-700' : 'text-slate-900'}>
                      {i.low && <AlertTriangle className="mr-1 inline size-3.5" />}
                      {qty(i.stock)} {i.unit}
                    </span>
                  </td>
                  <td className="px-4 py-2 text-right text-slate-500">{i.reorder_level ? qty(i.reorder_level) : '—'}</td>
                  <td className="px-4 py-2 text-right text-slate-500">{i.unit_cost !== null ? taka(i.unit_cost) : '—'}</td>
                  <td className="whitespace-nowrap px-4 py-2 text-right">
                    {canEdit && (
                      <>
                        <Button variant="secondary" className="min-h-8 px-3 text-xs" onClick={() => setMoving(i)}>
                          Stock in / out
                        </Button>
                        <Button variant="ghost" className="min-h-8 px-2 text-xs" onClick={() => setEditing(i)}>
                          Edit
                        </Button>
                      </>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </Card>
      {editing && <ItemForm item={editing === 'new' ? null : editing} categories={data?.categories ?? {}} onClose={() => setEditing(null)} />}
      {moving && <MoveForm item={moving} onClose={() => setMoving(null)} />}
      {card && <StockCard item={card} onClose={() => setCard(null)} />}
    </>
  )
}

function ItemForm({ item, categories, onClose }: { item: Item | null; categories: Record<string, string>; onClose: () => void }) {
  const qc = useQueryClient()
  const { user } = useAuth()
  const { data: branches } = useBranches()
  const [v, setV] = useState({
    branch_id: String(item?.branch_id ?? user?.branches?.[0]?.id ?? ''),
    name: item?.name ?? '',
    category: item?.category ?? 'therapy_material',
    unit: item?.unit ?? 'pcs',
    reorder_level: String(item?.reorder_level ?? 0),
    unit_cost: item?.unit_cost != null ? String(item.unit_cost) : '',
    opening_stock: '',
    notes: item?.notes ?? '',
    is_active: item?.is_active ?? true,
  })
  const [errors, setErrors] = useState<Record<string, string>>({})
  const save = useMutation({
    mutationFn: () => {
      const body = { ...v, branch_id: Number(v.branch_id), unit_cost: v.unit_cost || null, opening_stock: v.opening_stock || null }
      return item ? api.put(`/inventory/${item.id}`, body) : api.post('/inventory', body)
    },
    onSuccess: () => (qc.invalidateQueries({ queryKey: ['inventory'] }), onClose()),
    onError: (e) => setErrors(validationErrors(e)),
  })

  return (
    <Modal open title={item ? `Edit ${item.name}` : 'Add stock item'} onClose={onClose}>
      <div className="space-y-3">
        {save.isError && !Object.keys(errors).length && <Alert>{errorMessage(save.error)}</Alert>}
        <div className="grid gap-3 sm:grid-cols-2">
          <Field label="Name" htmlFor="inv_name" error={errors.name}>
            <Input id="inv_name" value={v.name} onChange={(e) => setV({ ...v, name: e.target.value })} />
          </Field>
          {!item && (
            <Field label="Branch" htmlFor="inv_branch">
              <Select id="inv_branch" value={v.branch_id} onChange={(e) => setV({ ...v, branch_id: e.target.value })}>
                {branches?.map((b) => (
                  <option key={b.id} value={b.id}>
                    {b.name}
                  </option>
                ))}
              </Select>
            </Field>
          )}
          <Field label="Category" htmlFor="inv_cat">
            <Select id="inv_cat" value={v.category} onChange={(e) => setV({ ...v, category: e.target.value })}>
              {Object.entries(categories).map(([k, l]) => (
                <option key={k} value={k}>
                  {l}
                </option>
              ))}
            </Select>
          </Field>
          <Field label="Unit" htmlFor="inv_unit" hint="pcs, box, pack, ream…" error={errors.unit}>
            <Input id="inv_unit" value={v.unit} onChange={(e) => setV({ ...v, unit: e.target.value })} />
          </Field>
          <Field label="Reorder level" htmlFor="inv_reorder" hint="Alert when stock falls to this; 0 = no alert" error={errors.reorder_level}>
            <Input id="inv_reorder" type="number" min="0" value={v.reorder_level} onChange={(e) => setV({ ...v, reorder_level: e.target.value })} />
          </Field>
          <Field label="Unit cost (৳)" htmlFor="inv_cost" error={errors.unit_cost}>
            <Input id="inv_cost" type="number" min="0" value={v.unit_cost} onChange={(e) => setV({ ...v, unit_cost: e.target.value })} />
          </Field>
          {!item && (
            <Field label="Opening stock" htmlFor="inv_open" error={errors.opening_stock}>
              <Input id="inv_open" type="number" min="0" value={v.opening_stock} onChange={(e) => setV({ ...v, opening_stock: e.target.value })} />
            </Field>
          )}
        </div>
        <Field label="Notes" htmlFor="inv_notes">
          <Input id="inv_notes" value={v.notes} onChange={(e) => setV({ ...v, notes: e.target.value })} />
        </Field>
        {item && (
          <label className="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" className="size-4 accent-brand-600" checked={v.is_active} onChange={(e) => setV({ ...v, is_active: e.target.checked })} /> In use (untick to hide the item)
          </label>
        )}
        <div className="flex justify-end gap-2">
          <Button variant="ghost" onClick={onClose}>
            Cancel
          </Button>
          <Button loading={save.isPending} onClick={() => save.mutate()}>
            Save
          </Button>
        </div>
      </div>
    </Modal>
  )
}

function MoveForm({ item, onClose }: { item: Item; onClose: () => void }) {
  const qc = useQueryClient()
  const [v, setV] = useState({ type: 'out', quantity: '', unit_cost: '', reference: '', note: '' })
  const [errors, setErrors] = useState<Record<string, string>>({})
  const save = useMutation({
    mutationFn: () => api.post(`/inventory/${item.id}/movements`, { ...v, unit_cost: v.type === 'in' && v.unit_cost ? v.unit_cost : null }),
    onSuccess: () => (qc.invalidateQueries({ queryKey: ['inventory'] }), onClose()),
    onError: (e) => setErrors(validationErrors(e)),
  })

  return (
    <Modal open title={`${item.name} — ${qty(item.stock)} ${item.unit} in stock`} onClose={onClose}>
      <div className="space-y-3">
        {save.isError && !Object.keys(errors).length && <Alert>{errorMessage(save.error)}</Alert>}
        <div className="grid grid-cols-3 gap-1.5">
          {[
            ['out', 'Used / issued'],
            ['in', 'Received'],
            ['adjust', 'Physical count'],
          ].map(([k, l]) => (
            <Button key={k} variant={v.type === k ? 'primary' : 'secondary'} className="text-xs" onClick={() => setV({ ...v, type: k })}>
              {l}
            </Button>
          ))}
        </div>
        <div className="grid gap-3 sm:grid-cols-2">
          <Field label={v.type === 'adjust' ? `Counted (${item.unit})` : `Quantity (${item.unit})`} htmlFor="mv_qty" error={errors.quantity}>
            <Input id="mv_qty" type="number" min="0" value={v.quantity} onChange={(e) => setV({ ...v, quantity: e.target.value })} />
          </Field>
          {v.type === 'in' && (
            <Field label="Unit cost (৳)" htmlFor="mv_cost">
              <Input id="mv_cost" type="number" min="0" value={v.unit_cost} onChange={(e) => setV({ ...v, unit_cost: e.target.value })} />
            </Field>
          )}
          <Field label={v.type === 'in' ? 'Bill / supplier' : v.type === 'out' ? 'Given to / used for' : 'Reference'} htmlFor="mv_ref">
            <Input id="mv_ref" value={v.reference} onChange={(e) => setV({ ...v, reference: e.target.value })} />
          </Field>
        </div>
        <Field label="Note" htmlFor="mv_note">
          <Input id="mv_note" value={v.note} onChange={(e) => setV({ ...v, note: e.target.value })} />
        </Field>
        <div className="flex justify-end gap-2">
          <Button variant="ghost" onClick={onClose}>
            Cancel
          </Button>
          <Button loading={save.isPending} onClick={() => save.mutate()}>
            Save
          </Button>
        </div>
      </div>
    </Modal>
  )
}

function StockCard({ item, onClose }: { item: Item; onClose: () => void }) {
  const { data, isLoading } = useQuery({
    queryKey: ['inventory', 'card', item.id],
    queryFn: async () => (await api.get<{ data: Movement[] }>(`/inventory/${item.id}/movements`)).data.data,
  })
  const tone = { in: 'green', out: 'amber', adjust: 'gray' } as const

  return (
    <Modal open title={`Stock card — ${item.name}`} onClose={onClose}>
      {isLoading ? (
        <Spinner className="text-brand-600" />
      ) : !data?.length ? (
        <p className="text-sm text-slate-500">No movements yet.</p>
      ) : (
        <ul className="max-h-[60vh] divide-y divide-slate-100 overflow-y-auto text-sm">
          {data.map((m) => (
            <li key={m.id} className="flex items-start justify-between gap-3 py-2">
              <div>
                <p>
                  <Badge tone={tone[m.type]}>{m.type}</Badge> <span className="text-slate-500">{m.date}</span>
                </p>
                <p className="text-xs text-slate-500">{[m.reference, m.note, m.by].filter(Boolean).join(' · ')}</p>
              </div>
              <div className="text-right">
                <p className={m.quantity < 0 ? 'text-red-600' : 'text-brand-700'}>
                  {m.quantity > 0 ? '+' : ''}
                  {qty(m.quantity)}
                </p>
                <p className="text-xs text-slate-500">
                  bal. {qty(m.balance_after)} {item.unit}
                </p>
              </div>
            </li>
          ))}
        </ul>
      )}
    </Modal>
  )
}
