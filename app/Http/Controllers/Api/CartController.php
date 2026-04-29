<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Services\MoneyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    public function __construct(private readonly MoneyService $money)
    {
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json($this->activeCart($request)->load('items.product.images'));
    }

    public function add(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'currency' => ['nullable', 'in:USD,CUP'],
        ]);

        $product = Product::active()->findOrFail($data['product_id']);
        $cart = $this->activeCart($request, $data['currency'] ?? 'USD');
        $currentQuantity = (int) $cart->items()->where('product_id', $product->id)->value('quantity');
        $newQuantity = $currentQuantity + $data['quantity'];

        if ($product->track_inventory && $product->stock < $newQuantity) {
            throw ValidationException::withMessages(['quantity' => 'No hay stock suficiente.']);
        }

        $price = $this->money->priceFor($product, $cart->currency);

        $item = CartItem::updateOrCreate(
            ['cart_id' => $cart->id, 'product_id' => $product->id],
            [
                'quantity' => $newQuantity,
                'unit_price_cents' => $price,
                'currency' => $cart->currency,
            ]
        );

        return response()->json($item->load('product.images'), 201);
    }

    public function update(Request $request, CartItem $cartItem): JsonResponse
    {
        abort_unless($cartItem->cart->user_id === $request->user()->id, 404);

        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1']]);
        $product = $cartItem->product;

        if ($product->track_inventory && $product->stock < $data['quantity']) {
            throw ValidationException::withMessages(['quantity' => 'No hay stock suficiente.']);
        }

        $cartItem->update($data);

        return response()->json($cartItem->fresh('product.images'));
    }

    public function remove(Request $request, CartItem $cartItem): JsonResponse
    {
        abort_unless($cartItem->cart->user_id === $request->user()->id, 404);
        $cartItem->delete();

        return response()->json(null, 204);
    }

    public function clear(Request $request): JsonResponse
    {
        $this->activeCart($request)->items()->delete();

        return response()->json(null, 204);
    }

    private function activeCart(Request $request, string $currency = 'USD'): Cart
    {
        return Cart::firstOrCreate(
            ['user_id' => $request->user()->id, 'status' => 'active'],
            ['currency' => strtoupper($currency)]
        );
    }
}
