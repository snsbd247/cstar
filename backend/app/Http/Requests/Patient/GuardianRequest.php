<?php

namespace App\Http\Requests\Patient;

use App\Models\Guardian;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Add a guardian to a child (new or existing) or update the link + guardian details. */
class GuardianRequest extends FormRequest
{
    public function rules(): array
    {
        $linking = $this->route('guardian') === null;

        return [
            'id' => [$linking ? 'nullable' : 'prohibited', 'integer', Rule::exists('guardians', 'id')->whereNull('deleted_at')],
            'name' => [$linking ? 'required_without:id' : 'sometimes', 'nullable', 'string', 'max:255'],
            'phone' => [$linking ? 'required_without:id' : 'sometimes', 'nullable', 'string', PatientRequest::PHONE],
            'alt_phone' => ['nullable', 'string', PatientRequest::PHONE],
            'email' => ['nullable', 'email', 'max:255'],
            'occupation' => ['nullable', 'string', 'max:255'],
            'nid' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:1000'],
            'relationship' => ['required', Rule::in(Guardian::RELATIONSHIPS)],
            'is_primary' => ['sometimes', 'boolean'],
            'is_emergency_contact' => ['sometimes', 'boolean'],
            'can_access_portal' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['regex' => 'Enter a valid Bangladeshi mobile number (01XXXXXXXXX).'];
    }
}
