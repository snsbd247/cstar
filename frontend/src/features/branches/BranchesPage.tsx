import { MapPin, Pencil, Phone, Plus } from 'lucide-react'
import { useState } from 'react'
import { useSearchParams } from 'react-router'
import { useForm } from 'react-hook-form'
import { errorMessage, validationErrors } from '../../api/client'
import { Button } from '../../components/ui/Button'
import { Alert, Badge, Card, PageHeader } from '../../components/ui/Card'
import { Field, Input } from '../../components/ui/Field'
import { Modal } from '../../components/ui/Modal'
import { Spinner } from '../../components/ui/Spinner'
import { useAuth } from '../../contexts/useAuth'
import type { Branch } from '../../types'
import { useBranches, useSaveBranch, type BranchInput } from './api'

export default function BranchesPage() {
  const { user, can } = useAuth()
  const { data: branches, isLoading } = useBranches()
  const [params, setParams] = useSearchParams()
  const [picked, setPicked] = useState<Branch | 'new' | null>(null)
  // The menu's "Add" link opens the form through ?new=1.
  const editing = picked ?? (params.get('new') && can('branches.manage') ? 'new' : null)
  const setEditing = (row: Branch | 'new' | null) => {
    setPicked(row)
    if (!row && params.has('new')) setParams({}, { replace: true })
  }

  return (
    <>
      <PageHeader
        title="Branches"
        description="C-STAR centers. Every patient, enrollment and transaction belongs to a branch."
        actions={
          user?.is_super_admin && (
            <Button onClick={() => setEditing('new')}>
              <Plus className="size-4" /> New branch
            </Button>
          )
        }
      />

      {isLoading ? (
        <Spinner className="text-brand-600" />
      ) : (
        <div className="grid gap-4 md:grid-cols-2">
          {branches?.map((branch) => (
            <Card key={branch.id} className="p-5">
              <div className="flex items-start justify-between gap-3">
                <div>
                  <div className="flex items-center gap-2">
                    <h2 className="font-semibold text-slate-900">{branch.name}</h2>
                    <Badge tone="blue">{branch.code}</Badge>
                  </div>
                  {branch.name_bn && <p className="font-bn text-sm text-slate-500">{branch.name_bn}</p>}
                </div>
                <Badge tone={branch.is_active ? 'green' : 'gray'}>{branch.is_active ? 'Active' : 'Inactive'}</Badge>
              </div>
              <dl className="mt-4 space-y-1.5 text-sm text-slate-600">
                {branch.address && (
                  <div className="flex gap-2">
                    <MapPin className="mt-0.5 size-4 shrink-0 text-slate-400" /> {branch.address}
                  </div>
                )}
                {branch.phone && (
                  <div className="flex gap-2">
                    <Phone className="mt-0.5 size-4 shrink-0 text-slate-400" /> {branch.phone}
                  </div>
                )}
              </dl>
              <div className="mt-4 flex items-center justify-between border-t border-slate-100 pt-3 text-sm text-slate-500">
                <span>{branch.users_count ?? 0} staff &amp; parent accounts</span>
                {can('branches.manage') && (
                  <Button variant="ghost" onClick={() => setEditing(branch)}>
                    <Pencil className="size-4" /> Edit
                  </Button>
                )}
              </div>
            </Card>
          ))}
        </div>
      )}

      {editing && <BranchForm branch={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />}
    </>
  )
}

function BranchForm({ branch, onClose }: { branch: Branch | null; onClose: () => void }) {
  const save = useSaveBranch()
  const [error, setError] = useState<string | null>(null)
  const { register, handleSubmit, setError: setFieldError, formState } = useForm<BranchInput>({
    defaultValues: {
      code: branch?.code ?? '',
      name: branch?.name ?? '',
      name_bn: branch?.name_bn ?? '',
      address: branch?.address ?? '',
      phone: branch?.phone ?? '',
      email: branch?.email ?? '',
      map_url: branch?.map_url ?? '',
      is_active: branch?.is_active ?? true,
      show_on_website: branch?.show_on_website ?? true,
    },
  })
  const errors = formState.errors

  const onSubmit = async (values: BranchInput) => {
    setError(null)
    try {
      await save.mutateAsync({ ...values, id: branch?.id })
      onClose()
    } catch (e) {
      const fields = validationErrors(e)
      Object.entries(fields).forEach(([name, message]) => setFieldError(name as keyof BranchInput, { message }))
      if (!Object.keys(fields).length) setError(errorMessage(e))
    }
  }

  return (
    <Modal open title={branch ? `Edit ${branch.name}` : 'New branch'} onClose={onClose}>
      <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
        {error && <Alert>{error}</Alert>}
        <div className="grid grid-cols-3 gap-3">
          <Field label="Code" htmlFor="code" error={errors.code?.message}>
            <Input id="code" placeholder="DHK" className="uppercase" {...register('code', { required: 'Required' })} />
          </Field>
          <div className="col-span-2">
            <Field label="Name" htmlFor="name" error={errors.name?.message}>
              <Input id="name" placeholder="Dhaka Branch" {...register('name', { required: 'Required' })} />
            </Field>
          </div>
        </div>
        <Field label="Name (Bangla)" htmlFor="name_bn" error={errors.name_bn?.message}>
          <Input id="name_bn" className="font-bn" placeholder="ঢাকা শাখা" {...register('name_bn')} />
        </Field>
        <Field label="Address" htmlFor="address" error={errors.address?.message}>
          <Input id="address" {...register('address')} />
        </Field>
        <div className="grid gap-3 sm:grid-cols-2">
          <Field label="Phone" htmlFor="phone" error={errors.phone?.message}>
            <Input id="phone" inputMode="tel" {...register('phone')} />
          </Field>
          <Field label="Email" htmlFor="email" error={errors.email?.message}>
            <Input id="email" type="email" {...register('email')} />
          </Field>
        </div>
        <Field label="Google Maps link" htmlFor="map_url" error={errors.map_url?.message}>
          <Input id="map_url" type="url" {...register('map_url')} />
        </Field>
        <div className="flex flex-wrap gap-5 text-sm text-slate-700">
          <label className="flex items-center gap-2">
            <input type="checkbox" className="size-4" {...register('is_active')} /> Active
          </label>
          <label className="flex items-center gap-2">
            <input type="checkbox" className="size-4" {...register('show_on_website')} /> Show on website
          </label>
        </div>
        <div className="flex justify-end gap-2 pt-2">
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
