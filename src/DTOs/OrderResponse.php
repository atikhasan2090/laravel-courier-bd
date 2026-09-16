<?php

namespace Shipkit\CourierBD\DTOs;

use Shipkit\CourierBD\Enums\DeliveryStatus;

readonly class OrderResponse
{
    public function __construct(
        public string $consignmentId,
        public DeliveryStatus $status,
        public ?string $trackingUrl = null,
        public float $deliveryFee = 0.0,
        public float $codFee = 0.0,
        public string $courier = '',
        public ?string $merchantOrderId = null,
        public array $rawResponse = []
    ) {}

    public function getTotalFee(): float
    {
        return $this->deliveryFee + $this->codFee;
    }

    public function toArray(): array
    {
        return [
            'consignment_id' => $this->consignmentId,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'tracking_url' => $this->trackingUrl,
            'delivery_fee' => $this->deliveryFee,
            'cod_fee' => $this->codFee,
            'total_fee' => $this->getTotalFee(),
            'courier' => $this->courier,
            'merchant_order_id' => $this->merchantOrderId,
            'raw_response' => $this->rawResponse,
        ];
    }
}
