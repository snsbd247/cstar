<?php

namespace App\Http\Requests\User;

use App\Enums\Role;
use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UserRequest extends FormRequest
{
    public function rules(): array
    {
        $id = $this->route('user')?->id;
        $creating = $id === null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'required_without:phone', 'email', 'max:255', Rule::unique('users')->ignore($id)],
            'phone' => ['nullable', 'required_without:email', 'string', 'regex:/^01[3-9]\d{8}$/', Rule::unique('users')->ignore($id)],
            'password' => [$creating ? 'required' : 'nullable', 'string', Password::defaults()],
            'role' => ['required', new Enum(Role::class)],
            'status' => ['sometimes', new Enum(UserStatus::class)],
            'must_change_password' => ['sometimes', 'boolean'],
            'branch_ids' => ['required', 'array', 'min:1'],
            'branch_ids.*' => ['integer', 'distinct', Rule::exists('branches', 'id')->whereNull('deleted_at')],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter a valid Bangladeshi mobile number (e.g. 01712345678).',
        ];
    }

    /** A Branch Admin may only give out staff/parent roles, and only in their own branches. */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $actor = $this->user();

                if ($actor->isSuperAdmin() || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $role = Role::from($this->input('role'));
                if (! in_array($role, Role::assignableByBranchAdmin(), true)) {
                    $validator->errors()->add('role', 'You are not allowed to assign this role.');
                }

                $outside = array_diff($this->input('branch_ids', []), $actor->accessibleBranchIds() ?? []);
                if ($outside !== []) {
                    $validator->errors()->add('branch_ids', 'You can only assign your own branches.');
                }
            },
        ];
    }
}
