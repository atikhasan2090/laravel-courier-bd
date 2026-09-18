<?php

namespace Shipkit\CourierBD\Drivers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Shipkit\CourierBD\Concerns\LogsShipments;
use Shipkit\CourierBD\Contracts\CourierInterface;
use Shipkit\CourierBD\DTOs\OrderRequest;
use Shipkit\CourierBD\DTOs\OrderResponse;
use Shipkit\CourierBD\Enums\DeliveryStatus;
use Shipkit\CourierBD\Exceptions\CourierApiException;
use Shipkit\CourierBD\Exceptions\OrderCreationFailedException;

class RedxCourier implements CourierInterface
{
    use LogsShipments;

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
        $deliveryAreaId = (is_numeric($order->recipientZone) && (int) $order->recipientZone > 0)
            ? (int) $order->recipientZone
            : null;

        $payload = [
            'customer_name' => $order->recipientName,
            'customer_phone' => $order->getNormalizedPhone(),
            'delivery_area' => $order->recipientArea ?? $order->recipientCity ?? 'Dhaka',
            'delivery_area_id' => $deliveryAreaId,
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
                    'value' => (float) $order->amountToCollect,
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

        if (empty($trackingId)) {
            throw OrderCreationFailedException::make(
                $this->getName(),
                "Tracking ID missing in RedX response",
                $response->json() ?? [],
                $response->status()
            );
        }

        $orderResponse = new OrderResponse(
            consignmentId: $trackingId,
            status: DeliveryStatus::Pending,
            trackingUrl: "https://redx.com.bd/track-parcel?trackingId={$trackingId}",
            deliveryFee: (float) ($response->json('charge') ?? 0.0),
            codFee: (float) ($response->json('cod_charge') ?? 0.0),
            courier: $this->getName(),
            merchantOrderId: $order->merchantOrderId,
            rawResponse: $response->json() ?? []
        );

        return $this->recordShipment($order, $orderResponse);
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

        if ($response->failed()) {
            throw new CourierApiException(
                "RedX fee calculation failed: " . ($response->json('message') ?? $response->body()),
                $response->status(),
                $response->json() ?? []
            );
        }

        return (float) ($response->json('charge') ?? $response->json('total_charge') ?? 65.0);
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

    public function verifyWebhook(Request $request): bool
    {
        $secret = config('shipkit.couriers.redx.webhook_secret', '');
        if (empty($secret)) {
            return true;
        }

        $authHeader = $request->header('Authorization', '');
        if ($authHeader && str_replace('Bearer ', '', $authHeader) === $secret) {
            return true;
        }

        return $request->header('X-Redx-Secret') === $secret
            || $request->header('X-Webhook-Secret') === $secret
            || $request->query('token') === $secret;
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
            '' => DeliveryStatus::Unknown,
            default => DeliveryStatus::Processing,
        };
    }
}
