<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExchangeRate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExchangeRateController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(ExchangeRate::latest('valid_from')->paginate(25));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'base_currency' => ['required', 'in:USD'],
            'quote_currency' => ['required', 'in:CUP'],
            'rate' => ['required', 'numeric', 'min:0.0001'],
            'source' => ['nullable', 'string', 'max:120'],
            'valid_from' => ['nullable', 'date'],
        ]);

        ExchangeRate::query()
            ->where('base_currency', $data['base_currency'])
            ->where('quote_currency', $data['quote_currency'])
            ->whereNull('valid_to')
            ->update(['valid_to' => now()]);

        $data['valid_from'] ??= now();

        return response()->json(ExchangeRate::create($data), 201);
    }
}
