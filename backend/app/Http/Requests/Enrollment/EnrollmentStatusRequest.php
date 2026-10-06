<?php

namespace App\Http\Requests\Enrollment;

use App\Models\Enrollment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EnrollmentStatusRequest extends FormRequest
{
    public function rules(): array
    {
        $action = $this->route('action');

        return [
            'end_date' => ['nullable', 'date', 'before_or_equal:today'],
            'end_reason' => [$action === 'discontinue' ? 'required' : 'nullable', Rule::in(Enrollment::END_REASONS)],
            'end_note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return ['end_reason.required' => 'Select why the enrollment is being discontinued.'];
    }
}
