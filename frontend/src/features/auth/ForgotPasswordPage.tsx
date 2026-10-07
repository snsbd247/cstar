import { useState } from 'react'
import { Link, useNavigate } from 'react-router'
import { api, errorMessage, validationErrors } from '../../api/client'
import { Logo } from '../../components/shared/Logo'
import { Button } from '../../components/ui/Button'
import { Alert } from '../../components/ui/Card'
import { Field, Input } from '../../components/ui/Field'

/** Sprint 22 (decision D5): reset a forgotten password with a code sent by SMS to the account's mobile number. */
export default function ForgotPasswordPage() {
  const navigate = useNavigate()
  const [step, setStep] = useState<'phone' | 'code'>('phone')
  const [phone, setPhone] = useState('')
  const [code, setCode] = useState('')
  const [password, setPassword] = useState('')
  const [confirm, setConfirm] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [sent, setSent] = useState<string | null>(null)

  const sendCode = async () => {
    setBusy(true)
    setError(null)
    try {
      const { data } = await api.post<{ message: string }>('/auth/forgot', { phone })
      setSent(data.message)
      setStep('code')
    } catch (e) {
      setError(validationErrors(e).phone ?? errorMessage(e))
    } finally {
      setBusy(false)
    }
  }

  const reset = async () => {
    setBusy(true)
    setError(null)
    setErrors({})
    try {
      await api.post('/auth/reset', { phone, code, password, password_confirmation: confirm })
      navigate('/login?reset=1', { replace: true })
    } catch (e) {
      const v = validationErrors(e)
      setErrors(v)
      if (!Object.keys(v).length) setError(errorMessage(e))
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-gradient-to-br from-brand-50 via-white to-sky-brand-50 px-4 py-10">
      <div className="w-full max-w-sm">
        <Logo className="mb-8" />
        <h1 className="text-2xl font-semibold text-slate-900">Forgot password</h1>
        <p className="font-bn mt-0.5 text-sm text-slate-500">পাসওয়ার্ড ভুলে গেছেন? মোবাইলে একটি কোড পাঠানো হবে।</p>

        <div className="mt-8 space-y-5">
          {error && <Alert>{error}</Alert>}
          {step === 'phone' ? (
            <>
              <Field label="Mobile number · মোবাইল নম্বর" htmlFor="fp_phone">
                <Input id="fp_phone" inputMode="tel" autoComplete="tel" autoFocus value={phone} onChange={(e) => setPhone(e.target.value)} placeholder="01XXXXXXXXX" />
              </Field>
              <Button className="w-full" loading={busy} disabled={phone.trim().length < 11} onClick={sendCode}>
                Send code · কোড পাঠান
              </Button>
            </>
          ) : (
            <>
              {sent && (
                <Alert tone="green">
                  {sent} <span className="font-bn">নম্বরটি নিবন্ধিত থাকলে SMS-এ ৬ সংখ্যার কোড গেছে।</span>
                </Alert>
              )}
              <Field label="Code from SMS · SMS-এর কোড" htmlFor="fp_code" error={errors.code}>
                <Input id="fp_code" inputMode="numeric" autoComplete="one-time-code" maxLength={6} value={code} onChange={(e) => setCode(e.target.value.replace(/\D/g, ''))} />
              </Field>
              <Field label="New password · নতুন পাসওয়ার্ড" htmlFor="fp_pw" error={errors.password} hint="Letters and numbers, at least 8 · অক্ষর ও সংখ্যা, অন্তত ৮টি">
                <Input id="fp_pw" type="password" autoComplete="new-password" value={password} onChange={(e) => setPassword(e.target.value)} />
              </Field>
              <Field label="Repeat new password · আবার লিখুন" htmlFor="fp_pw2">
                <Input id="fp_pw2" type="password" autoComplete="new-password" value={confirm} onChange={(e) => setConfirm(e.target.value)} />
              </Field>
              <Button className="w-full" loading={busy} disabled={code.length !== 6 || !password} onClick={reset}>
                Set new password · পাসওয়ার্ড বদলান
              </Button>
              <button type="button" onClick={() => (setStep('phone'), setCode(''))} className="w-full text-center text-sm text-slate-500 hover:text-brand-700">
                Send a new code · নতুন কোড
              </button>
            </>
          )}
          <Link to="/login" className="block text-center text-sm text-brand-700 hover:underline">
            Back to sign in · লগইনে ফিরে যান
          </Link>
        </div>
      </div>
    </div>
  )
}
