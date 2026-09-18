<?php

namespace Shipkit\CourierBD\DTOs;

readonly class OrderRequest
{
    public string $recipientPhone;

    public function __construct(
        public string $merchantOrderId,
        public string $recipientName,
        string $recipientPhone,
        public string $recipientAddress,
        public ?string $recipientCity = null,
        public ?string $recipientZone = null,
        public ?string $recipientArea = null,
        public float $amountToCollect = 0.0,
        public float $itemWeight = 0.5,
        public ?string $itemDescription = 'General Package',
        public int $itemQuantity = 1,
        public ?string $specialInstruction = null,
        public array $extraData = []
    ) {
        $this->recipientPhone = self::normalizePhoneNumber($recipientPhone);
    }

    public static function normalizePhoneNumber(string $phone): string
    {
        $cleaned = preg_replace('/[^\d]/', '', $phone);

        if (str_starts_with($cleaned, '8801')) {
            $cleaned = substr($cleaned, 2);
        } elseif (str_starts_with($cleaned, '1') && strlen($cleaned) === 10) {
            $cleaned = '0' . $cleaned;
        }

        return $cleaned;
    }

    public function getNormalizedPhone(): string
    {
        return self::normalizePhoneNumber($this->recipientPhone);
    }

    public function isInsideDhaka(): bool
    {
        if ($this->recipientCity && (strcasecmp($this->recipientCity, 'dhaka') === 0 || $this->recipientCity === '1')) {
            return true;
        }

        return stripos($this->recipientAddress, 'dhaka') !== false;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            merchantOrderId: (string) ($data['merchant_order_id'] ?? $data['order_id'] ?? ''),
            recipientName: (string) ($data['recipient_name'] ?? $data['name'] ?? ''),
            recipientPhone: self::normalizePhoneNumber((string) ($data['recipient_phone'] ?? $data['phone'] ?? '')),
            recipientAddress: (string) ($data['recipient_address'] ?? $data['address'] ?? ''),
            recipientCity: isset($data['recipient_city']) ? (string) $data['recipient_city'] : (isset($data['city']) ? (string) $data['city'] : null),
            recipientZone: isset($data['recipient_zone']) ? (string) $data['recipient_zone'] : (isset($data['zone']) ? (string) $data['zone'] : null),
            recipientArea: isset($data['recipient_area']) ? (string) $data['recipient_area'] : (isset($data['area']) ? (string) $data['area'] : null),
            amountToCollect: (float) ($data['amount_to_collect'] ?? $data['amount'] ?? $data['cod'] ?? 0),
            itemWeight: (float) ($data['item_weight'] ?? $data['weight'] ?? 0.5),
            itemDescription: $data['item_description'] ?? $data['description'] ?? 'General Package',
            itemQuantity: (int) ($data['item_quantity'] ?? $data['quantity'] ?? 1),
            specialInstruction: $data['special_instruction'] ?? $data['instruction'] ?? null,
            extraData: $data['extra_data'] ?? []
        );
    }

    public function toArray(): array
    {
        return [
            'merchant_order_id' => $this->merchantOrderId,
            'recipient_name' => $this->recipientName,
            'recipient_phone' => $this->recipientPhone,
            'recipient_address' => $this->recipientAddress,
            'recipient_city' => $this->recipientCity,
            'recipient_zone' => $this->recipientZone,
            'recipient_area' => $this->recipientArea,
            'amount_to_collect' => $this->amountToCollect,
            'item_weight' => $this->itemWeight,
            'item_description' => $this->itemDescription,
            'item_quantity' => $this->itemQuantity,
            'special_instruction' => $this->specialInstruction,
            'extra_data' => $this->extraData,
        ];
    }
}
