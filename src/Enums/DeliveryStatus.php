<?php

namespace Shipkit\CourierBD\Enums;

enum DeliveryStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case PartiallyDelivered = 'partially_delivered';
    case Cancelled = 'cancelled';
    case Hold = 'hold';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Processing => 'Processing',
            self::InTransit => 'In Transit',
            self::Delivered => 'Delivered',
            self::PartiallyDelivered => 'Partially Delivered',
            self::Cancelled => 'Cancelled',
            self::Hold => 'On Hold',
            self::Unknown => 'Unknown',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Delivered, self::PartiallyDelivered, self::Cancelled]);
    }
}
