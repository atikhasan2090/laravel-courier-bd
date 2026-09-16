<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Shipkit\CourierBD\DTOs\OrderRequest;
use Shipkit\CourierBD\Facades\Courier;

class CheckoutController extends Controller
{
    /**
     * Get live courier rates comparison during checkout.
     */
    public function getCourierRates(Request $request)
    {
        $request->validate([
            'address' => 'required|string',
            'city' => 'required|string',
            'cod_amount' => 'required|numeric',
            'weight' => 'nullable|numeric',
        ]);

        $order = new OrderRequest(
            merchantOrderId: 'TEMP-' . time(),
            recipientName: 'Customer',
            recipientPhone: '01700000000',
            recipientAddress: $request->input('address'),
            recipientCity: $request->input('city'),
            amountToCollect: (float) $request->input('cod_amount'),
            itemWeight: (float) ($request->input('weight') ?? 1.0)
        );

        $rates = Courier::compareFees($order);

        return response()->json([
            'status' => 'success',
            'rates' => $rates,
        ]);
    }

    /**
     * Dispatch order using selected or auto-selected courier.
     */
    public function placeOrder(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'required|string',
            'name' => 'required|string',
            'phone' => 'required|string',
            'address' => 'required|string',
            'city' => 'required|string',
            'amount' => 'required|numeric',
            'courier' => 'nullable|string', // optional, defaults to auto-cheapest
        ]);

        $order = new OrderRequest(
            merchantOrderId: $validated['order_id'],
            recipientName: $validated['name'],
            recipientPhone: $validated['phone'],
            recipientAddress: $validated['address'],
            recipientCity: $validated['city'],
            amountToCollect: (float) $validated['amount'],
            itemWeight: 1.0
        );

        $courierToUse = $validated['courier'];

        if (!$courierToUse) {
            $rates = Courier::compareFees($order);
            $courierToUse = $rates[0]['courier'] ?? 'steadfast';
        }

        $shipmentResponse = Courier::via($courierToUse)->createOrder($order);

        return response()->json([
            'message' => 'Order placed and shipped successfully!',
            'courier_used' => $courierToUse,
            'consignment_id' => $shipmentResponse->consignmentId,
            'tracking_url' => $shipmentResponse->trackingUrl,
            'status' => $shipmentResponse->status->label(),
        ]);
    }
}
