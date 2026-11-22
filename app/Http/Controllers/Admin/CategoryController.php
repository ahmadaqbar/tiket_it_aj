<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Http\Requests\Admin\SubcategoryRequest;
use App\Models\Category;
use App\Models\Subcategory;
use App\Services\CategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function __construct(private readonly CategoryService $categories) {}

    public function index(): View
    {
        return view('admin.categories.index', [
            'categories' => Category::query()
                ->withCount(['tickets', 'subcategories'])
                ->with(['subcategories' => fn ($q) => $q->withCount('tickets')])
                ->ordered()->get(),
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        $category = $this->categories->createCategory($request->validated());

        return back()->with('success', "Kategori {$category->name} ditambahkan.");
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $this->categories->updateCategory($category, $request->validated());

        return back()->with('success', "Kategori {$category->name} diperbarui.");
    }

    public function destroy(Category $category): RedirectResponse
    {
        $this->authorize('access-admin');
        $this->categories->deleteCategory($category);

        return back()->with('success', 'Kategori dihapus.');
    }

    public function storeSubcategory(SubcategoryRequest $request, Category $category): RedirectResponse
    {
        $sub = $this->categories->createSubcategory($category, $request->validated());

        return back()->with('success', "Subkategori {$sub->name} ditambahkan.");
    }

    public function updateSubcategory(SubcategoryRequest $request, Subcategory $subcategory): RedirectResponse
    {
        $this->categories->updateSubcategory($subcategory, $request->validated());

        return back()->with('success', "Subkategori {$subcategory->name} diperbarui.");
    }

    public function destroySubcategory(Subcategory $subcategory): RedirectResponse
    {
        $this->authorize('access-admin');
        $this->categories->deleteSubcategory($subcategory);

        return back()->with('success', 'Subkategori dihapus.');
    }
}
