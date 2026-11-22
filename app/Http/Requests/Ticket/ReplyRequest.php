<?php

namespace App\Http\Requests\Ticket;

use App\Enums\ReplyVisibility;
use App\Services\AttachmentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ticket = $this->route('ticket');

        return $this->visibility() === ReplyVisibility::INTERNAL
            ? $this->user()->can('addInternalNote', $ticket)
            : $this->user()->can('reply', $ticket);
    }

    /** Role USER selalu PUBLIC, apa pun isi request. */
    public function visibility(): ReplyVisibility
    {
        if (! $this->user()->isItStaff()) {
            return ReplyVisibility::PUBLIC;
        }

        return ReplyVisibility::tryFrom((string) $this->input('visibility')) ?? ReplyVisibility::PUBLIC;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'min:2', 'max:5000'],
            'visibility' => ['nullable', Rule::enum(ReplyVisibility::class)],
            ...app(AttachmentService::class)->rules(),
        ];
    }

    public function attributes(): array
    {
        return ['message' => 'pesan', 'attachments' => 'lampiran', 'attachments.*' => 'lampiran'];
    }

    public function messages(): array
    {
        return AttachmentService::messages();
    }
}
