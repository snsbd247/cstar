import { CheckCheck } from 'lucide-react'
import { useState } from 'react'
import { errorMessage, validationErrors } from '../../../api/client'
import { Button } from '../../../components/ui/Button'
import { Alert, Card } from '../../../components/ui/Card'
import { Spinner } from '../../../components/ui/Spinner'
import { cn } from '../../../utils/cn'
import { PatientAvatar } from '../../patients/components/badges'
import { useAttendanceDay, useMarkAttendance } from '../api'
import { attendanceStyle, type AttendanceStatus, type RosterDay } from '../types'

const choices: AttendanceStatus[] = ['present', 'late', 'absent', 'leave']

/**
 * One-tap attendance for a class (Plan §৩৭: Trainer → Today's Students → Save).
 * Everyone starts as "Present"; tap another status for the few who differ, then Save once.
 */
export function AttendanceSheet({ classId, date, onSaved }: { classId: number; date: string; onSaved?: () => void }) {
  const { data, isLoading } = useAttendanceDay(classId, date)
  if (isLoading || !data) return <Spinner className="text-brand-600" />
  return <SheetBody key={`${classId}-${date}-${data.students.map((s) => s.status ?? '').join()}`} data={data} classId={classId} date={date} onSaved={onSaved} />
}

function SheetBody({ data, classId, date, onSaved }: { data: RosterDay; classId: number; date: string; onSaved?: () => void }) {
  const mark = useMarkAttendance(classId)
  const fallback: AttendanceStatus = data.is_holiday ? 'holiday' : 'present'
  const [statuses, setStatuses] = useState<Record<number, AttendanceStatus>>(() =>
    Object.fromEntries(data.students.map((s) => [s.enrollment_id, s.status ?? fallback])),
  )
  const [message, setMessage] = useState<{ tone: 'green' | 'red'; text: string } | null>(null)
  const alreadyMarked = data.students.some((s) => s.status)

  const save = async () => {
    setMessage(null)
    try {
      await mark.mutateAsync({ date, entries: data.students.map((s) => ({ enrollment_id: s.enrollment_id, status: statuses[s.enrollment_id] })) })
      setMessage({ tone: 'green', text: 'Attendance saved.' })
      onSaved?.()
    } catch (e) {
      const fields = validationErrors(e)
      setMessage({ tone: 'red', text: fields.date ?? Object.values(fields)[0] ?? errorMessage(e) })
    }
  }

  if (!data.students.length) return <Card className="p-5 text-sm text-slate-500">No students on this class's roster for this day.</Card>

  const counts = Object.values(statuses).reduce<Record<string, number>>((acc, s) => ({ ...acc, [s]: (acc[s] ?? 0) + 1 }), {})

  return (
    <div className="space-y-3">
      {data.is_holiday && <Alert tone="green">This day is a holiday — everyone is marked "Holiday".</Alert>}
      {!data.is_class_day && !data.is_holiday && <Alert>This class is not scheduled on this day.</Alert>}
      {message && <Alert tone={message.tone}>{message.text}</Alert>}

      <div className="flex flex-wrap gap-2 text-xs">
        {(Object.keys(attendanceStyle) as AttendanceStatus[])
          .filter((s) => counts[s])
          .map((s) => (
            <span key={s} className={cn('rounded-full px-2.5 py-1 font-medium', attendanceStyle[s].className)}>
              {attendanceStyle[s].label}: {counts[s]}
            </span>
          ))}
        {!alreadyMarked && <span className="rounded-full bg-amber-50 px-2.5 py-1 font-medium text-amber-800">Not saved yet</span>}
      </div>

      <Card className="overflow-hidden">
        <ul className="divide-y divide-slate-100">
          {data.students.map((s) => (
            <li key={s.enrollment_id} className="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center">
              <div className="flex min-w-0 flex-1 items-center gap-3">
                <PatientAvatar id={s.patient.id} name={s.patient.name} hasPhoto={s.patient.has_photo} size="sm" />
                <div className="min-w-0">
                  <p className="truncate font-medium text-slate-900">{s.patient.name}</p>
                  <p className="text-xs text-slate-500">{s.patient.patient_code}</p>
                </div>
              </div>
              {!data.is_holiday && (
                <div className="grid grid-cols-4 gap-1.5" role="radiogroup" aria-label={`Attendance for ${s.patient.name}`}>
                  {choices.map((c) => (
                    <button
                      key={c}
                      type="button"
                      role="radio"
                      aria-checked={statuses[s.enrollment_id] === c}
                      onClick={() => setStatuses((st) => ({ ...st, [s.enrollment_id]: c }))}
                      className={cn(
                        'min-h-10 rounded-lg px-2.5 text-xs font-semibold transition',
                        statuses[s.enrollment_id] === c ? attendanceStyle[c].className : 'bg-slate-100 text-slate-500 hover:bg-slate-200',
                      )}
                    >
                      {attendanceStyle[c].label}
                    </button>
                  ))}
                </div>
              )}
            </li>
          ))}
        </ul>
      </Card>

      <Button className="w-full sm:w-auto" onClick={save} loading={mark.isPending}>
        <CheckCheck className="size-4" /> {alreadyMarked ? 'Update attendance' : 'Save attendance'}
      </Button>
    </div>
  )
}
