<?php

namespace App\Http\Requests\Ticket;

use App\Enums\TicketPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReclassifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', $this->route('ticket'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['subcategory_id' => $this->filled('subcategory_id') ? $this->input('subcategory_id') : null]);
    }

    public function rules(): array
    {
        $ticket = $this->route('ticket');

        return [
            'priority' => ['required', Rule::enum(TicketPriority::class)],
            // Kategori baru harus aktif; kategori lama tiket (walau sudah nonaktif) tetap boleh dipertahankan.
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where(
                fn ($q) => $q->where('is_active', true)->orWhere('id', $ticket->category_id)
            )],
            'subcategory_id' => ['nullable', 'integer', Rule::exists('subcategories', 'id')
                ->where('category_id', (int) $this->input('category_id'))
                ->where(fn ($q) => $q->where('is_active', true)->orWhere('id', (int) $ticket->subcategory_id))],
        ];
    }

    public function attributes(): array
    {
        return ['priority' => 'prioritas', 'category_id' => 'kategori', 'subcategory_id' => 'subkategori'];
    }

    public function messages(): array
    {
        return ['subcategory_id.exists' => 'Subkategori tidak sesuai dengan kategori yang dipilih.'];
    }
}
