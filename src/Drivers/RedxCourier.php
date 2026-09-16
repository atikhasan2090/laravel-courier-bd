<?php

namespace Shipkit\CourierBD\Drivers;

use Illuminate\Support\Facades\Http;
use Shipkit\CourierBD\Contracts\CourierInterface;
use Shipkit\CourierBD\DTOs\OrderRequest;
use Shipkit\CourierBD\DTOs\OrderResponse;
use Shipkit\CourierBD\Enums\DeliveryStatus;
use Shipkit\CourierBD\Exceptions\CourierApiException;

class RedxCourier implements CourierInterface
{
    protected string $baseUrl;
    protected string $apiToken;
    protected bool $sandbox;

    public function __construct(array $config)
    {
        $this->sandbox = $config['sandbox'] ?? true;
        $this->baseUrl = $config['base_url'] ?? ($this->sandbox
            ? 'https://sandbox.redx.com.bd/v1.0.0'
            : 'https://openapi.redx.com.bd/v1.0.0');
        $this->apiToken = $config['api_token'] ?? '';
    }

    public function getName(): string
    {
        return 'redx';
    }

    protected function http()
    {
        return Http::withHeaders([
            'API-ACCESS-TOKEN' => "Bearer {$this->apiToken}",
            'Authorization' => "Bearer {$this->apiToken}",
        ])->acceptJson()->asJson();
    }

    public function createOrder(OrderRequest $order): OrderResponse
    {
        $payload = [
            'customer_name' => $order->recipientName,
            'customer_phone' => $order->recipientPhone,
            'delivery_area' => $order->recipientArea ?? $order->recipientCity ?? 'Dhaka',
            'delivery_area_id' => $order->recipientZone ? (int) $order->recipientZone : null,
            'customer_address' => $order->recipientAddress,
            'merchant_invoice_id' => $order->merchantOrderId,
            'cash_collection_amount' => (float) $order->amountToCollect,
            'parcel_weight' => (float) ($order->itemWeight * 1000), // RedX expects weight in grams
            'instruction' => $order->specialInstruction ?? '',
            'value' => (float) $order->amountToCollect,
            'parcel_details_json' => [
                [
                    'name' => $order->itemDescription ?? 'Item',
                    'quantity' => $order->itemQuantity,
                    'category' => 'General',
                ]
            ],
        ];

        $response = $this->http()->post("{$this->baseUrl}/parcels", $payload);

        if ($response->failed()) {
            throw new CourierApiException(
                "RedX order creation failed: " . ($response->json('message') ?? $response->body()),
                $response->status(),
                $response->json() ?? []
            );
        }

        $trackingId = (string) ($response->json('tracking_id') ?? $response->json('parcel_id') ?? '');

        return new OrderResponse(
            consignmentId: $trackingId,
            status: DeliveryStatus::Pending,
            trackingUrl: "https://redx.com.bd/track-parcel?trackingId={$trackingId}",
            deliveryFee: (float) ($response->json('charge') ?? 0.0),
            codFee: (float) ($response->json('cod_charge') ?? 0.0),
            courier: $this->getName(),
            merchantOrderId: $order->merchantOrderId,
            rawResponse: $response->json() ?? []
        );
    }

    public function track(string $consignmentId): OrderResponse
    {
        $response = $this->http()->get("{$this->baseUrl}/parcels/{$consignmentId}");

        if ($response->failed()) {
            throw new CourierApiException(
                "RedX tracking failed: " . ($response->json('message') ?? $response->body()),
                $response->status(),
                $response->json() ?? []
            );
        }

        $data = $response->json('parcel') ?? $response->json();
        $rawStatus = (string) ($data['status'] ?? '');

        return new OrderResponse(
            consignmentId: $consignmentId,
            status: $this->mapStatus($rawStatus),
            trackingUrl: "https://redx.com.bd/track-parcel?trackingId={$consignmentId}",
            deliveryFee: (float) ($data['charge'] ?? 0.0),
            codFee: (float) ($data['cod_charge'] ?? 0.0),
            courier: $this->getName(),
            merchantOrderId: $data['merchant_invoice_id'] ?? null,
            rawResponse: $response->json() ?? []
        );
    }

    public function cancelOrder(string $consignmentId): bool
    {
        $response = $this->http()->post("{$this->baseUrl}/parcels/{$consignmentId}/cancel");
        return $response->successful();
    }

    public function calculateFee(OrderRequest $order): float
    {
        $payload = [
            'delivery_area' => $order->recipientArea ?? $order->recipientCity ?? 'Dhaka',
            'parcel_weight' => (float) ($order->itemWeight * 1000), // grams
            'cash_collection_amount' => (float) $order->amountToCollect,
        ];

        $response = $this->http()->post("{$this->baseUrl}/charge-calculator", $payload);

        if ($response->successful()) {
            return (float) ($response->json('charge') ?? $response->json('total_charge') ?? 60.0);
        }

        return 65.0 + (max(0, $order->itemWeight - 1.0) * 15.0);
    }

    public function checkCoverage(string $areaIdentifier): bool
    {
        $response = $this->http()->get("{$this->baseUrl}/areas");
        if ($response->successful()) {
            $areas = $response->json('areas') ?? [];
            foreach ($areas as $area) {
                if (isset($area['name']) && strcasecmp($area['name'], $areaIdentifier) === 0) {
                    return true;
                }
            }
        }
        return true;
    }

    public function mapStatus(string $rawStatus): DeliveryStatus
    {
        return match (strtolower(trim($rawStatus))) {
            'created', 'pickup-pending', 'ready-for-pickup' => DeliveryStatus::Pending,
            'in-transit', 'out-for-delivery', 'hub-received' => DeliveryStatus::InTransit,
            'delivered' => DeliveryStatus::Delivered,
            'partial-delivered', 'partially-delivered' => DeliveryStatus::PartiallyDelivered,
            'cancelled', 'returned', 'return-in-transit' => DeliveryStatus::Cancelled,
            'hold' => DeliveryStatus::Hold,
            default => DeliveryStatus::Processing,
        };
    }
}
