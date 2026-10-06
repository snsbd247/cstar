<?php

namespace App\Http\Requests\Enrollment;

use Illuminate\Foundation\Http\FormRequest;

class TransferEnrollmentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'training_group_id' => ['nullable', 'integer'],
            'trainer_id' => ['nullable', 'integer'],
            'therapist_id' => ['nullable', 'integer'],
            'effective_date' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
