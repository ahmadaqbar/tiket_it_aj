<?php

namespace App\Http\Requests\Admin;

use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        $unit = $this->route('unit');

        return $unit instanceof Unit
            ? $this->user()->can('update', $unit)
            : $this->user()->can('create', Unit::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'is_active' => $this->boolean('is_active'),
            'sort_order' => (int) $this->input('sort_order', 0),
        ]);
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'min:2', 'max:10', 'regex:/^[A-Z0-9]+$/', Rule::unique('units', 'code')->ignore($this->route('unit')?->id)],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0', 'max:9999'],
        ];
    }

    public function attributes(): array
    {
        return ['code' => 'kode unit', 'name' => 'nama unit', 'description' => 'keterangan', 'sort_order' => 'urutan'];
    }

    public function messages(): array
    {
        return ['code.regex' => 'Kode unit hanya boleh berisi huruf kapital dan angka tanpa spasi.'];
    }
}
