<?php

namespace Shipkit\CourierBD\Drivers;

use Illuminate\Support\Facades\Http;
use Shipkit\CourierBD\Contracts\CourierInterface;
use Shipkit\CourierBD\DTOs\OrderRequest;
use Shipkit\CourierBD\DTOs\OrderResponse;
use Shipkit\CourierBD\Enums\DeliveryStatus;
use Shipkit\CourierBD\Exceptions\CourierApiException;

class SteadfastCourier implements CourierInterface
{
    protected string $baseUrl;
    protected string $apiKey;
    protected string $secretKey;
    protected bool $sandbox;

    public function __construct(array $config)
    {
        $this->sandbox = $config['sandbox'] ?? false;
        $this->baseUrl = $config['base_url'] ?? 'https://portal.steadfast.com.bd/api/v1';
        $this->apiKey = $config['api_key'] ?? '';
        $this->secretKey = $config['secret_key'] ?? '';
    }

    public function getName(): string
    {
        return 'steadfast';
    }

    protected function http()
    {
        return Http::withHeaders([
            'Api-Key' => $this->apiKey,
            'Secret-Key' => $this->secretKey,
            'Content-Type' => 'application/json',
        ])->acceptJson()->asJson();
    }

    public function createOrder(OrderRequest $order): OrderResponse
    {
        $payload = [
            'invoice' => $order->merchantOrderId,
            'recipient_name' => $order->recipientName,
            'recipient_phone' => $order->recipientPhone,
            'recipient_address' => $order->recipientAddress,
            'cod_amount' => (float) $order->amountToCollect,
            'note' => $order->specialInstruction ?? '',
        ];

        $response = $this->http()->post("{$this->baseUrl}/create_order", $payload);

        if ($response->failed() || ($response->json('status') && $response->json('status') != 200)) {
            $msg = $response->json('message') ?? $response->json('errors') ?? $response->body();
            if (is_array($msg)) {
                $msg = json_encode($msg);
            }
            throw new CourierApiException(
                "Steadfast order creation failed: " . $msg,
                $response->status(),
                $response->json() ?? []
            );
        }

        $data = $response->json('consignment') ?? $response->json();
        $consignmentId = (string) ($data['consignment_id'] ?? $data['tracking_code'] ?? '');

        return new OrderResponse(
            consignmentId: $consignmentId,
            status: DeliveryStatus::Pending,
            trackingUrl: "https://steadfast.com.bd/t/{$consignmentId}",
            deliveryFee: (float) ($data['delivery_charge'] ?? $data['charge'] ?? 70.0),
            codFee: (float) ($data['cod_charge'] ?? 0.0),
            courier: $this->getName(),
            merchantOrderId: $order->merchantOrderId,
            rawResponse: $response->json() ?? []
        );
    }

    public function track(string $consignmentId): OrderResponse
    {
        $response = $this->http()->get("{$this->baseUrl}/status_by_cid/{$consignmentId}");

        if ($response->failed()) {
            throw new CourierApiException(
                "Steadfast tracking failed: " . ($response->json('message') ?? $response->body()),
                $response->status(),
                $response->json() ?? []
            );
        }

        $rawStatus = (string) ($response->json('delivery_status') ?? $response->json('status') ?? '');

        return new OrderResponse(
            consignmentId: $consignmentId,
            status: $this->mapStatus($rawStatus),
            trackingUrl: "https://steadfast.com.bd/t/{$consignmentId}",
            deliveryFee: (float) ($response->json('delivery_charge') ?? 0.0),
            codFee: (float) ($response->json('cod_charge') ?? 0.0),
            courier: $this->getName(),
            merchantOrderId: $response->json('invoice') ?? null,
            rawResponse: $response->json() ?? []
        );
    }

    public function cancelOrder(string $consignmentId): bool
    {
        // Steadfast order cancellation endpoint if available or status request
        $response = $this->http()->post("{$this->baseUrl}/cancel_order/{$consignmentId}");
        return $response->successful();
    }

    public function calculateFee(OrderRequest $order): float
    {
        // Steadfast pricing calculation based on location (Inside Dhaka = 60/70, Outside = 120/130)
        $isDhaka = false;
        if ($order->recipientCity && strcasecmp($order->recipientCity, 'dhaka') === 0) {
            $isDhaka = true;
        } elseif (stripos($order->recipientAddress, 'dhaka') !== false) {
            $isDhaka = true;
        }

        $baseFee = $isDhaka ? 70.0 : 130.0;
        $weightExtra = max(0, $order->itemWeight - 1.0) * 20.0;

        return $baseFee + $weightExtra;
    }

    public function checkCoverage(string $areaIdentifier): bool
    {
        return true; // Steadfast offers 64 districts nationwide coverage in Bangladesh
    }

    public function mapStatus(string $rawStatus): DeliveryStatus
    {
        return match (strtolower(trim($rawStatus))) {
            'pending' => DeliveryStatus::Pending,
            'in_review', 'delivered_approval_pending' => DeliveryStatus::Processing,
            'delivered' => DeliveryStatus::Delivered,
            'partial_delivered', 'partially_delivered' => DeliveryStatus::PartiallyDelivered,
            'cancelled', 'cancelled_approval_pending' => DeliveryStatus::Cancelled,
            'hold' => DeliveryStatus::Hold,
            default => DeliveryStatus::InTransit,
        };
    }
}
