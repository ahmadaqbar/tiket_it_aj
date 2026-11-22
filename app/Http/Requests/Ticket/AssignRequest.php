<?php

namespace App\Http\Requests\Ticket;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', $this->route('ticket'));
    }

    public function rules(): array
    {
        return [
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return ['assigned_to' => 'PIC'];
    }
}
