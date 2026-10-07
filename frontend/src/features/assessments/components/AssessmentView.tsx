import { FileDown, Share2 } from 'lucide-react'
import { errorMessage } from '../../../api/client'
import { Button } from '../../../components/ui/Button'
import { AmendmentsPanel } from '../../../components/shared/AmendmentsPanel'
import { Alert, Badge, Card } from '../../../components/ui/Card'
import { ReviewHistory } from '../../therapist/ClinicalReviewPage'
import { assessmentPdfUrl, priorityTone, useShareAssessment, type Assessment } from '../api'

/** Read-only assessment report (the same content as the PDF). */
export function AssessmentView({ assessment: a, canShare }: { assessment: Assessment; canShare?: boolean }) {
  const share = useShareAssessment()
  const findings = a.type?.sections.filter((s) => a.section_findings[s.key]) ?? []

  const block = (label: string, value: string | null) =>
    value && (
      <div>
        <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</p>
        <p className="mt-1 whitespace-pre-line text-sm text-slate-800">{value}</p>
      </div>
    )

  return (
    <>
      <Card className="space-y-5 p-5">
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <p className="text-lg font-semibold text-slate-900">{a.type?.name}</p>
            <p className="text-sm text-slate-500">
              {a.assessment_code} · {a.date} · {a.therapist?.name}
              {a.patient && ` · ${a.patient.name}`}
            </p>
            <div className="mt-2 flex gap-2">
              <Badge tone={a.status === 'final' ? 'green' : 'amber'}>{a.status}</Badge>
              {a.shared_with_parent && <Badge tone="blue">Shared with family</Badge>}
            </div>
          </div>
          <div className="flex flex-wrap gap-2">
            <a href={assessmentPdfUrl(a.id)} target="_blank" rel="noreferrer">
              <Button variant="secondary">
                <FileDown className="size-4" /> PDF
              </Button>
            </a>
            {canShare && a.status === 'final' && (
              <Button variant="secondary" loading={share.isPending} onClick={() => share.mutate({ id: a.id, shared: !a.shared_with_parent })}>
                <Share2 className="size-4" /> {a.shared_with_parent ? 'Stop sharing' : 'Share with family'}
              </Button>
            )}
          </div>
        </div>
        {share.isError && <Alert>{errorMessage(share.error)}</Alert>}

        {block('Reason for assessment', a.chief_complaint)}
        {block('Background & history', a.background)}
        {findings.length > 0 && (
          <div className="space-y-3 rounded-lg bg-slate-50 p-4">
            {findings.map((s) => block(s.label, a.section_findings[s.key]))}
          </div>
        )}
        {block('Summary', a.summary)}
        {!!a.recommendation_items?.length && (
          <div>
            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Recommended programmes</p>
            <ul className="mt-2 space-y-1.5">
              {a.recommendation_items.map((r) => (
                <li key={r.id} className="flex flex-wrap items-center gap-2 text-sm text-slate-800">
                  <span className="font-medium">{r.enrollment_type === 'training' ? 'Regular Training' : r.service?.name}</span>
                  {r.frequency && <span className="text-slate-500">· {r.frequency}</span>}
                  <Badge tone={priorityTone[r.priority]}>{r.priority}</Badge>
                  {r.enrollment && <Badge tone="green">Enrolled {r.enrollment.enrollment_code}</Badge>}
                </li>
              ))}
            </ul>
          </div>
        )}
        {block('Recommendations', a.recommendations)}
        {a.parent_summary && (
          <div className="rounded-lg bg-brand-50 p-3">
            <p className="text-xs font-semibold text-brand-800">For the family</p>
            <p className="mt-1 whitespace-pre-line font-bn text-sm text-brand-900">{a.parent_summary}</p>
          </div>
        )}
      </Card>
      {a.status === 'final' && <ReviewHistory type="assessment" id={a.id} />}
      {a.status === 'final' && (
        <AmendmentsPanel
          url={`/assessments/${a.id}/amendments`}
          fields={[
            ['chief_complaint', 'Reason for assessment'],
            ['background', 'Background & history'],
            ...(a.type?.sections ?? []).map((s) => [`section_findings.${s.key}`, s.label] as const),
            ['summary', 'Summary'],
            ['recommendations', 'Recommendations'],
            ['parent_summary', 'For the family'],
          ]}
          current={{
            chief_complaint: a.chief_complaint,
            background: a.background,
            summary: a.summary,
            recommendations: a.recommendations,
            parent_summary: a.parent_summary,
            ...Object.fromEntries(Object.entries(a.section_findings ?? {}).map(([k, v]) => [`section_findings.${k}`, v])),
          }}
          canAmend={!!a.can_amend}
          refresh={[['assessment', a.id], ['assessments']]}
        />
      )}
    </>
  )
}
