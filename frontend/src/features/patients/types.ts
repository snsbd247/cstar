export type PatientTypeCode = 'student' | 'therapy' | 'both' | 'none'
export type EnrollmentStatus = 'pending' | 'active' | 'on_hold' | 'completed' | 'discontinued'
export type EnrollmentType = 'training' | 'therapy'

export interface PatientListItem {
  id: number
  patient_code: string
  name: string
  name_bn: string | null
  has_photo: boolean
  date_of_birth: string
  age: string
  gender: 'male' | 'female' | 'other'
  phone: string
  status: 'active' | 'on_hold' | 'discharged' | 'inactive'
  type: PatientTypeCode
  type_label: string
  registration_date: string
  home_branch?: { id: number; name: string; code: string }
  primary_guardian?: { id: number; name: string; phone: string; relationship: string } | null
}

export interface Guardian {
  id: number
  name: string
  phone: string
  alt_phone: string | null
  email: string | null
  occupation: string | null
  nid: string | null
  address: string | null
  has_portal_account: boolean
  relationship: string
  is_primary: boolean
  is_emergency_contact: boolean
  can_access_portal: boolean
}

export interface Enrollment {
  id: number
  enrollment_code: string
  type: EnrollmentType
  type_label: string
  status: EnrollmentStatus
  start_date: string
  end_date: string | null
  end_reason: string | null
  end_note: string | null
  notes: string | null
  branch?: { id: number; name: string; code: string }
  patient?: { id: number; patient_code: string; name: string }
  training: { class: { id: number; name: string; code: string }; trainer: { id: number; name: string }; monthly_fee: string | null } | null
  therapy: {
    service: { id: number; name: string }
    therapist: { id: number; name: string }
    sessions_per_week: number | null
    session_duration_min: number | null
    billing_mode: string
  } | null
  assignments?: { from_date: string; to_date: string | null; class: string | null; trainer: string | null; therapist: string | null; reason: string | null }[]
}

export const clinicalFields = [
  ['diagnosis_notes', 'Diagnosis / condition notes'],
  ['medical_history', 'Medical history'],
  ['developmental_history', 'Developmental history'],
  ['previous_therapy', 'Previous therapy'],
  ['medications', 'Medications'],
  ['allergies', 'Allergies'],
  ['school_info', 'School'],
] as const
export type ClinicalField = (typeof clinicalFields)[number][0]

export interface PatientDetail extends PatientListItem {
  father_name: string | null
  mother_name: string | null
  alt_phone: string | null
  email: string | null
  address: string | null
  emergency_contact_name: string | null
  emergency_contact_phone: string | null
  emergency_contact_relation: string | null
  referral_source: string | null
  referred_by: string | null
  notes: string | null
  guardians: Guardian[]
  enrollments: Enrollment[]
  consents: { type: string; granted: boolean; signed_on: string }[]
  clinical_profile?: Partial<Record<ClinicalField, string | null>> | null
  diagnoses?: { id: number; name: string }[]
  can: { update: boolean; view_clinical: boolean; manage_guardians: boolean; enroll: boolean }
}

export interface PatientDocument {
  id: number
  category: string
  title: string
  original_name: string
  mime: string
  size: number
  visible_to_parent: boolean
  uploaded_by?: string | null
  created_at: string
}

export interface TimelineEvent {
  id: number
  event_type: string
  title: string
  description: string | null
  actor?: string | null
  occurred_at: string
}

export interface EnrollmentOptions {
  classes: { id: number; code: string; name: string; lead_trainer: { id: number; name: string } | null; max_students: number | null; occupied: number }[]
  trainers: { id: number; name: string }[]
  services: { id: number; name: string; default_duration_min: number | null }[]
  therapists: { id: number; name: string; type: string; service_ids: number[] }[]
}

export const relationships = ['mother', 'father', 'grandparent', 'sibling', 'uncle', 'aunt', 'other'] as const

export const documentCategories: Record<string, string> = {
  medical_report: 'Medical report',
  prescription: 'Prescription',
  previous_assessment: 'Previous assessment',
  consent_form: 'Consent form',
  id_document: 'Birth certificate / ID',
  other: 'Other',
}

export const endReasons: Record<string, string> = {
  goals_achieved: 'Goals achieved',
  dropout: 'Dropped out',
  transferred_out: 'Transferred to another center',
  financial: 'Financial reasons',
  relocated: 'Family relocated',
  other: 'Other',
}
