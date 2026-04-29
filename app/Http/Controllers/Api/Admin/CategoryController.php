<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(Category::with('children')->orderBy('sort_order')->orderBy('name')->paginate((int) $request->integer('per_page', 50)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['slug'] ??= Str::slug($data['name']);

        return response()->json(Category::create($data), 201);
    }

    public function show(Category $category): JsonResponse
    {
        return response()->json($category->load(['parent', 'children', 'products']));
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $data = $this->validated($request, false);
        $data['slug'] ??= isset($data['name']) ? Str::slug($data['name']) : $category->slug;
        $category->update($data);

        return response()->json($category->fresh(['parent', 'children']));
    }

    public function destroy(Category $category): JsonResponse
    {
        abort_if($category->products()->exists() || $category->children()->exists(), 422, 'No se puede eliminar una categoria con productos o subcategorias.');
        $category->delete();

        return response()->json(null, 204);
    }

    private function validated(Request $request, bool $creating = true): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'name' => [$required, 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:categories,slug'.($creating ? '' : ','.$request->route('category')?->id)],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);
    }
}
