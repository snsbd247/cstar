import { useState } from 'react'
import { Link } from 'react-router'
import { Badge, Card, PageHeader } from '../../components/ui/Card'
import { Select } from '../../components/ui/Field'
import { Spinner } from '../../components/ui/Spinner'
import { PatientAvatar } from '../patients/components/badges'
import { useClasses, useStudents } from './api'

/** Regular students = children with an open training enrollment (PATIENT ≠ STUDENT). */
export default function StudentsPage() {
  const [classId, setClassId] = useState<number | undefined>()
  const { data: classes } = useClasses()
  const { data: students, isLoading } = useStudents(classId)

  return (
    <>
      <PageHeader title="Students / Training" description="Children currently enrolled in regular training, with this month's attendance." />
      <Card className="mb-4 p-3">
        <Select value={classId ?? ''} onChange={(e) => setClassId(Number(e.target.value) || undefined)} aria-label="Filter by class" className="sm:w-72">
          <option value="">All classes</option>
          {classes?.map((c) => (
            <option key={c.id} value={c.id}>
              {c.name}
            </option>
          ))}
        </Select>
      </Card>
      {isLoading ? (
        <Spinner className="text-brand-600" />
      ) : !students?.length ? (
        <Card className="p-5 text-sm text-slate-500">No regular students.</Card>
      ) : (
        <Card className="overflow-hidden">
          <ul className="divide-y divide-slate-100">
            {students.map((s) => (
              <li key={s.enrollment_id}>
                <Link to={`/app/patients/${s.patient.id}?tab=training`} className="flex flex-wrap items-center gap-3 px-4 py-3 hover:bg-slate-50">
                  <PatientAvatar id={s.patient.id} name={s.patient.name} hasPhoto={s.patient.has_photo} size="sm" />
                  <div className="min-w-0 flex-1">
                    <p className="truncate font-medium text-slate-900">{s.patient.name}</p>
                    <p className="text-sm text-slate-500">
                      {s.patient.patient_code} · {s.class.name} · {s.trainer.name}
                    </p>
                  </div>
                  {s.status !== 'active' && <Badge tone="amber">{s.status.replace('_', ' ')}</Badge>}
                  <div className="text-right">
                    <p className="font-semibold text-brand-700">{s.month_attendance.rate === null ? '—' : `${s.month_attendance.rate}%`}</p>
                    <p className="text-[11px] text-slate-500">
                      P {s.month_attendance.present} · A {s.month_attendance.absent}
                    </p>
                  </div>
                </Link>
              </li>
            ))}
          </ul>
        </Card>
      )}
    </>
  )
}
