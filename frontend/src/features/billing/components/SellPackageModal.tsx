import { useState } from 'react'
import { errorMessage, validationErrors } from '../../../api/client'
import { Button } from '../../../components/ui/Button'
import { Alert } from '../../../components/ui/Card'
import { Field, Input, Select } from '../../../components/ui/Field'
import { Modal } from '../../../components/ui/Modal'
import { useAuth } from '../../../contexts/useAuth'
import { cn } from '../../../utils/cn'
import { taka, useBillingMutations, usePackages, type Invoice } from '../api'

/** Selling a package issues its invoice; sessions are then taken from it automatically as they happen. */
export function SellPackageModal({
  patient,
  therapyEnrollments,
  onClose,
  onSold,
}: {
  patient: { id: number; name: string; home_branch_id?: number | null }
  therapyEnrollments: { id: number; service_id: number; label: string }[]
  onClose: () => void
  onSold?: (invoice: Invoice) => void
}) {
  const { user, can } = useAuth()
  const { data: packages } = usePackages()
  const { sellPackage } = useBillingMutations()
  const branches = user?.branches ?? []
  const [packageId, setPackageId] = useState<number | null>(null)
  const [branchId, setBranchId] = useState<number>(patient.home_branch_id && branches.some((b) => b.id === patient.home_branch_id) ? patient.home_branch_id : (branches[0]?.id ?? 0))
  const [discount, setDiscount] = useState('')
  const [reason, setReason] = useState('')
  const [error, setError] = useState<string | null>(null)
  const chosen = packages?.find((p) => p.id === packageId)
  const enrollment = therapyEnrollments.find((e) => e.service_id === chosen?.service_id)

  const submit = async () => {
    setError(null)
    try {
      const invoice = await sellPackage.mutateAsync({
        patientId: patient.id,
        package_id: packageId,
        branch_id: branchId,
        enrollment_id: enrollment?.id ?? null,
        discount: Number(discount) || 0,
        discount_reason: reason || null,
      })
      onSold?.(invoice)
      onClose()
    } catch (e) {
      setError(Object.values(validationErrors(e))[0] ?? errorMessage(e))
    }
  }

  return (
    <Modal open title={`Sell package — ${patient.name}`} onClose={onClose}>
      <div className="space-y-4">
        {error && <Alert>{error}</Alert>}
        <ul className="space-y-2" role="radiogroup" aria-label="Package">
          {packages?.map((p) => (
            <li key={p.id}>
              <button
                type="button"
                role="radio"
                aria-checked={packageId === p.id}
                onClick={() => setPackageId(p.id)}
                className={cn('flex w-full items-center justify-between gap-3 rounded-xl border p-3 text-left', packageId === p.id ? 'border-brand-500 bg-brand-50 ring-2 ring-brand-500/20' : 'border-slate-200 hover:border-slate-300')}
              >
                <span>
                  <span className="block font-medium text-slate-900">{p.name}</span>
                  <span className="text-xs text-slate-500">
                    {p.sessions_count} sessions · valid {p.validity_days} days · {taka(p.per_session)}/session
                  </span>
                </span>
                <span className="font-semibold text-slate-900">{taka(p.price)}</span>
              </button>
            </li>
          ))}
          {packages?.length === 0 && <p className="text-sm text-slate-500">No packages set up yet.</p>}
        </ul>
        {chosen && (
          <p className="text-xs text-slate-500">
            {enrollment ? `Linked to ${enrollment.label}. ` : 'The child has no open enrollment for this therapy yet — the package still works for any appointment of this service. '}
            Sessions are deducted automatically when a therapist finalizes a session note.
          </p>
        )}
        <div className="grid gap-3 sm:grid-cols-2">
          <Field label="Branch" htmlFor="pkg_branch">
            <Select id="pkg_branch" value={branchId} onChange={(e) => setBranchId(Number(e.target.value))}>
              {branches.map((b) => (
                <option key={b.id} value={b.id}>
                  {b.name}
                </option>
              ))}
            </Select>
          </Field>
          {can('discounts.apply') && (
            <Field label="Discount (৳)" htmlFor="pkg_discount">
              <Input id="pkg_discount" type="number" min={0} value={discount} onChange={(e) => setDiscount(e.target.value)} />
            </Field>
          )}
        </div>
        {Number(discount) > 0 && (
          <Field label="Discount reason" htmlFor="pkg_reason">
            <Input id="pkg_reason" value={reason} onChange={(e) => setReason(e.target.value)} />
          </Field>
        )}
        <div className="flex justify-end gap-2">
          <Button variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button loading={sellPackage.isPending} disabled={!packageId || !branchId} onClick={submit}>
            Sell {chosen ? taka(chosen.price - (Number(discount) || 0)) : ''}
          </Button>
        </div>
      </div>
    </Modal>
  )
}
