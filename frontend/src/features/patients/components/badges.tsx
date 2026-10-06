import { useState } from 'react'
import { Badge } from '../../../components/ui/Card'
import { cn } from '../../../utils/cn'
import type { EnrollmentStatus, PatientTypeCode } from '../types'

const typeTone = { student: 'green', therapy: 'blue', both: 'amber', none: 'gray' } as const
const typeShort = { student: 'Regular Student', therapy: 'Therapy Patient', both: 'Student + Therapy', none: 'No enrollment' }

/** Derived from enrollments — never set by hand (PATIENT ≠ STUDENT). */
export function PatientTypeBadge({ type }: { type: PatientTypeCode }) {
  return <Badge tone={typeTone[type]}>{typeShort[type]}</Badge>
}

const statusTone = { pending: 'amber', active: 'green', on_hold: 'gray', completed: 'blue', discontinued: 'red' } as const

export function EnrollmentStatusBadge({ status }: { status: EnrollmentStatus }) {
  return <Badge tone={statusTone[status]}>{status.replace('_', ' ')}</Badge>
}

/** Child photo (served privately by the API) with an initials fallback. */
export function PatientAvatar({ id, name, hasPhoto, size = 'md' }: { id: number; name: string; hasPhoto: boolean; size?: 'sm' | 'md' | 'lg' }) {
  const [failed, setFailed] = useState(false)
  const dims = { sm: 'size-9 text-sm', md: 'size-11 text-base', lg: 'size-20 text-2xl' }[size]
  const initials = name
    .split(' ')
    .map((p) => p[0])
    .slice(0, 2)
    .join('')
    .toUpperCase()

  if (hasPhoto && !failed) {
    return <img src={`/api/v1/patients/${id}/photo`} alt={name} onError={() => setFailed(true)} className={cn('shrink-0 rounded-full object-cover', dims)} />
  }
  return <span className={cn('flex shrink-0 items-center justify-center rounded-full bg-sky-brand-100 font-semibold text-sky-brand-700', dims)}>{initials}</span>
}
