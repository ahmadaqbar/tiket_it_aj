<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Subcategory;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CategoryService
{
    private const FIELDS = ['name', 'description', 'sort_order', 'is_active'];

    public function __construct(private readonly AuditService $audit) {}

    public function createCategory(array $data): Category
    {
        $category = Category::create(Arr::only($data, self::FIELDS));
        $this->audit->log('category.created', $category, "Membuat kategori {$category->name}", newValues: Arr::only($category->getAttributes(), self::FIELDS));

        return $category;
    }

    public function updateCategory(Category $category, array $data): Category
    {
        $before = Arr::only($category->getAttributes(), self::FIELDS);
        $category->fill(Arr::only($data, self::FIELDS))->save();
        $this->audit->logChanges('category.updated', $category, $before);

        return $category;
    }

    /** Hanya kategori yang belum pernah dipakai tiket yang dapat dihapus. */
    public function deleteCategory(Category $category): void
    {
        if ($category->isInUse()) {
            throw ValidationException::withMessages(['category' => 'Kategori sudah digunakan tiket dan tidak dapat dihapus. Nonaktifkan saja.']);
        }
        if ($category->subcategories()->whereHas('tickets', fn ($q) => $q->withTrashed())->exists()) {
            throw ValidationException::withMessages(['category' => 'Subkategori di dalam kategori ini sudah digunakan tiket.']);
        }

        DB::transaction(function () use ($category) {
            $this->audit->log('category.deleted', $category, "Menghapus kategori {$category->name}", Arr::only($category->getAttributes(), self::FIELDS));
            $category->subcategories()->delete();
            $category->delete();
        });
    }

    public function createSubcategory(Category $category, array $data): Subcategory
    {
        $sub = $category->subcategories()->create(Arr::only($data, ['name', 'sort_order', 'is_active']));
        $this->audit->log('subcategory.created', $sub, "Membuat subkategori {$sub->name} ({$category->name})", newValues: Arr::only($sub->getAttributes(), ['category_id', 'name', 'is_active']));

        return $sub;
    }

    public function updateSubcategory(Subcategory $sub, array $data): Subcategory
    {
        $before = Arr::only($sub->getAttributes(), ['name', 'sort_order', 'is_active']);
        $sub->fill(Arr::only($data, ['name', 'sort_order', 'is_active']))->save();
        $this->audit->logChanges('subcategory.updated', $sub, $before);

        return $sub;
    }

    public function deleteSubcategory(Subcategory $sub): void
    {
        if ($sub->isInUse()) {
            throw ValidationException::withMessages(['subcategory' => 'Subkategori sudah digunakan tiket dan tidak dapat dihapus. Nonaktifkan saja.']);
        }
        $this->audit->log('subcategory.deleted', $sub, "Menghapus subkategori {$sub->name}", Arr::only($sub->getAttributes(), ['category_id', 'name']));
        $sub->delete();
    }
}
