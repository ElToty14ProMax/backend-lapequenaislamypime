<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json($request->user()->addresses()->latest()->get());
    }

    public function store(Request $request): JsonResponse
    {
        $address = $request->user()->addresses()->create($this->validated($request));

        if ($address->is_default) {
            $request->user()->addresses()->whereKeyNot($address->id)->update(['is_default' => false]);
        }

        return response()->json($address, 201);
    }

    public function update(Request $request, Address $address): JsonResponse
    {
        abort_unless($address->user_id === $request->user()->id, 404);

        $address->update($this->validated($request, false));

        if ($address->is_default) {
            $request->user()->addresses()->whereKeyNot($address->id)->update(['is_default' => false]);
        }

        return response()->json($address->fresh());
    }

    public function destroy(Request $request, Address $address): JsonResponse
    {
        abort_unless($address->user_id === $request->user()->id, 404);
        $address->delete();

        return response()->json(null, 204);
    }

    private function validated(Request $request, bool $creating = true): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'label' => ['sometimes', 'string', 'max:80'],
            'recipient_name' => [$required, 'string', 'max:255'],
            'phone' => [$required, 'string', 'max:40'],
            'country' => ['sometimes', 'string', 'max:80'],
            'province' => [$required, 'string', 'max:120'],
            'municipality' => [$required, 'string', 'max:120'],
            'street' => [$required, 'string', 'max:255'],
            'between_streets' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:1000'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_default' => ['sometimes', 'boolean'],
        ]);
    }
}
