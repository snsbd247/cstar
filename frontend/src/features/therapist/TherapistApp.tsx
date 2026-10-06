import { useState } from 'react'
import { Card } from '../../components/ui/Card'
import { useAuth } from '../../contexts/useAuth'
import { longDate } from '../../utils/format'

/** Plan §১৪: Login → Today's appointments → Patient → Session note → Save. Live from Sprint 8. */
export function TherapistToday() {
  const { user } = useAuth()
  const [today] = useState(() => longDate(new Date()))

  return (
    <div className="space-y-4">
      <div>
        <p className="text-sm text-slate-500">{today}</p>
        <h1 className="text-xl font-semibold text-slate-900">Good day, {user?.name}</h1>
      </div>
      <div className="grid grid-cols-3 gap-3">
        {['Appointments', 'Checked in', 'Notes to finalize'].map((label) => (
          <Card key={label} className="p-4 text-center">
            <p className="text-2xl font-semibold text-slate-300">—</p>
            <p className="text-xs text-slate-500">{label}</p>
          </Card>
        ))}
      </div>
      <Card className="p-5">
        <h2 className="font-medium text-slate-900">Today's appointments</h2>
        <p className="mt-1 text-sm text-slate-500">
          Your appointment list and the session note workflow will appear here (Sprint 8).
        </p>
      </Card>
    </div>
  )
}
