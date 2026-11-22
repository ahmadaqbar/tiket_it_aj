<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SlaPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('access-admin');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    public function rules(): array
    {
        return [
            'response_minutes' => ['required', 'integer', 'min:5', 'max:43200'],
            'resolution_minutes' => ['required', 'integer', 'min:5', 'max:129600', 'gte:response_minutes'],
            'is_active' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['response_minutes' => 'target respons', 'resolution_minutes' => 'target penyelesaian'];
    }

    public function messages(): array
    {
        return ['resolution_minutes.gte' => 'Target penyelesaian tidak boleh lebih cepat dari target respons.'];
    }
}
