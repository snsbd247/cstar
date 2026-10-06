import { Download, FileText, Lock, Trash2, Upload } from 'lucide-react'
import { useState, type FormEvent } from 'react'
import { errorMessage, validationErrors } from '../../../api/client'
import { Button } from '../../../components/ui/Button'
import { Alert, Badge, Card } from '../../../components/ui/Card'
import { Field, Input, Select } from '../../../components/ui/Field'
import { Spinner } from '../../../components/ui/Spinner'
import { useDeleteDocument, useDocuments, useUploadDocument } from '../api'
import { documentCategories, type PatientDetail } from '../types'

const clinical = ['medical_report', 'prescription', 'previous_assessment']

export function DocumentsTab({ patient }: { patient: PatientDetail }) {
  const { data: documents, isLoading } = useDocuments(patient.id)
  const upload = useUploadDocument(patient.id)
  const remove = useDeleteDocument(patient.id)
  const [error, setError] = useState<string | null>(null)
  const [formKey, setFormKey] = useState(0)

  const onSubmit = async (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault()
    setError(null)
    try {
      await upload.mutateAsync(new FormData(e.currentTarget))
      setFormKey((k) => k + 1)
    } catch (err) {
      setError(Object.values(validationErrors(err))[0] ?? errorMessage(err))
    }
  }

  return (
    <div className="grid gap-5 lg:grid-cols-[1fr_320px]">
      <Card className="overflow-hidden">
        {isLoading ? (
          <div className="p-5">
            <Spinner className="text-brand-600" />
          </div>
        ) : !documents?.length ? (
          <p className="p-5 text-sm text-slate-500">No documents yet.</p>
        ) : (
          <ul className="divide-y divide-slate-100">
            {documents.map((d) => (
              <li key={d.id} className="flex items-center gap-3 px-4 py-3">
                <FileText className="size-5 shrink-0 text-slate-400" />
                <div className="min-w-0 flex-1">
                  <p className="truncate text-sm font-medium text-slate-900">{d.title}</p>
                  <p className="truncate text-xs text-slate-500">
                    {documentCategories[d.category]} · {(d.size / 1024).toFixed(0)} KB · {new Date(d.created_at).toLocaleDateString('en-GB')}
                    {d.uploaded_by && ` · ${d.uploaded_by}`}
                  </p>
                </div>
                {clinical.includes(d.category) && (
                  <Badge tone="blue">
                    <Lock className="mr-1 size-3" /> Clinical
                  </Badge>
                )}
                <a href={`/api/v1/documents/${d.id}/download`} className="rounded-md p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-800" aria-label={`Download ${d.title}`}>
                  <Download className="size-4" />
                </a>
                {patient.can.update && (
                  <button
                    onClick={() => confirm(`Delete "${d.title}"?`) && remove.mutate(d.id)}
                    className="rounded-md p-2 text-slate-400 hover:bg-red-50 hover:text-red-600"
                    aria-label={`Delete ${d.title}`}
                  >
                    <Trash2 className="size-4" />
                  </button>
                )}
              </li>
            ))}
          </ul>
        )}
      </Card>

      {patient.can.update && (
        <Card className="h-fit p-5">
          <h2 className="mb-3 font-semibold text-slate-900">Upload document</h2>
          <form key={formKey} onSubmit={onSubmit} className="space-y-3">
            {error && <Alert>{error}</Alert>}
            <Field label="Title" htmlFor="doc_title">
              <Input id="doc_title" name="title" required />
            </Field>
            <Field label="Category" htmlFor="doc_category">
              <Select id="doc_category" name="category" defaultValue="medical_report">
                {Object.entries(documentCategories).map(([k, v]) => (
                  <option key={k} value={k}>
                    {v}
                  </option>
                ))}
              </Select>
            </Field>
            <Field label="File" htmlFor="doc_file" hint="PDF, image or Word · max 10 MB · stored privately">
              <input id="doc_file" name="file" type="file" required accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx" className="block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm" />
            </Field>
            <label className="flex items-center gap-2 text-sm text-slate-700">
              <input type="checkbox" name="visible_to_parent" value="1" className="size-4" /> Visible to parent
            </label>
            <Button type="submit" className="w-full" loading={upload.isPending}>
              <Upload className="size-4" /> Upload
            </Button>
          </form>
        </Card>
      )}
    </div>
  )
}
