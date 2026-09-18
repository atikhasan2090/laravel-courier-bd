<?php

namespace Shipkit\CourierBD\Models;

use Illuminate\Database\Eloquent\Model;
use Shipkit\CourierBD\Enums\DeliveryStatus;

class Shipment extends Model
{
    protected $table = 'shipments';

    protected $fillable = [
        'merchant_order_id',
        'courier',
        'consignment_id',
        'status',
        'delivery_fee',
        'cod_fee',
        'total_fee',
        'tracking_url',
        'recipient_name',
        'recipient_phone',
        'recipient_address',
        'raw_response',
    ];

    protected $casts = [
        'status' => DeliveryStatus::class,
        'delivery_fee' => 'float',
        'cod_fee' => 'float',
        'total_fee' => 'float',
        'raw_response' => 'array',
    ];

    public function scopePending($query)
    {
        return $query->where('status', DeliveryStatus::Pending);
    }

    public function scopeInTransit($query)
    {
        return $query->where('status', DeliveryStatus::InTransit);
    }

    public function scopeDelivered($query)
    {
        return $query->where('status', DeliveryStatus::Delivered);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', DeliveryStatus::Cancelled);
    }

    public function scopeForMerchantOrder($query, string $orderId)
    {
        return $query->where('merchant_order_id', $orderId);
    }

    public function scopeForConsignment($query, string $consignmentId)
    {
        return $query->where('consignment_id', $consignmentId);
    }
}
