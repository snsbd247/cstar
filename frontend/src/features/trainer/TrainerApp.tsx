import { CalendarOff, ClipboardCheck, Clock, NotebookPen } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router'
import { Alert, Card } from '../../components/ui/Card'
import { Spinner } from '../../components/ui/Spinner'
import { useAuth } from '../../contexts/useAuth'
import { longDate } from '../../utils/format'
import { useTrainerToday } from '../training/api'
import { CheckInCard } from '../staff/StaffAttendance'

/** Plan §১০/§৩৭: Login → Today's classes → Attendance → Student → Training record → Save. */
export function TrainerToday() {
  const { user } = useAuth()
  const [today] = useState(() => longDate(new Date()))
  const { data, isLoading, error } = useTrainerToday()

  return (
    <div className="space-y-4">
      <div>
        <p className="text-sm text-slate-500">{today}</p>
        <h1 className="text-xl font-semibold text-slate-900">Good day, {data?.trainer.name ?? user?.name}</h1>
      </div>

      <CheckInCard />
      {error && <Alert>This login is not linked to a trainer profile yet. Ask the branch admin.</Alert>}
      {isLoading && <Spinner className="text-brand-600" />}

      {data && (
        <>
          <div className="grid grid-cols-4 gap-2">
            {[
              ['Students', data.totals.students, 'text-slate-900'],
              ['Present', data.totals.present, 'text-brand-700'],
              ['Absent', data.totals.absent, 'text-red-600'],
              ['Notes due', data.totals.pending_records, 'text-amber-600'],
            ].map(([label, value, color]) => (
              <Card key={label as string} className="p-3 text-center">
                <p className={`text-2xl font-semibold ${color}`}>{value}</p>
                <p className="text-[11px] text-slate-500">{label}</p>
              </Card>
            ))}
          </div>

          <h2 className="pt-2 font-semibold text-slate-900">Today's classes</h2>
          {data.classes.length === 0 && (
            <Card className="flex items-center gap-3 p-5 text-sm text-slate-500">
              <CalendarOff className="size-5" /> No class scheduled for you today.
            </Card>
          )}
          {data.classes.map((c) => (
            <Card key={c.id} className="p-4">
              <div className="flex items-start justify-between gap-2">
                <div>
                  <p className="font-semibold text-slate-900">{c.name}</p>
                  <p className="flex items-center gap-1 text-sm text-slate-500">
                    <Clock className="size-3.5" /> {c.start_time} – {c.end_time}
                  </p>
                </div>
                {c.is_holiday ? (
                  <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">Holiday</span>
                ) : c.marked < c.students ? (
                  <span className="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700">Attendance due</span>
                ) : (
                  <span className="rounded-full bg-brand-50 px-2.5 py-1 text-xs font-medium text-brand-700">
                    {c.present}/{c.students} present
                  </span>
                )}
              </div>
              <div className="mt-3 grid grid-cols-2 gap-2">
                <Link to={`/trainer/attendance?class=${c.id}`} className="flex min-h-11 items-center justify-center gap-2 rounded-lg bg-brand-600 text-sm font-semibold text-white">
                  <ClipboardCheck className="size-4" /> Attendance
                </Link>
                <Link to={`/trainer/records?class=${c.id}`} className="flex min-h-11 items-center justify-center gap-2 rounded-lg border border-slate-300 text-sm font-semibold text-slate-700">
                  <NotebookPen className="size-4" /> Records {c.pending_records > 0 && <span className="rounded-full bg-amber-500 px-1.5 text-xs text-white">{c.pending_records}</span>}
                </Link>
              </div>
            </Card>
          ))}

          {data.other_classes.length > 0 && (
            <p className="text-sm text-slate-500">Not today: {data.other_classes.map((c) => c.name).join(', ')}</p>
          )}
        </>
      )}
    </div>
  )
}
