<?php

namespace App\Http\Requests\Patient;

use App\Enums\PatientStatus;
use App\Models\Consent;
use App\Models\Guardian;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class PatientRequest extends FormRequest
{
    public const PHONE = 'regex:/^01[3-9]\d{8}$/';

    public function rules(): array
    {
        $creating = $this->route('patient') === null;

        return [
            'home_branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:255'],
            'name_bn' => ['nullable', 'string', 'max:255'],
            'date_of_birth' => ['required', 'date', 'before_or_equal:today', 'after:1940-01-01'],
            'gender' => ['required', Rule::in(['male', 'female', 'other'])],
            'father_name' => ['nullable', 'string', 'max:255'],
            'mother_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', self::PHONE],
            'alt_phone' => ['nullable', 'string', self::PHONE],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', self::PHONE],
            'emergency_contact_relation' => ['nullable', 'string', 'max:50'],
            'referral_source' => ['nullable', Rule::in(['doctor', 'website', 'facebook', 'parent', 'school', 'other'])],
            'referred_by' => ['nullable', 'string', 'max:255'],
            'registration_date' => ['nullable', 'date', 'before_or_equal:today'],
            'status' => [$creating ? 'prohibited' : 'sometimes', new Enum(PatientStatus::class)],
            'notes' => ['nullable', 'string', 'max:5000'],

            'clinical' => ['sometimes', 'array'],
            'clinical.*' => ['nullable', 'string', 'max:10000'],
            'diagnosis_ids' => ['sometimes', 'array'],
            'diagnosis_ids.*' => ['integer', 'distinct', 'exists:diagnoses,id'],

            // Primary guardian on registration: an existing guardian (sibling) or a new one.
            'guardian' => [$creating ? 'required' : 'prohibited', 'array'],
            ...($creating ? [
                'guardian.id' => ['nullable', 'integer', Rule::exists('guardians', 'id')->whereNull('deleted_at')],
                'guardian.name' => ['required_without:guardian.id', 'nullable', 'string', 'max:255'],
                'guardian.phone' => ['required_without:guardian.id', 'nullable', 'string', self::PHONE],
                'guardian.email' => ['nullable', 'email', 'max:255'],
                'guardian.occupation' => ['nullable', 'string', 'max:255'],
                'guardian.relationship' => ['required', Rule::in(Guardian::RELATIONSHIPS)],
            ] : []),

            'consents' => [$creating ? 'sometimes' : 'prohibited', 'array:'.implode(',', Consent::TYPES)],
            'consents.*' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'regex' => 'Enter a valid Bangladeshi mobile number (01XXXXXXXXX).',
            'guardian.required' => 'Add the primary guardian (parent) of the child.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $branchId = (int) $this->input('home_branch_id');
                if ($branchId && ! $this->user()->canAccessBranch($branchId)) {
                    $validator->errors()->add('home_branch_id', 'You can only register children in your own branch.');
                }
            },
        ];
    }
}
