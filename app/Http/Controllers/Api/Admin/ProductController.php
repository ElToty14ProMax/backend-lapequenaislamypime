<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(Product::with(['category', 'images'])
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->query('q'), fn ($query, $term) => $query->where('name', 'ilike', "%{$term}%")->orWhere('sku', 'ilike', "%{$term}%"))
            ->latest()
            ->paginate((int) $request->integer('per_page', 25)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['slug'] ??= Str::slug($data['name']);
        $images = $data['images'] ?? [];
        unset($data['images']);

        $product = Product::create($data);
        $this->syncImages($product, $images);

        return response()->json($product->load(['category', 'images']), 201);
    }

    public function show(Product $product): JsonResponse
    {
        return response()->json($product->load(['category', 'images', 'discounts']));
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $data = $this->validated($request, false);
        $data['slug'] ??= isset($data['name']) ? Str::slug($data['name']) : $product->slug;
        $images = $data['images'] ?? null;
        unset($data['images']);

        $product->update($data);

        if (is_array($images)) {
            $product->images()->delete();
            $this->syncImages($product, $images);
        }

        return response()->json($product->fresh(['category', 'images']));
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json(null, 204);
    }

    private function validated(Request $request, bool $creating = true): array
    {
        $required = $creating ? 'required' : 'sometimes';
        $productId = $request->route('product')?->id;

        return $request->validate([
            'category_id' => [$required, 'integer', 'exists:categories,id'],
            'sku' => [$required, 'string', 'max:80', 'unique:products,sku'.($productId ? ','.$productId : '')],
            'name' => [$required, 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:products,slug'.($productId ? ','.$productId : '')],
            'description' => ['nullable', 'string'],
            'brand' => ['nullable', 'string', 'max:120'],
            'unit' => ['sometimes', 'string', 'max:40'],
            'status' => ['sometimes', 'in:draft,active,archived'],
            'track_inventory' => ['sometimes', 'boolean'],
            'stock' => ['sometimes', 'integer', 'min:0'],
            'low_stock_threshold' => ['sometimes', 'integer', 'min:0'],
            'price_usd_cents' => ['nullable', 'integer', 'min:0'],
            'price_cup_cents' => ['nullable', 'integer', 'min:0'],
            'metadata' => ['nullable', 'array'],
            'images' => ['sometimes', 'array'],
            'images.*.url' => ['required_with:images', 'url'],
            'images.*.alt' => ['nullable', 'string', 'max:255'],
            'images.*.is_primary' => ['sometimes', 'boolean'],
            'images.*.sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);
    }

    private function syncImages(Product $product, array $images): void
    {
        foreach ($images as $image) {
            $product->images()->create($image);
        }
    }
}
