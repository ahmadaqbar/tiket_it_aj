<?php

namespace App\Http\Requests;

use App\Enums\TicketPriority;
use App\Models\Ticket;
use App\Services\AttachmentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Ticket::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => trim((string) $this->input('title')),
            'description' => trim((string) $this->input('description')),
            'subcategory_id' => $this->filled('subcategory_id') ? $this->input('subcategory_id') : null,
            'priority' => $this->filled('priority') ? $this->input('priority') : null,
        ]);
    }

    public function rules(): array
    {
        $attachments = app(AttachmentService::class);
        $allowedUnitIds = $this->user()->activeUnits()->pluck('id')->all();

        return [
            // Unit harus salah satu unit aktif milik user — mencegah melapor atas nama unit lain.
            'unit_id' => ['required', 'integer', Rule::in($allowedUnitIds)],
            'title' => ['required', 'string', 'min:5', 'max:150'],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('is_active', true)],
            'subcategory_id' => ['nullable', 'integer', Rule::exists('subcategories', 'id')
                ->where('is_active', true)
                ->where('category_id', (int) $this->input('category_id'))],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'priority' => ['nullable', Rule::enum(TicketPriority::class)],
            ...$attachments->rules(),
        ];
    }

    public function attributes(): array
    {
        return [
            'unit_id' => 'unit', 'title' => 'judul laporan', 'category_id' => 'kategori',
            'subcategory_id' => 'subkategori', 'description' => 'deskripsi kendala', 'priority' => 'prioritas',
            'attachments' => 'lampiran', 'attachments.*' => 'lampiran',
        ];
    }

    public function messages(): array
    {
        return [
            'unit_id.in' => 'Unit tidak valid atau Anda tidak terdaftar di unit tersebut.',
            'subcategory_id.exists' => 'Subkategori tidak sesuai dengan kategori yang dipilih.',
            ...AttachmentService::messages(),
        ];
    }
}
