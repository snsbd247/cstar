import { useMutation, useQuery, useQueryClient, type QueryKey } from '@tanstack/react-query'
import { History, PenLine } from 'lucide-react'
import { useState } from 'react'
import { api, errorMessage, validationErrors } from '../../api/client'
import { Button } from '../ui/Button'
import { Alert, Card } from '../ui/Card'
import { Field, Input, Select, Textarea } from '../ui/Field'
import { Modal } from '../ui/Modal'

interface Amendment {
  id: number
  field: string
  old_value: string | null
  new_value: string | null
  reason: string
  by: string | null
  at: string
}

/**
 * Sprint 22 (Plan §২১): a finalized clinical note is amended, never edited — the author picks a part, writes the
 * corrected text and the reason. The old text stays visible in the history below the note.
 */
export function AmendmentsPanel({
  url,
  fields,
  current,
  canAmend,
  refresh,
  onAmended,
}: {
  url: string
  fields: readonly (readonly [string, string])[]
  current: Record<string, string | null | undefined>
  canAmend: boolean
  refresh: QueryKey[]
  onAmended?: () => void
}) {
  const [open, setOpen] = useState(false)
  const { data } = useQuery({ queryKey: ['amendments', url], queryFn: async () => (await api.get<{ data: Amendment[] }>(url)).data.data })
  const label = (key: string) => fields.find(([k]) => k === key)?.[1] ?? key.replace('section_findings.', '')
  if (!canAmend && !data?.length) return null

  return (
    <Card className="space-y-3 p-4">
      <div className="flex items-center justify-between gap-2">
        <p className="flex items-center gap-1.5 text-sm font-semibold text-slate-900">
          <History className="size-4 text-slate-400" /> Amendments
        </p>
        {canAmend && (
          <Button variant="secondary" className="min-h-8 px-3 text-xs" onClick={() => setOpen(true)}>
            <PenLine className="size-3.5" /> Amend note
          </Button>
        )}
      </div>
      {!data?.length ? (
        <p className="text-xs text-slate-500">No amendments. A final note cannot be edited — use “Amend note” to correct it with a reason; the original text is kept.</p>
      ) : (
        <ul className="space-y-2 text-sm">
          {data.map((a) => (
            <li key={a.id} className="rounded-lg border border-slate-100 p-2">
              <p className="text-xs text-slate-500">
                {new Date(a.at).toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short' })} · {a.by} · <b className="text-slate-700">{label(a.field)}</b>
              </p>
              <p className="text-slate-700">Reason: {a.reason}</p>
              <details className="text-xs">
                <summary className="cursor-pointer text-brand-700">Show old and new text</summary>
                <p className="mt-1 whitespace-pre-line rounded bg-red-50 p-1.5 text-red-900 line-through decoration-red-300">{a.old_value || '(empty)'}</p>
                <p className="mt-1 whitespace-pre-line rounded bg-brand-50 p-1.5 text-brand-900">{a.new_value || '(empty)'}</p>
              </details>
            </li>
          ))}
        </ul>
      )}
      {open && <AmendModal url={url} fields={fields} current={current} refresh={refresh} onClose={() => setOpen(false)} onAmended={onAmended} />}
    </Card>
  )
}

function AmendModal({
  url,
  fields,
  current,
  refresh,
  onClose,
  onAmended,
}: {
  url: string
  fields: readonly (readonly [string, string])[]
  current: Record<string, string | null | undefined>
  refresh: QueryKey[]
  onClose: () => void
  onAmended?: () => void
}) {
  const qc = useQueryClient()
  const [field, setField] = useState(fields[0][0])
  const [value, setValue] = useState(current[fields[0][0]] ?? '')
  const [reason, setReason] = useState('')
  const [errors, setErrors] = useState<Record<string, string>>({})
  const save = useMutation({
    mutationFn: () => api.post(url, { field, value: value || null, reason }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['amendments', url] })
      refresh.forEach((key) => qc.invalidateQueries({ queryKey: key }))
      onClose()
      onAmended?.()
    },
    onError: (e) => setErrors(validationErrors(e)),
  })

  return (
    <Modal open title="Amend a final note" onClose={onClose}>
      <div className="space-y-3">
        {save.isError && !Object.keys(errors).length && <Alert>{errorMessage(save.error)}</Alert>}
        <Field label="Part of the note" htmlFor="am_field" error={errors.field}>
          <Select
            id="am_field"
            value={field}
            onChange={(e) => {
              setField(e.target.value)
              setValue(current[e.target.value] ?? '')
            }}
          >
            {fields.map(([k, l]) => (
              <option key={k} value={k}>
                {l}
              </option>
            ))}
          </Select>
        </Field>
        <Field label="Corrected text" htmlFor="am_value" error={errors.value}>
          <Textarea id="am_value" rows={5} value={value} onChange={(e) => setValue(e.target.value)} />
        </Field>
        <Field label="Reason for the amendment" htmlFor="am_reason" hint="e.g. wrong word, missing detail, written for the wrong child" error={errors.reason}>
          <Input id="am_reason" value={reason} onChange={(e) => setReason(e.target.value)} />
        </Field>
        <p className="text-xs text-slate-500">The old text, your name, the time and the reason are kept permanently.</p>
        <div className="flex justify-end gap-2">
          <Button variant="ghost" onClick={onClose}>
            Cancel
          </Button>
          <Button loading={save.isPending} onClick={() => save.mutate()}>
            Save amendment
          </Button>
        </div>
      </div>
    </Modal>
  )
}
