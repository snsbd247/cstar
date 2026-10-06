<?php

namespace App\Http\Requests\Enrollment;

use App\Enums\EnrollmentType;
use App\Models\TherapyEnrollment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

/** Shape only — business rules (capacity, duplicates, therapist ↔ service) live in EnrollmentService. */
class StoreEnrollmentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'integer', Rule::exists('patients', 'id')->whereNull('deleted_at')],
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->whereNull('deleted_at')],
            'type' => ['required', new Enum(EnrollmentType::class)],
            'status' => ['sometimes', Rule::in(['pending', 'active'])],
            'start_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'source_assessment_id' => ['nullable', 'integer', Rule::exists('assessments', 'id')->where('patient_id', $this->input('patient_id'))],
            'recommendation_id' => ['nullable', 'integer', 'exists:assessment_recommendations,id'],

            // Regular Training
            'training_group_id' => ['required_if:type,training', 'prohibited_if:type,therapy', 'nullable', 'integer'],
            'trainer_id' => ['prohibited_if:type,therapy', 'nullable', 'integer'],
            'monthly_fee' => ['prohibited_if:type,therapy', 'nullable', 'numeric', 'min:0'],

            // Therapy
            'service_id' => ['required_if:type,therapy', 'prohibited_if:type,training', 'nullable', 'integer'],
            'therapist_id' => ['required_if:type,therapy', 'prohibited_if:type,training', 'nullable', 'integer'],
            'sessions_per_week' => ['prohibited_if:type,training', 'nullable', 'integer', 'between:1,7'],
            'session_duration_min' => ['prohibited_if:type,training', 'nullable', 'integer', 'between:15,240'],
            'billing_mode' => ['prohibited_if:type,training', 'nullable', Rule::in(TherapyEnrollment::BILLING_MODES)],
        ];
    }

    public function messages(): array
    {
        return [
            'training_group_id.required_if' => 'Regular Training needs a class.',
            'service_id.required_if' => 'Therapy needs a therapy service.',
            'therapist_id.required_if' => 'Therapy needs a therapist.',
            'prohibited_if' => 'This field does not apply to this enrollment type.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $branchId = (int) $this->input('branch_id');
                if ($branchId && ! $this->user()->canAccessBranch($branchId)) {
                    $validator->errors()->add('branch_id', 'You can only enroll in your own branch.');
                }
            },
        ];
    }
}
