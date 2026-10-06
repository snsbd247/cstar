<?php

namespace App\Http\Requests\Branch;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BranchRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => $this->filled('code') ? Str::upper((string) $this->input('code')) : null,
            'slug' => $this->input('slug') ?: Str::slug((string) $this->input('name')),
        ]);
    }

    public function rules(): array
    {
        $id = $this->route('branch')?->id;

        return [
            'code' => ['required', 'string', 'max:10', 'alpha_dash', Rule::unique('branches')->ignore($id)],
            'name' => ['required', 'string', 'max:255'],
            'name_bn' => ['nullable', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('branches')->ignore($id)],
            'address' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'map_url' => ['nullable', 'url', 'max:500'],
            'opening_hours' => ['nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
            'show_on_website' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
