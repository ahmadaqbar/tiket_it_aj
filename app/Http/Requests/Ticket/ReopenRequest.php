<?php

namespace App\Http\Requests\Ticket;

use Illuminate\Foundation\Http\FormRequest;

class ReopenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('respondToResolution', $this->route('ticket'));
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:5', 'max:1000']];
    }

    public function attributes(): array
    {
        return ['reason' => 'alasan'];
    }
}
