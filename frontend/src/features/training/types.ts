export type AttendanceStatus = 'present' | 'absent' | 'late' | 'leave' | 'holiday'

export interface ClassSchedule {
  weekday: number
  day: string
  start_time: string
  end_time: string
}

export interface TrainingClass {
  id: number
  code: string
  name: string
  status: 'active' | 'inactive' | 'closed'
  max_students: number | null
  start_date: string | null
  end_date: string | null
  notes: string | null
  branch?: { id: number; name: string }
  lead_trainer?: { id: number; name: string } | null
  schedules?: ClassSchedule[]
  students_count?: number
}

export interface MiniPatient {
  id: number
  name: string
  patient_code: string
  has_photo: boolean
}

export interface RosterDay {
  class: { id: number; name: string; code: string }
  date: string
  is_holiday: boolean
  is_class_day: boolean
  students: { enrollment_id: number; patient: MiniPatient; status: AttendanceStatus | null; arrival_time: string | null; remarks: string | null }[]
}

export interface AttendanceSummary {
  present: number
  late: number
  absent: number
  leave: number
  holiday: number
  rate: number | null
}

export interface TrainingRecord {
  id: number
  enrollment_id: number
  date: string
  start_time: string | null
  end_time: string | null
  duration_min: number | null
  goals_worked: string | null
  observation: string | null
  performance: number | null
  progress: string | null
  challenges: string | null
  trainer_notes: string | null
  parent_note: string | null
  next_plan: string | null
  status: 'draft' | 'final'
  can_amend?: boolean
  patient?: { id: number; name: string; patient_code: string }
  trainer?: { id: number; name: string } | null
  class?: { id: number; name: string } | null
  activities?: { id: number; name: string }[]
  goal_scores?: { goal_id: number; score: number; note: string | null }[]
}

export interface PlanGoal {
  id: number
  domain: string | null
  title: string
  target: string | null
  baseline_level: string | null
  current_level: string | null
  activities: string | null
  measurement: string | null
  progress_percent: number
  status: 'not_started' | 'in_progress' | 'achieved' | 'discontinued'
  review_date: string | null
  recent_scores: { date: string; score: number; note: string | null }[]
}

export interface IndividualPlan {
  id: number
  enrollment_id: number
  title: string
  start_date: string
  review_date: string | null
  status: 'active' | 'closed'
  notes: string | null
  goals: PlanGoal[]
}

export const attendanceStyle: Record<AttendanceStatus, { label: string; short: string; className: string }> = {
  present: { label: 'Present', short: 'P', className: 'bg-brand-600 text-white' },
  late: { label: 'Late', short: 'L', className: 'bg-amber-500 text-white' },
  absent: { label: 'Absent', short: 'A', className: 'bg-red-500 text-white' },
  leave: { label: 'Leave', short: 'LV', className: 'bg-sky-brand-600 text-white' },
  holiday: { label: 'Holiday', short: 'H', className: 'bg-slate-400 text-white' },
}
