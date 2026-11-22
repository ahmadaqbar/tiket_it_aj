<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubcategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('access-admin');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'is_active' => $this->boolean('is_active'),
            'sort_order' => (int) $this->input('sort_order', 0),
        ]);
    }

    public function rules(): array
    {
        $sub = $this->route('subcategory');
        $categoryId = $sub?->category_id ?? $this->route('category')?->id;

        return [
            'name' => ['required', 'string', 'max:100',
                Rule::unique('subcategories', 'name')->where('category_id', $categoryId)->ignore($sub?->id)],
            'sort_order' => ['integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nama subkategori', 'sort_order' => 'urutan'];
    }
}
