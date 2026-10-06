import { KeyRound, Pencil, Phone, Plus, Star, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { errorMessage, validationErrors } from '../../../api/client'
import { Button } from '../../../components/ui/Button'
import { Alert, Badge, Card } from '../../../components/ui/Card'
import { Field, Input, Select } from '../../../components/ui/Field'
import { Modal } from '../../../components/ui/Modal'
import { useGuardianMutations } from '../api'
import { relationships, type Guardian, type PatientDetail } from '../types'

export function GuardiansTab({ patient }: { patient: PatientDetail }) {
  const [editing, setEditing] = useState<Guardian | 'new' | null>(null)
  const [portalFor, setPortalFor] = useState<Guardian | null>(null)
  const { remove } = useGuardianMutations(patient.id)
  const [error, setError] = useState<string | null>(null)
  const canManage = patient.can.manage_guardians

  return (
    <div className="space-y-3">
      {error && <Alert>{error}</Alert>}
      {canManage && (
        <div className="flex justify-end">
          <Button onClick={() => setEditing('new')}>
            <Plus className="size-4" /> Add guardian
          </Button>
        </div>
      )}
      <div className="grid gap-3 md:grid-cols-2">
        {patient.guardians.map((g) => (
          <Card key={g.id} className="p-4">
            <div className="flex items-start justify-between gap-2">
              <div>
                <p className="flex items-center gap-1.5 font-medium text-slate-900">
                  {g.name} {g.is_primary && <Star className="size-3.5 fill-amber-400 text-amber-400" aria-label="Primary guardian" />}
                </p>
                <p className="text-sm capitalize text-slate-500">{g.relationship}</p>
              </div>
              <div className="flex flex-wrap justify-end gap-1">
                {g.is_emergency_contact && <Badge tone="red">Emergency</Badge>}
                {g.has_portal_account ? <Badge tone="green">Portal account</Badge> : g.can_access_portal && <Badge>Portal allowed</Badge>}
              </div>
            </div>
            <a href={`tel:${g.phone}`} className="mt-2 inline-flex items-center gap-1 text-sm text-sky-brand-600">
              <Phone className="size-3.5" /> {g.phone}
            </a>
            {g.occupation && <p className="text-sm text-slate-500">{g.occupation}</p>}
            {canManage && (
              <div className="mt-3 flex flex-wrap gap-1 border-t border-slate-100 pt-3">
                <Button variant="secondary" className="min-h-8 px-2.5 py-1 text-xs" onClick={() => setEditing(g)}>
                  <Pencil className="size-3.5" /> Edit
                </Button>
                {!g.has_portal_account && g.can_access_portal && (
                  <Button variant="secondary" className="min-h-8 px-2.5 py-1 text-xs" onClick={() => setPortalFor(g)}>
                    <KeyRound className="size-3.5" /> Create portal login
                  </Button>
                )}
                {patient.guardians.length > 1 && (
                  <Button
                    variant="ghost"
                    className="min-h-8 px-2.5 py-1 text-xs text-red-600"
                    onClick={async () => {
                      if (!confirm(`Remove ${g.name} from ${patient.name}?`)) return
                      try {
                        await remove.mutateAsync(g.id)
                      } catch (e) {
                        setError(errorMessage(e))
                      }
                    }}
                  >
                    <Trash2 className="size-3.5" /> Remove
                  </Button>
                )}
              </div>
            )}
          </Card>
        ))}
      </div>

      {editing && <GuardianForm patientId={patient.id} guardian={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />}
      {portalFor && <PortalAccountForm patientId={patient.id} guardian={portalFor} onClose={() => setPortalFor(null)} />}
    </div>
  )
}

interface GuardianValues {
  name: string
  phone: string
  email: string
  occupation: string
  relationship: string
  is_primary: boolean
  is_emergency_contact: boolean
  can_access_portal: boolean
}

function GuardianForm({ patientId, guardian, onClose }: { patientId: number; guardian: Guardian | null; onClose: () => void }) {
  const { add, update } = useGuardianMutations(patientId)
  const [error, setError] = useState<string | null>(null)
  const { register, handleSubmit, setError: setFieldError, formState } = useForm<GuardianValues>({
    defaultValues: {
      name: guardian?.name ?? '',
      phone: guardian?.phone ?? '',
      email: guardian?.email ?? '',
      occupation: guardian?.occupation ?? '',
      relationship: guardian?.relationship ?? 'father',
      is_primary: guardian?.is_primary ?? false,
      is_emergency_contact: guardian?.is_emergency_contact ?? false,
      can_access_portal: guardian?.can_access_portal ?? true,
    },
  })
  const errors = formState.errors

  const onSubmit = async (v: GuardianValues) => {
    setError(null)
    try {
      if (guardian) await update.mutateAsync({ id: guardian.id, ...v })
      else await add.mutateAsync({ ...v })
      onClose()
    } catch (e) {
      const fields = validationErrors(e)
      Object.entries(fields).forEach(([name, message]) => setFieldError(name as keyof GuardianValues, { message }))
      if (!Object.keys(fields).length) setError(errorMessage(e))
    }
  }

  return (
    <Modal open title={guardian ? `Edit ${guardian.name}` : 'Add guardian'} onClose={onClose}>
      <form onSubmit={handleSubmit(onSubmit)} className="space-y-4" noValidate>
        {error && <Alert>{error}</Alert>}
        <div className="grid gap-3 sm:grid-cols-2">
          <Field label="Name" htmlFor="g_name" error={errors.name?.message}>
            <Input id="g_name" {...register('name')} />
          </Field>
          <Field label="Mobile" htmlFor="g_phone" error={errors.phone?.message}>
            <Input id="g_phone" inputMode="tel" {...register('phone')} />
          </Field>
          <Field label="Relationship" htmlFor="g_relationship" error={errors.relationship?.message}>
            <Select id="g_relationship" className="capitalize" {...register('relationship')}>
              {relationships.map((r) => (
                <option key={r} value={r}>
                  {r}
                </option>
              ))}
            </Select>
          </Field>
          <Field label="Occupation" htmlFor="g_occupation">
            <Input id="g_occupation" {...register('occupation')} />
          </Field>
          <Field label="Email" htmlFor="g_email" error={errors.email?.message}>
            <Input id="g_email" type="email" {...register('email')} />
          </Field>
        </div>
        <div className="space-y-2 text-sm text-slate-700">
          <label className="flex items-center gap-2">
            <input type="checkbox" className="size-4" {...register('is_primary')} /> Primary guardian
          </label>
          <label className="flex items-center gap-2">
            <input type="checkbox" className="size-4" {...register('is_emergency_contact')} /> Emergency contact
          </label>
          <label className="flex items-center gap-2">
            <input type="checkbox" className="size-4" {...register('can_access_portal')} /> May see this child in the parent portal
          </label>
        </div>
        <div className="flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" loading={formState.isSubmitting}>
            Save
          </Button>
        </div>
      </form>
    </Modal>
  )
}

function PortalAccountForm({ patientId, guardian, onClose }: { patientId: number; guardian: Guardian; onClose: () => void }) {
  const { portalAccount } = useGuardianMutations(patientId)
  const [password, setPassword] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [done, setDone] = useState(false)

  const submit = async () => {
    setError(null)
    try {
      await portalAccount.mutateAsync({ id: guardian.id, password })
      setDone(true)
    } catch (e) {
      setError(Object.values(validationErrors(e))[0] ?? errorMessage(e))
    }
  }

  return (
    <Modal open title="Parent portal login" onClose={onClose}>
      {done ? (
        <div className="space-y-4">
          <Alert tone="green">
            Account created. {guardian.name} can sign in with mobile <b>{guardian.phone}</b> and the password you set, and will be asked to change it.
          </Alert>
          <div className="flex justify-end">
            <Button onClick={onClose}>Done</Button>
          </div>
        </div>
      ) : (
        <div className="space-y-4">
          {error && <Alert>{error}</Alert>}
          <p className="text-sm text-slate-600">
            Login: <b>{guardian.phone}</b>
          </p>
          <Field label="Temporary password" hint="At least 8 characters with letters and numbers. Share it with the parent.">
            <Input value={password} onChange={(e) => setPassword(e.target.value)} aria-label="Temporary password" />
          </Field>
          <div className="flex justify-end gap-2">
            <Button variant="secondary" onClick={onClose}>
              Cancel
            </Button>
            <Button loading={portalAccount.isPending} disabled={password.length < 8} onClick={submit}>
              Create login
            </Button>
          </div>
        </div>
      )}
    </Modal>
  )
}
