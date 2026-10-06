import { ChevronLeft, ChevronRight } from 'lucide-react'
import { useState } from 'react'
import { Card } from '../../../components/ui/Card'
import { Spinner } from '../../../components/ui/Spinner'
import { cn } from '../../../utils/cn'
import { useStudentMonth } from '../api'
import { attendanceStyle } from '../types'

function shiftMonth(month: string, by: number) {
  const [y, m] = month.split('-').map(Number)
  const d = new Date(y, m - 1 + by, 1)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
}

/** One student's month (Plan §১২): a calendar of marks plus the attendance rate. */
export function MonthAttendance({ enrollmentId }: { enrollmentId: number }) {
  const [month, setMonth] = useState(() => new Date().toLocaleDateString('en-CA', { timeZone: 'Asia/Dhaka' }).slice(0, 7))
  const { data, isLoading } = useStudentMonth(enrollmentId, month)
  const [y, m] = month.split('-').map(Number)
  const first = new Date(y, m - 1, 1)
  const daysInMonth = new Date(y, m, 0).getDate()
  const byDate = Object.fromEntries((data?.days ?? []).map((d) => [d.date, d.status]))

  return (
    <Card className="p-5">
      <div className="flex items-center justify-between">
        <h3 className="font-semibold text-slate-900">Attendance</h3>
        <div className="flex items-center gap-1">
          <button onClick={() => setMonth(shiftMonth(month, -1))} className="rounded p-1.5 text-slate-500 hover:bg-slate-100" aria-label="Previous month">
            <ChevronLeft className="size-4" />
          </button>
          <span className="w-28 text-center text-sm font-medium text-slate-700">{first.toLocaleDateString('en-GB', { month: 'long', year: 'numeric' })}</span>
          <button onClick={() => setMonth(shiftMonth(month, 1))} className="rounded p-1.5 text-slate-500 hover:bg-slate-100" aria-label="Next month">
            <ChevronRight className="size-4" />
          </button>
        </div>
      </div>

      {isLoading || !data ? (
        <Spinner className="mt-4 text-brand-600" />
      ) : (
        <>
          <div className="mt-4 grid grid-cols-7 gap-1 text-center text-[11px] text-slate-400">
            {['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map((d) => (
              <span key={d}>{d}</span>
            ))}
            {Array.from({ length: first.getDay() }, (_, i) => (
              <span key={`b${i}`} />
            ))}
            {Array.from({ length: daysInMonth }, (_, i) => {
              const date = `${month}-${String(i + 1).padStart(2, '0')}`
              const status = byDate[date]
              return (
                <span
                  key={date}
                  title={status ? attendanceStyle[status].label : undefined}
                  className={cn('flex aspect-square items-center justify-center rounded-md text-xs font-medium', status ? attendanceStyle[status].className : 'bg-slate-50 text-slate-400')}
                >
                  {i + 1}
                </span>
              )
            })}
          </div>
          <div className="mt-4 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-slate-600">
            <span className="text-2xl font-semibold text-brand-700">{data.summary.rate === null ? '—' : `${data.summary.rate}%`}</span>
            <span>Present {data.summary.present}</span>
            <span>Late {data.summary.late}</span>
            <span>Absent {data.summary.absent}</span>
            <span>Leave {data.summary.leave}</span>
            <span>Holiday {data.summary.holiday}</span>
          </div>
          <p className="mt-1 text-xs text-slate-400">Rate = (present + late) ÷ (present + late + absent). Leave and holidays are not counted.</p>
        </>
      )}
    </Card>
  )
}
