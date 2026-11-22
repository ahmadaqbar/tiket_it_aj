<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('access-admin');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'ticket_number_prefix' => strtoupper(trim((string) $this->input('ticket_number_prefix'))),
            'kb_public_enabled' => $this->boolean('kb_public_enabled'),
            'ai_enabled' => $this->boolean('ai_enabled'),
            'remove_logo' => $this->boolean('remove_logo'),
        ]);
    }

    public function rules(): array
    {
        return [
            'app_name' => ['required', 'string', 'max:100'],
            'short_name' => ['required', 'string', 'max:30'],
            'organization_name' => ['required', 'string', 'max:150'],
            'support_team' => ['required', 'string', 'max:150'],
            'contact_email' => ['nullable', 'email', 'max:150'],
            'contact_phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+\-\s()]+$/'],
            'contact_address' => ['nullable', 'string', 'max:255'],
            'primary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo' => ['nullable', 'file', 'max:1024', 'mimes:png,jpg,jpeg,webp', 'mimetypes:image/png,image/jpeg,image/webp', 'dimensions:max_width=2000,max_height=2000'],
            'remove_logo' => ['boolean'],
            'ticket_number_prefix' => ['required', 'regex:/^[A-Z0-9]{1,10}$/'],
            'auto_close_days' => ['required', 'integer', 'min:0', 'max:60'],
            'upload_max_kb' => ['required', 'integer', 'min:100', 'max:20480'],
            'upload_max_files' => ['required', 'integer', 'min:1', 'max:10'],
            'kb_public_enabled' => ['boolean'],
            'ai_enabled' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'app_name' => 'nama aplikasi', 'short_name' => 'nama singkat', 'organization_name' => 'nama organisasi',
            'support_team' => 'nama tim IT', 'contact_email' => 'email kontak', 'contact_phone' => 'telepon kontak',
            'contact_address' => 'alamat', 'primary_color' => 'warna utama', 'logo' => 'logo',
            'ticket_number_prefix' => 'prefix nomor tiket', 'auto_close_days' => 'hari auto-close',
            'upload_max_kb' => 'ukuran maksimum lampiran', 'upload_max_files' => 'jumlah maksimum lampiran',
        ];
    }

    public function messages(): array
    {
        return [
            'primary_color.regex' => 'Warna harus format heksadesimal, mis. #1b5434.',
            'ticket_number_prefix.regex' => 'Prefix hanya huruf kapital/angka, maks. 10 karakter.',
        ];
    }
}
