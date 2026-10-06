import { useQuery } from '@tanstack/react-query'
import { Lock, Plus, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { api, errorMessage, validationErrors } from '../../../api/client'
import { Button } from '../../../components/ui/Button'
import { Alert, Card } from '../../../components/ui/Card'
import { Field, Input, Select, Textarea } from '../../../components/ui/Field'
import { todayISO } from '../../../utils/format'
import { useAssessmentTypes, useSaveAssessment, type Assessment, type RecommendationItem } from '../api'

const textFields = [
  ['chief_complaint', 'Reason for assessment / chief complaint', 3],
  ['background', 'Background & history', 3],
] as const
const closingFields = [
  ['summary', 'Overall summary (required to finalize)', 4],
  ['recommendations', 'Recommendations (free text)', 3],
  ['parent_summary', 'Summary for the family (shown in the parent portal when shared)', 3],
] as const
type TextKey = (typeof textFields)[number][0] | (typeof closingFields)[number][0]

function useTherapyServices() {
  return useQuery({
    queryKey: ['therapy-services'],
    queryFn: async () =>
      (await api.get<{ data: { id: number; name: string; category: string }[] }>('/lookups/bookable-services')).data.data.filter((s) => s.category === 'therapy'),
    staleTime: Infinity,
  })
}

/**
 * Plan §১৯ assessment form: type → sectioned findings → summary → structured recommendations.
 * Recommendations become one-click "Enroll" suggestions for reception once the assessment is final.
 */
export function AssessmentForm({
  patientId,
  appointmentId,
  assessment,
  onSaved,
}: {
  patientId: number
  appointmentId?: number
  assessment?: Assessment
  onSaved?: (a: Assessment, finalized: boolean) => void
}) {
  const { data: types } = useAssessmentTypes()
  const { data: services } = useTherapyServices()
  const save = useSaveAssessment()
  const locked = !!assessment && !assessment.can_edit

  const [typeId, setTypeId] = useState<number | ''>(assessment?.type?.id ?? '')
  const [date, setDate] = useState(assessment?.date ?? todayISO())
  const [text, setText] = useState<Record<TextKey, string>>(() =>
    Object.fromEntries([...textFields, ...closingFields].map(([k]) => [k, assessment?.[k] ?? ''])) as Record<TextKey, string>,
  )
  const [findings, setFindings] = useState<Record<string, string>>(assessment?.section_findings ?? {})
  const [items, setItems] = useState<RecommendationItem[]>(
    () => assessment?.recommendation_items?.map((r) => ({ ...r, service_id: r.service?.id ?? null })) ?? [],
  )
  const [message, setMessage] = useState<{ tone: 'green' | 'red'; text: string } | null>(null)
  const type = types?.find((t) => t.id === typeId)

  const setItem = (i: number, patch: Partial<RecommendationItem>) => setItems((l) => l.map((x, j) => (j === i ? { ...x, ...patch } : x)))

  const submit = async (finalize: boolean) => {
    setMessage(null)
    if (finalize && !confirm('Finalize this assessment? It will be locked and its recommendations sent to reception.')) return
    try {
      const saved = await save.mutateAsync({
        id: assessment?.id,
        patientId,
        body: {
          assessment_type_id: typeId || null,
          appointment_id: assessment ? undefined : appointmentId,
          date,
          ...Object.fromEntries(Object.entries(text).map(([k, v]) => [k, v || null])),
          section_findings: Object.fromEntries(Object.entries(findings).filter(([, v]) => v.trim())),
          recommendation_items: items
            .filter((r) => !r.enrollment)
            .map((r) => ({ enrollment_type: r.enrollment_type, service_id: r.enrollment_type === 'therapy' ? r.service_id : null, frequency: r.frequency || null, priority: r.priority, note: r.note || null })),
          finalize,
        },
      })
      setMessage({ tone: 'green', text: finalize ? 'Assessment finalized.' : 'Draft saved.' })
      onSaved?.(saved, finalize)
    } catch (e) {
      setMessage({ tone: 'red', text: Object.values(validationErrors(e))[0] ?? errorMessage(e) })
    }
  }

  const area = (key: TextKey, label: string, rows: number) => (
    <Field key={key} label={label} htmlFor={key}>
      <Textarea id={key} rows={rows} disabled={locked} value={text[key]} onChange={(e) => setText((t) => ({ ...t, [key]: e.target.value }))} />
    </Field>
  )

  return (
    <Card className="space-y-5 p-5">
      {locked && (
        <Alert tone="green">
          <span className="inline-flex items-center gap-1.5">
            <Lock className="size-4" /> {assessment?.status === 'final' ? 'This assessment is final and locked.' : 'Read only — only the assessing therapist edits it.'}
          </span>
        </Alert>
      )}
      {message && <Alert tone={message.tone}>{message.text}</Alert>}

      <div className="grid gap-4 sm:grid-cols-[1fr_180px]">
        <Field label="Assessment type" htmlFor="assessment_type_id">
          <Select id="assessment_type_id" disabled={locked || !!assessment} value={typeId} onChange={(e) => setTypeId(Number(e.target.value) || '')}>
            <option value="">Choose…</option>
            {types?.map((t) => (
              <option key={t.id} value={t.id}>
                {t.name}
              </option>
            ))}
          </Select>
        </Field>
        <Field label="Date" htmlFor="assessment_date">
          <Input id="assessment_date" type="date" max={todayISO()} disabled={locked} value={date} onChange={(e) => setDate(e.target.value)} />
        </Field>
      </div>

      {textFields.map(([k, l, r]) => area(k, l, r))}

      {type && (
        <div className="space-y-3">
          <p className="text-sm font-semibold text-slate-900">Findings</p>
          {type.sections.map((s) => (
            <Field key={s.key} label={s.label} htmlFor={`f-${s.key}`}>
              <Textarea id={`f-${s.key}`} rows={2} disabled={locked} value={findings[s.key] ?? ''} onChange={(e) => setFindings((f) => ({ ...f, [s.key]: e.target.value }))} />
            </Field>
          ))}
        </div>
      )}

      {closingFields.map(([k, l, r]) => area(k, l, r))}

      <div>
        <div className="mb-2 flex items-center justify-between">
          <p className="text-sm font-semibold text-slate-900">Recommended programmes</p>
          {!locked && (
            <Button variant="ghost" type="button" onClick={() => setItems((l) => [...l, { enrollment_type: 'therapy', service_id: null, frequency: '', priority: 'normal', note: '' }])}>
              <Plus className="size-4" /> Add
            </Button>
          )}
        </div>
        {items.length === 0 && <p className="text-sm text-slate-500">None yet — add the therapy or training this child should start.</p>}
        <ul className="space-y-2">
          {items.map((r, i) => (
            <li key={r.id ?? `new-${i}`} className="grid gap-2 rounded-lg border border-slate-100 p-3 sm:grid-cols-[130px_1fr_140px_110px_auto] sm:items-center">
              <Select aria-label="Programme" disabled={locked || !!r.enrollment} value={r.enrollment_type} onChange={(e) => setItem(i, { enrollment_type: e.target.value as RecommendationItem['enrollment_type'] })}>
                <option value="therapy">Therapy</option>
                <option value="training">Regular Training</option>
              </Select>
              {r.enrollment_type === 'therapy' ? (
                <Select aria-label="Therapy service" disabled={locked || !!r.enrollment} value={r.service_id ?? ''} onChange={(e) => setItem(i, { service_id: Number(e.target.value) || null })}>
                  <option value="">Therapy service…</option>
                  {services?.map((s) => (
                    <option key={s.id} value={s.id}>
                      {s.name}
                    </option>
                  ))}
                </Select>
              ) : (
                <Input aria-label="Note" placeholder="Note (optional)" disabled={locked || !!r.enrollment} value={r.note ?? ''} onChange={(e) => setItem(i, { note: e.target.value })} />
              )}
              <Input aria-label="Frequency" placeholder="e.g. 2 / week" disabled={locked || !!r.enrollment} value={r.frequency ?? ''} onChange={(e) => setItem(i, { frequency: e.target.value })} />
              <Select aria-label="Priority" disabled={locked || !!r.enrollment} value={r.priority} onChange={(e) => setItem(i, { priority: e.target.value as RecommendationItem['priority'] })}>
                <option value="high">High</option>
                <option value="normal">Normal</option>
                <option value="low">Low</option>
              </Select>
              {r.enrollment ? (
                <span className="text-xs text-brand-700">Enrolled {r.enrollment.enrollment_code}</span>
              ) : (
                !locked && (
                  <button type="button" aria-label="Remove" onClick={() => setItems((l) => l.filter((_, j) => j !== i))} className="justify-self-end p-2 text-slate-400 hover:text-red-600">
                    <Trash2 className="size-4" />
                  </button>
                )
              )}
            </li>
          ))}
        </ul>
      </div>

      {!locked && (
        <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
          <Button variant="secondary" loading={save.isPending} disabled={!typeId} onClick={() => submit(false)}>
            Save draft
          </Button>
          <Button loading={save.isPending} disabled={!typeId} onClick={() => submit(true)}>
            Finalize
          </Button>
        </div>
      )}
    </Card>
  )
}
