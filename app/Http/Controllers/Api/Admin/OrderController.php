<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Mail\OrderStatusChangedMail;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(Order::with(['user', 'invoice'])
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate((int) $request->integer('per_page', 25)));
    }

    public function show(Order $order): JsonResponse
    {
        return response()->json($order->load(['user', 'address', 'items', 'payments', 'invoice', 'histories.user']));
    }

    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:pending,paid,preparing,out_for_delivery,delivered,cancelled,refunded'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $from = $order->status;
        $order->update(['status' => $data['status']]);
        $order->histories()->create([
            'user_id' => $request->user()->id,
            'from_status' => $from,
            'to_status' => $data['status'],
            'comment' => $data['comment'] ?? null,
        ]);

        Mail::to($order->user)->queue(new OrderStatusChangedMail($order->fresh(['user']), $from));

        return response()->json($order->fresh(['histories']));
    }
}
