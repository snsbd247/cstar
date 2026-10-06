import { zodResolver } from '@hookform/resolvers/zod'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { useNavigate } from 'react-router'
import { z } from 'zod'
import { api, errorMessage, validationErrors } from '../../api/client'
import { Logo } from '../../components/shared/Logo'
import { Button } from '../../components/ui/Button'
import { Alert, Card } from '../../components/ui/Card'
import { Field, Input } from '../../components/ui/Field'
import { useAuth } from '../../contexts/useAuth'

const schema = z
  .object({
    current_password: z.string().min(1, 'Required'),
    password: z.string().min(8, 'At least 8 characters').regex(/[A-Za-z]/, 'Include a letter').regex(/\d/, 'Include a number'),
    password_confirmation: z.string(),
  })
  .refine((v) => v.password === v.password_confirmation, { path: ['password_confirmation'], message: 'Passwords do not match' })
type FormValues = z.infer<typeof schema>

export default function ChangePasswordPage() {
  const { user, refresh } = useAuth()
  const navigate = useNavigate()
  const [error, setError] = useState<string | null>(null)
  const { register, handleSubmit, formState, setError: setFieldError } = useForm<FormValues>({ resolver: zodResolver(schema) })

  const onSubmit = async (values: FormValues) => {
    setError(null)
    try {
      await api.put('/auth/password', values)
      await refresh()
      navigate(user?.home_path ?? '/', { replace: true })
    } catch (e) {
      const fields = validationErrors(e)
      Object.entries(fields).forEach(([name, message]) => setFieldError(name as keyof FormValues, { message }))
      if (!Object.keys(fields).length) setError(errorMessage(e))
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center px-4 py-10">
      <Card className="w-full max-w-sm p-6">
        <Logo className="mb-6" />
        <h1 className="text-lg font-semibold">Change your password</h1>
        {user?.must_change_password && <p className="mt-1 text-sm text-slate-500">Please set a new password before continuing.</p>}
        <form onSubmit={handleSubmit(onSubmit)} className="mt-6 space-y-4" noValidate>
          {error && <Alert>{error}</Alert>}
          <Field label="Current password" htmlFor="current_password" error={formState.errors.current_password?.message}>
            <Input id="current_password" type="password" autoComplete="current-password" {...register('current_password')} />
          </Field>
          <Field label="New password" htmlFor="password" hint="At least 8 characters with letters and numbers" error={formState.errors.password?.message}>
            <Input id="password" type="password" autoComplete="new-password" {...register('password')} />
          </Field>
          <Field label="Confirm new password" htmlFor="password_confirmation" error={formState.errors.password_confirmation?.message}>
            <Input id="password_confirmation" type="password" autoComplete="new-password" {...register('password_confirmation')} />
          </Field>
          <Button type="submit" className="w-full" loading={formState.isSubmitting}>
            Save password
          </Button>
        </form>
      </Card>
    </div>
  )
}
