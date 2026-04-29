<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Services\MoneyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function __construct(private readonly MoneyService $money)
    {
    }

    public function categories(): JsonResponse
    {
        return response()->json(Category::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->with(['children' => fn ($query) => $query->where('is_active', true)])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get());
    }

    public function products(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'currency' => ['nullable', 'in:USD,CUP'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'sort' => ['nullable', 'in:name,price_asc,price_desc,newest'],
        ]);

        $currency = $data['currency'] ?? 'USD';
        $priceColumn = $currency === 'USD' ? 'price_usd_cents' : 'price_cup_cents';

        $products = Product::query()
            ->active()
            ->with(['category', 'images'])
            ->when($data['q'] ?? null, function ($query, string $term): void {
                $query->where(function ($inner) use ($term): void {
                    $inner->where('name', 'ilike', "%{$term}%")
                        ->orWhere('description', 'ilike', "%{$term}%")
                        ->orWhere('sku', 'ilike', "%{$term}%");
                });
            })
            ->when($data['category_id'] ?? null, fn ($query, $categoryId) => $query->where('category_id', $categoryId))
            ->when(isset($data['min_price']), fn ($query) => $query->where($priceColumn, '>=', (int) round(request('min_price') * 100)))
            ->when(isset($data['max_price']), fn ($query) => $query->where($priceColumn, '<=', (int) round(request('max_price') * 100)))
            ->when(($data['sort'] ?? null) === 'name', fn ($query) => $query->orderBy('name'))
            ->when(($data['sort'] ?? null) === 'price_asc', fn ($query) => $query->orderBy($priceColumn))
            ->when(($data['sort'] ?? null) === 'price_desc', fn ($query) => $query->orderByDesc($priceColumn))
            ->when(($data['sort'] ?? null) === 'newest' || ! isset($data['sort']), fn ($query) => $query->latest())
            ->paginate((int) $request->integer('per_page', 15));

        $products->getCollection()->transform(fn (Product $product) => [
            ...$product->toArray(),
            'display_price_cents' => $this->money->priceFor($product, $currency),
            'display_currency' => $currency,
        ]);

        return response()->json($products);
    }

    public function product(Product $product, Request $request): JsonResponse
    {
        abort_unless($product->status === 'active', 404);

        $currency = $request->query('currency', 'USD');

        return response()->json([
            ...$product->load(['category', 'images', 'discounts'])->toArray(),
            'display_price_cents' => $this->money->priceFor($product, $currency),
            'display_currency' => $currency,
        ]);
    }
}
