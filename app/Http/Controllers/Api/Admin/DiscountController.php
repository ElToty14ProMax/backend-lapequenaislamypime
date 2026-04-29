<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Discount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiscountController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(Discount::with(['product', 'category'])->latest()->paginate((int) $request->integer('per_page', 25)));
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(Discount::create($this->validated($request)), 201);
    }

    public function show(Discount $discount): JsonResponse
    {
        return response()->json($discount->load(['product', 'category']));
    }

    public function update(Request $request, Discount $discount): JsonResponse
    {
        $discount->update($this->validated($request, false));

        return response()->json($discount->fresh(['product', 'category']));
    }

    public function destroy(Discount $discount): JsonResponse
    {
        $discount->delete();

        return response()->json(null, 204);
    }

    private function validated(Request $request, bool $creating = true): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'code' => ['nullable', 'string', 'max:80', 'unique:discounts,code'.($creating ? '' : ','.$request->route('discount')?->id)],
            'name' => [$required, 'string', 'max:255'],
            'type' => [$required, 'in:percent,fixed'],
            'value' => [$required, 'numeric', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
