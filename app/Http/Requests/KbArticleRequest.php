<?php

namespace App\Http\Requests;

use App\Enums\KbVisibility;
use App\Models\KbArticle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class KbArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $article = $this->route('article');

        return $article instanceof KbArticle
            ? $this->user()->can('update', $article)
            : $this->user()->can('create', KbArticle::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'category_id' => $this->filled('category_id') ? $this->input('category_id') : null,
            'subcategory_id' => $this->filled('subcategory_id') ? $this->input('subcategory_id') : null,
            'publish' => $this->boolean('publish'),
        ]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:5', 'max:200'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'subcategory_id' => ['nullable', 'integer', Rule::exists('subcategories', 'id')->where('category_id', (int) $this->input('category_id'))],
            'problem' => ['required', 'string', 'max:5000'],
            'symptoms' => ['nullable', 'string', 'max:5000'],
            'root_cause' => ['nullable', 'string', 'max:5000'],
            'steps' => ['required', 'string', 'max:10000'],
            'resolution' => ['nullable', 'string', 'max:5000'],
            'visibility' => ['required', Rule::enum(KbVisibility::class)],
            'tags' => ['nullable', 'string', 'max:500'],
            'source_ticket_id' => ['nullable', 'integer', Rule::exists('tickets', 'id')],
            'publish' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'judul', 'category_id' => 'kategori', 'subcategory_id' => 'subkategori', 'problem' => 'deskripsi masalah',
            'symptoms' => 'gejala', 'root_cause' => 'akar masalah', 'steps' => 'langkah troubleshooting', 'resolution' => 'resolusi',
            'visibility' => 'visibilitas', 'tags' => 'tag',
        ];
    }

    public function messages(): array
    {
        return ['subcategory_id.exists' => 'Subkategori tidak sesuai dengan kategori yang dipilih.'];
    }
}
