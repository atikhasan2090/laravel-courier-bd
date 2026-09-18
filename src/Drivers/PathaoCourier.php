<?php

namespace Shipkit\CourierBD\Drivers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Shipkit\CourierBD\Concerns\LogsShipments;
use Shipkit\CourierBD\Contracts\CourierInterface;
use Shipkit\CourierBD\DTOs\OrderRequest;
use Shipkit\CourierBD\DTOs\OrderResponse;
use Shipkit\CourierBD\Enums\DeliveryStatus;
use Shipkit\CourierBD\Exceptions\CourierApiException;
use Shipkit\CourierBD\Exceptions\OrderCreationFailedException;

class PathaoCourier implements CourierInterface
{
    use LogsShipments;

    protected string $baseUrl;
    protected string $clientId;
    protected string $clientSecret;
    protected string $username;
    protected string $password;
    protected int $storeId;
    protected bool $sandbox;

    public function __construct(array $config)
    {
        $this->sandbox = $config['sandbox'] ?? true;
        $this->baseUrl = $config['base_url'] ?? ($this->sandbox
            ? 'https://courier-api-sandbox.pathao.com'
            : 'https://api.pathao.com');
        $this->clientId = $config['client_id'] ?? '';
        $this->clientSecret = $config['client_secret'] ?? '';
        $this->username = $config['username'] ?? '';
        $this->password = $config['password'] ?? '';
        $this->storeId = (int) ($config['store_id'] ?? 0);
    }

    public function getName(): string
    {
        return 'pathao';
    }

    protected function getAccessToken(): string
    {
        $cacheKey = 'shipkit_pathao_token_' . md5($this->clientId . $this->username);

        return Cache::remember($cacheKey, 82800, function () { // Cache for ~23 hours
            $response = Http::asJson()->post("{$this->baseUrl}/aladdin/api/v1/issue-token", [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'username' => $this->username,
                'password' => $this->password,
                'grant_type' => 'password',
            ]);

            if ($response->failed()) {
                throw new CourierApiException(
                    "Pathao authentication failed: " . ($response->json('message') ?? $response->body()),
                    $response->status(),
                    $response->json() ?? []
                );
            }

            $token = $response->json('access_token');
            if (empty($token)) {
                throw new CourierApiException(
                    "Pathao authentication failed: No access token received from API",
                    $response->status(),
                    $response->json() ?? []
                );
            }

            return $token;
        });
    }

    protected function http()
    {
        return Http::withToken($this->getAccessToken())
            ->acceptJson()
            ->asJson();
    }

    public function createOrder(OrderRequest $order): OrderResponse
    {
        $recipientCity = is_numeric($order->recipientCity) ? (int) $order->recipientCity : 1;
        $recipientZone = is_numeric($order->recipientZone) ? (int) $order->recipientZone : 1;
        $recipientArea = (is_numeric($order->recipientArea) && (int) $order->recipientArea > 0) ? (int) $order->recipientArea : null;

        $payload = [
            'store_id' => $this->storeId,
            'merchant_order_id' => $order->merchantOrderId,
            'recipient_name' => $order->recipientName,
            'recipient_phone' => $order->getNormalizedPhone(),
            'recipient_address' => $order->recipientAddress,
            'recipient_city' => $recipientCity,
            'recipient_zone' => $recipientZone,
            'recipient_area' => $recipientArea,
            'delivery_type' => 48, // 48 Hours Normal Delivery
            'item_type' => 2, // Parcel
            'special_instruction' => $order->specialInstruction ?? '',
            'item_quantity' => $order->itemQuantity,
            'item_weight' => $order->itemWeight,
            'amount_to_collect' => (int) $order->amountToCollect,
            'item_description' => $order->itemDescription,
        ];

        $response = $this->http()->post("{$this->baseUrl}/aladdin/api/v1/orders", $payload);

        if ($response->failed()) {
            throw new CourierApiException(
                "Pathao order creation failed: " . ($response->json('message') ?? $response->body()),
                $response->status(),
                $response->json() ?? []
            );
        }

        $data = $response->json('data') ?? $response->json();
        $consignmentId = (string) ($data['consignment_id'] ?? '');

        if (empty($consignmentId)) {
            throw OrderCreationFailedException::make(
                $this->getName(),
                "Consignment ID missing in Pathao response",
                $response->json() ?? [],
                $response->status()
            );
        }

        $orderResponse = new OrderResponse(
            consignmentId: $consignmentId,
            status: DeliveryStatus::Pending,
            trackingUrl: "https://pathao.com/courier/tracking?consignment_id={$consignmentId}",
            deliveryFee: (float) ($data['delivery_fee'] ?? 0.0),
            codFee: (float) ($data['cod_fee'] ?? 0.0),
            courier: $this->getName(),
            merchantOrderId: $order->merchantOrderId,
            rawResponse: $response->json() ?? []
        );

        return $this->recordShipment($order, $orderResponse);
    }

    public function track(string $consignmentId): OrderResponse
    {
        $response = $this->http()->get("{$this->baseUrl}/aladdin/api/v1/orders/{$consignmentId}/info");

        if ($response->failed()) {
            throw new CourierApiException(
                "Pathao tracking failed: " . ($response->json('message') ?? $response->body()),
                $response->status(),
                $response->json() ?? []
            );
        }

        $data = $response->json('data') ?? $response->json();
        $rawStatus = (string) ($data['order_status'] ?? $data['order_status_slug'] ?? '');

        return new OrderResponse(
            consignmentId: $consignmentId,
            status: $this->mapStatus($rawStatus),
            trackingUrl: "https://pathao.com/courier/tracking?consignment_id={$consignmentId}",
            deliveryFee: (float) ($data['delivery_fee'] ?? 0.0),
            codFee: (float) ($data['cod_fee'] ?? 0.0),
            courier: $this->getName(),
            merchantOrderId: $data['merchant_order_id'] ?? null,
            rawResponse: $response->json() ?? []
        );
    }

    public function cancelOrder(string $consignmentId): bool
    {
        // Pathao endpoint for cancellation
        $response = $this->http()->post("{$this->baseUrl}/aladdin/api/v1/orders/{$consignmentId}/cancel");

        return $response->successful();
    }

    public function calculateFee(OrderRequest $order): float
    {
        $payload = [
            'store_id' => $this->storeId,
            'item_type' => 2,
            'delivery_type' => 48,
            'item_weight' => $order->itemWeight,
            'recipient_city' => is_numeric($order->recipientCity) ? (int) $order->recipientCity : 1,
            'recipient_zone' => is_numeric($order->recipientZone) ? (int) $order->recipientZone : 1,
            'amount_to_collect' => (int) $order->amountToCollect,
        ];

        $response = $this->http()->post("{$this->baseUrl}/aladdin/api/v1/merchant/price-plan", $payload);

        if ($response->failed()) {
            throw new CourierApiException(
                "Pathao fee calculation failed: " . ($response->json('message') ?? $response->body()),
                $response->status(),
                $response->json() ?? []
            );
        }

        $data = $response->json('data') ?? [];
        return (float) ($data['estimated_price'] ?? $data['final_price'] ?? 60.0);
    }

    public function checkCoverage(string $areaIdentifier): bool
    {
        // Query city/zone list or check coverage
        $response = $this->http()->get("{$this->baseUrl}/aladdin/api/v1/cities");
        if ($response->successful()) {
            $cities = $response->json('data') ?? [];
            foreach ($cities as $city) {
                if (strcasecmp((string) $city['city_id'], $areaIdentifier) === 0 || strcasecmp($city['city_name'], $areaIdentifier) === 0) {
                    return true;
                }
            }
        }
        return true; // Default fallback to true for general area identifiers
    }

    public function verifyWebhook(Request $request): bool
    {
        $secret = config('shipkit.couriers.pathao.webhook_secret', '');
        if (empty($secret)) {
            return true;
        }

        $authHeader = $request->header('Authorization', '');
        if ($authHeader && str_replace('Bearer ', '', $authHeader) === $secret) {
            return true;
        }

        $signature = $request->header('X-PATHAO-Signature') ?? $request->header('X-Pathao-Signature');
        if ($signature) {
            $expected = hash_hmac('sha256', $request->getContent(), $secret);
            return hash_equals($expected, $signature);
        }

        return $request->header('X-Webhook-Secret') === $secret
            || $request->query('token') === $secret;
    }

    public function mapStatus(string $rawStatus): DeliveryStatus
    {
        return match (strtolower(trim($rawStatus))) {
            'pending', 'order_created' => DeliveryStatus::Pending,
            'in_transit', 'dispatched', 'at_sorting_hub', 'out_for_delivery', 'picked_up', 'assigned_for_pickup' => DeliveryStatus::InTransit,
            'delivered' => DeliveryStatus::Delivered,
            'partial_delivery', 'partially_delivered' => DeliveryStatus::PartiallyDelivered,
            'pickup_cancelled', 'cancelled', 'order_cancelled', 'returned', 'return', 'returned_to_merchant' => DeliveryStatus::Cancelled,
            'on_hold', 'hold' => DeliveryStatus::Hold,
            '' => DeliveryStatus::Unknown,
            default => DeliveryStatus::Processing,
        };
    }
}
