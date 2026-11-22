<?php

namespace App\Http\Requests\Ticket;

use App\Enums\TicketStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', $this->route('ticket'));
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(TicketStatus::class)],
            'message' => ['nullable', 'string', 'max:5000', 'required_if:status,RESOLVED'],
        ];
    }

    public function attributes(): array
    {
        return ['status' => 'status', 'message' => 'pesan untuk pelapor'];
    }

    public function messages(): array
    {
        return ['message.required_if' => 'Catatan penyelesaian wajib diisi saat menandai tiket Resolved.'];
    }
}
