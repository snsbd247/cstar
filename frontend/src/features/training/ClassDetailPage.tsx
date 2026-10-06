import { ArrowLeft } from 'lucide-react'
import { useState } from 'react'
import { Link, useParams } from 'react-router'
import { Card, PageHeader } from '../../components/ui/Card'
import { Input } from '../../components/ui/Field'
import { Spinner } from '../../components/ui/Spinner'
import { useAuth } from '../../contexts/useAuth'
import { cn } from '../../utils/cn'
import { todayISO } from '../../utils/format'
import { PatientAvatar } from '../patients/components/badges'
import { useClass, useClassMonth, useRoster } from './api'
import { scheduleText } from './schedule'
import { AttendanceSheet } from './components/AttendanceSheet'
import { attendanceStyle } from './types'

export default function ClassDetailPage() {
  const { id } = useParams()
  const classId = Number(id)
  const { can } = useAuth()
  const { data: cls } = useClass(classId)
  const { data: roster } = useRoster(classId)
  const [date, setDate] = useState(todayISO())
  const [month, setMonth] = useState(todayISO().slice(0, 7))
  const { data: sheet } = useClassMonth(classId, month)

  if (!cls) return <Spinner className="text-brand-600" />

  return (
    <>
      <Link to="/app/classes" className="mb-3 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-800">
        <ArrowLeft className="size-4" /> Classes
      </Link>
      <PageHeader title={cls.name} description={`${cls.code} · Trainer: ${cls.lead_trainer?.name ?? '—'} · ${scheduleText(cls)}`} />

      <div className="grid gap-5 lg:grid-cols-[1fr_1.2fr]">
        <Card className="h-fit p-5">
          <h2 className="font-semibold text-slate-900">
            Roster ({roster?.length ?? 0}
            {cls.max_students ? ` / ${cls.max_students}` : ''})
          </h2>
          <ul className="mt-3 divide-y divide-slate-100">
            {roster?.map((r) => (
              <li key={r.enrollment_id} className="flex items-center gap-3 py-2">
                <PatientAvatar id={r.patient.id} name={r.patient.name} hasPhoto={r.patient.has_photo} size="sm" />
                <Link to={`/app/patients/${r.patient.id}?tab=training`} className="min-w-0 flex-1 truncate text-sm font-medium text-slate-900 hover:text-brand-700">
                  {r.patient.name}
                </Link>
                <span className="text-xs text-slate-500">since {r.start_date}</span>
              </li>
            ))}
          </ul>
        </Card>

        {can('training_attendance.mark') && (
          <div>
            <div className="mb-3 flex items-center justify-between gap-2">
              <h2 className="font-semibold text-slate-900">Attendance</h2>
              <Input type="date" value={date} max={todayISO()} onChange={(e) => setDate(e.target.value)} className="w-44" aria-label="Attendance date" />
            </div>
            <AttendanceSheet classId={classId} date={date} />
          </div>
        )}
      </div>

      <Card className="mt-6 overflow-hidden">
        <div className="flex items-center justify-between gap-2 border-b border-slate-100 px-5 py-3">
          <h2 className="font-semibold text-slate-900">Monthly attendance</h2>
          <Input type="month" value={month} onChange={(e) => setMonth(e.target.value)} className="w-44" aria-label="Month" />
        </div>
        {!sheet ? (
          <Spinner className="m-5 text-brand-600" />
        ) : sheet.students.length === 0 ? (
          <p className="p-5 text-sm text-slate-500">No attendance this month.</p>
        ) : (
          <div className="overflow-x-auto">
            <table className="min-w-full text-xs">
              <thead>
                <tr className="text-slate-500">
                  <th className="sticky left-0 bg-white px-3 py-2 text-left font-medium">Student</th>
                  {sheet.dates.map((d) => (
                    <th key={d} className="px-1 py-2 font-medium">
                      {Number(d.slice(8))}
                    </th>
                  ))}
                  <th className="px-3 py-2 font-medium">Rate</th>
                </tr>
              </thead>
              <tbody>
                {sheet.students.map((s) => (
                  <tr key={s.enrollment_id} className="border-t border-slate-100">
                    <td className="sticky left-0 bg-white px-3 py-2 font-medium whitespace-nowrap text-slate-800">{s.patient.name}</td>
                    {sheet.dates.map((d) => {
                      const st = s.days[d]
                      return (
                        <td key={d} className="px-0.5 py-1 text-center">
                          {st && <span className={cn('inline-flex size-6 items-center justify-center rounded text-[10px] font-semibold', attendanceStyle[st].className)}>{attendanceStyle[st].short}</span>}
                        </td>
                      )
                    })}
                    <td className="px-3 py-2 text-center font-semibold text-brand-700">{s.summary.rate === null ? '—' : `${s.summary.rate}%`}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>
    </>
  )
}
