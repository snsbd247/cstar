import { ArrowLeft, CheckCircle2, ChevronRight, Circle, PencilLine } from 'lucide-react'
import { useState } from 'react'
import { Link, useNavigate, useParams, useSearchParams } from 'react-router'
import { Badge, Card } from '../../components/ui/Card'
import { Input, Select } from '../../components/ui/Field'
import { Modal } from '../../components/ui/Modal'
import { Spinner } from '../../components/ui/Spinner'
import { todayISO } from '../../utils/format'
import { PatientAvatar } from '../patients/components/badges'
import { useClasses, useRecordsDay, useStudents, useTrainingRecords, type DayRecordRow } from '../training/api'
import { AttendanceSheet } from '../training/components/AttendanceSheet'
import { MonthAttendance } from '../training/components/MonthAttendance'
import { PlanPanel } from '../training/components/PlanPanel'
import { RecordForm } from '../training/components/RecordForm'
import { attendanceStyle } from '../training/types'

/** Class + date picker shared by the attendance and records screens (kept in the URL). */
function useClassAndDate() {
  const [params, setParams] = useSearchParams()
  const { data: classes } = useClasses()
  const classId = Number(params.get('class')) || classes?.[0]?.id
  const date = params.get('date') ?? todayISO()
  const set = (patch: Record<string, string>) => setParams({ class: String(classId ?? ''), date, ...patch }, { replace: true })

  const picker = (
    <div className="grid grid-cols-[1fr_auto] gap-2">
      <Select value={classId ?? ''} onChange={(e) => set({ class: e.target.value })} aria-label="Class">
        {classes?.map((c) => (
          <option key={c.id} value={c.id}>
            {c.name}
          </option>
        ))}
      </Select>
      <Input type="date" value={date} max={todayISO()} onChange={(e) => set({ date: e.target.value })} aria-label="Date" className="w-40" />
    </div>
  )

  return { classId, date, picker, hasClasses: !!classes?.length, loading: !classes }
}

export function TrainerAttendancePage() {
  const { classId, date, picker, hasClasses, loading } = useClassAndDate()
  const navigate = useNavigate()

  return (
    <div className="space-y-4">
      <h1 className="text-xl font-semibold text-slate-900">Attendance</h1>
      {picker}
      {loading ? <Spinner className="text-brand-600" /> : !hasClasses ? <Card className="p-5 text-sm text-slate-500">You have no classes.</Card> : null}
      {classId && <AttendanceSheet classId={classId} date={date} onSaved={() => navigate(`/trainer/records?class=${classId}&date=${date}`)} />}
    </div>
  )
}

export function TrainerRecordsPage() {
  const { classId, date, picker } = useClassAndDate()
  const { data: rows, isLoading } = useRecordsDay(classId, date)
  const [open, setOpen] = useState<DayRecordRow | null>(null)

  return (
    <div className="space-y-4">
      <h1 className="text-xl font-semibold text-slate-900">Training records</h1>
      {picker}
      {isLoading ? (
        <Spinner className="text-brand-600" />
      ) : !rows?.length ? (
        <Card className="p-5 text-sm text-slate-500">
          No student marked present for this day. <Link to={`/trainer/attendance?class=${classId}&date=${date}`} className="font-medium text-brand-700 underline">Take attendance first</Link>.
        </Card>
      ) : (
        <Card className="overflow-hidden">
          <ul className="divide-y divide-slate-100">
            {rows.map((r) => (
              <li key={r.enrollment_id}>
                <button onClick={() => setOpen(r)} className="flex w-full items-center gap-3 px-4 py-3 text-left hover:bg-slate-50">
                  <PatientAvatar id={r.patient.id} name={r.patient.name} hasPhoto={r.patient.has_photo} size="sm" />
                  <span className="min-w-0 flex-1">
                    <span className="block truncate font-medium text-slate-900">{r.patient.name}</span>
                    <span className="text-xs text-slate-500">{attendanceStyle[r.attendance].label}</span>
                  </span>
                  {r.record?.status === 'final' ? (
                    <CheckCircle2 className="size-5 text-brand-600" aria-label="Final" />
                  ) : r.record ? (
                    <PencilLine className="size-5 text-amber-500" aria-label="Draft" />
                  ) : (
                    <Circle className="size-5 text-slate-300" aria-label="Not written" />
                  )}
                </button>
              </li>
            ))}
          </ul>
        </Card>
      )}

      {open && classId && (
        <Modal open title={`${open.patient.name} — ${date}`} onClose={() => setOpen(null)}>
          <RecordForm classId={classId} date={date} enrollmentId={open.enrollment_id} record={open.record} onDone={() => setOpen(null)} />
        </Modal>
      )}
    </div>
  )
}

export function TrainerStudentsPage() {
  const { data: students, isLoading } = useStudents()

  return (
    <div className="space-y-4">
      <h1 className="text-xl font-semibold text-slate-900">My students</h1>
      {isLoading ? (
        <Spinner className="text-brand-600" />
      ) : !students?.length ? (
        <Card className="p-5 text-sm text-slate-500">No students assigned to you.</Card>
      ) : (
        <Card className="overflow-hidden">
          <ul className="divide-y divide-slate-100">
            {students.map((s) => (
              <li key={s.enrollment_id}>
                <Link to={`/trainer/students/${s.enrollment_id}`} className="flex items-center gap-3 px-4 py-3 hover:bg-slate-50">
                  <PatientAvatar id={s.patient.id} name={s.patient.name} hasPhoto={s.patient.has_photo} size="sm" />
                  <span className="min-w-0 flex-1">
                    <span className="block truncate font-medium text-slate-900">{s.patient.name}</span>
                    <span className="text-xs text-slate-500">
                      {s.class.name} · {s.patient.age}
                    </span>
                  </span>
                  {s.month_attendance.rate !== null && <Badge tone={s.month_attendance.rate >= 80 ? 'green' : 'amber'}>{s.month_attendance.rate}%</Badge>}
                  <ChevronRight className="size-4 text-slate-300" />
                </Link>
              </li>
            ))}
          </ul>
        </Card>
      )}
    </div>
  )
}

export function TrainerStudentPage() {
  const { enrollmentId } = useParams()
  const id = Number(enrollmentId)
  const { data: students } = useStudents()
  const student = students?.find((s) => s.enrollment_id === id)
  const { data: records } = useTrainingRecords({ patient_id: student?.patient.id })

  if (!student) return <Spinner className="text-brand-600" />

  return (
    <div className="space-y-4">
      <Link to="/trainer/students" className="inline-flex items-center gap-1 text-sm text-slate-500">
        <ArrowLeft className="size-4" /> My students
      </Link>
      <div className="flex items-center gap-3">
        <PatientAvatar id={student.patient.id} name={student.patient.name} hasPhoto={student.patient.has_photo} size="lg" />
        <div>
          <h1 className="text-xl font-semibold text-slate-900">{student.patient.name}</h1>
          <p className="text-sm text-slate-500">
            {student.patient.patient_code} · {student.patient.age} · {student.class.name}
          </p>
        </div>
      </div>
      <PlanPanel enrollmentId={id} canEdit />
      <MonthAttendance enrollmentId={id} />
      <Card className="p-5">
        <h3 className="font-semibold text-slate-900">Recent records</h3>
        <ul className="mt-3 space-y-3">
          {records?.data.length === 0 && <li className="text-sm text-slate-500">No records yet.</li>}
          {records?.data.slice(0, 8).map((r) => (
            <li key={r.id} className="border-l-2 border-brand-200 pl-3">
              <p className="text-xs text-slate-500">
                {r.date} · {r.status === 'final' ? 'Final' : 'Draft'}
                {r.performance && ` · ${'★'.repeat(r.performance)}`}
              </p>
              <p className="text-sm text-slate-800">{r.observation || r.progress || '—'}</p>
              {r.activities && r.activities.length > 0 && <p className="text-xs text-slate-500">{r.activities.map((a) => a.name).join(', ')}</p>}
            </li>
          ))}
        </ul>
      </Card>
    </div>
  )
}
