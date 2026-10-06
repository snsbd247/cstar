import { zodResolver } from '@hookform/resolvers/zod'
import { Eye, EyeOff } from 'lucide-react'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { Navigate, useLocation, useNavigate } from 'react-router'
import { z } from 'zod'
import { errorMessage, validationErrors } from '../../api/client'
import { Logo } from '../../components/shared/Logo'
import { Button } from '../../components/ui/Button'
import { Alert } from '../../components/ui/Card'
import { Field, Input } from '../../components/ui/Field'
import { FullPageSpinner } from '../../components/ui/Spinner'
import { useAuth } from '../../contexts/useAuth'

const schema = z.object({
  login: z.string().trim().min(1, 'Enter your email or mobile number'),
  password: z.string().min(1, 'Enter your password'),
  remember: z.boolean(),
})
type FormValues = z.infer<typeof schema>

export default function LoginPage() {
  const { user, isLoading, login } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const [error, setError] = useState<string | null>(null)
  const [showPassword, setShowPassword] = useState(false)
  const { register, handleSubmit, formState } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { login: '', password: '', remember: true },
  })

  if (isLoading) return <FullPageSpinner />
  if (user) return <Navigate to={user.home_path} replace />

  const onSubmit = async (values: FormValues) => {
    setError(null)
    try {
      const me = await login(values.login, values.password, values.remember)
      const from = (location.state as { from?: string } | null)?.from
      navigate(from && from !== '/login' ? from : me.home_path, { replace: true })
    } catch (e) {
      setError(validationErrors(e).login ?? errorMessage(e))
    }
  }

  return (
    <div className="flex min-h-screen flex-col bg-gradient-to-br from-brand-50 via-white to-sky-brand-50 lg:flex-row">
      <aside className="hidden flex-1 flex-col justify-between bg-gradient-to-br from-brand-700 to-sky-brand-700 p-12 text-white lg:flex">
        <Logo className="[&_span]:text-white" />
        <div className="max-w-md space-y-4">
          <h2 className="text-3xl font-semibold leading-tight">Every child grows at their own pace.</h2>
          <p className="text-white/80">
            Training, therapy, progress and billing for every C-STAR child — in one place for our team and families.
          </p>
        </div>
        <p className="text-sm text-white/60">Center for Speech Therapy &amp; Autism Rehabilitation, Bangladesh</p>
      </aside>

      <main className="flex flex-1 items-center justify-center px-4 py-10 sm:px-6">
        <div className="w-full max-w-sm">
          <Logo className="mb-8 lg:hidden" />
          <h1 className="text-2xl font-semibold text-slate-900">Sign in</h1>
          <p className="mt-1 text-sm text-slate-500">Staff: email or mobile · Parents: mobile number</p>
          <p className="font-bn mt-0.5 text-sm text-slate-500">অভিভাবকগণ মোবাইল নম্বর দিয়ে লগইন করুন</p>

          <form onSubmit={handleSubmit(onSubmit)} className="mt-8 space-y-5" noValidate>
            {!error && new URLSearchParams(location.search).has('expired') && (
              <Alert>You were signed out after a period of inactivity. Please sign in again. · নিষ্ক্রিয়তার কারণে লগআউট হয়েছে, আবার লগইন করুন।</Alert>
            )}
            {error && <Alert>{error}</Alert>}

            <Field label="Email or mobile number" htmlFor="login" error={formState.errors.login?.message}>
              <Input id="login" autoComplete="username" inputMode="email" autoFocus invalid={!!formState.errors.login} {...register('login')} />
            </Field>

            <Field label="Password" htmlFor="password" error={formState.errors.password?.message}>
              <div className="relative">
                <Input
                  id="password"
                  type={showPassword ? 'text' : 'password'}
                  autoComplete="current-password"
                  className="pr-10"
                  invalid={!!formState.errors.password}
                  {...register('password')}
                />
                <button
                  type="button"
                  onClick={() => setShowPassword((v) => !v)}
                  className="absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 hover:text-slate-600"
                  aria-label={showPassword ? 'Hide password' : 'Show password'}
                >
                  {showPassword ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                </button>
              </div>
            </Field>

            <label className="flex items-center gap-2 text-sm text-slate-600">
              <input type="checkbox" className="size-4 rounded border-slate-300 text-brand-600" {...register('remember')} />
              Keep me signed in on this device
            </label>

            <Button type="submit" className="w-full" loading={formState.isSubmitting}>
              Sign in
            </Button>
          </form>
        </div>
      </main>
    </div>
  )
}
