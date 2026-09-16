# Laravel Courier BD 📦

[![Latest Version on Packagist](https://img.shields.io/packagist/v/shipkit-bd/laravel-courier-bd.svg?style=flat-square)](https://packagist.org/packages/shipkit-bd/laravel-courier-bd)
[![Total Downloads](https://img.shields.io/packagist/dt/shipkit-bd/laravel-courier-bd.svg?style=flat-square)](https://packagist.org/packages/shipkit-bd/laravel-courier-bd)
[![License](https://img.shields.io/packagist/l/shipkit-bd/laravel-courier-bd.svg?style=flat-square)](LICENSE)

A unified Laravel package that provides a single, consistent driver-based API interface for integrating multiple Bangladeshi courier services starting with **Pathao**, **RedX**, and **Steadfast**.

---

## Key Features

- 🚚 **One Interface, Multiple Couriers**: Unified methods (`createOrder`, `track`, `cancelOrder`, `calculateFee`, `checkCoverage`) for all Bangladeshi couriers.
- 💡 **Fee Comparison Engine**: `Courier::compareFees($order)` dynamically queries all enabled couriers and sorts them by price from cheapest to most expensive.
- 🔄 **Status Normalization**: Maps diverse courier statuses (`Delivered`, `in-transit`, `partial_delivered`, etc.) into a single `DeliveryStatus` enum.
- 🔔 **Built-in Webhooks & Events**: Automatic webhook listener endpoints (`/shipkit/webhooks/{courier}`) that dispatch `ShipmentStatusUpdated` and `ShipmentDelivered` Laravel events.
- 📊 **Shipment History Logging**: Optional Eloquent database logging of all package shipments.

---

## Installation

Install the package via Composer:

```bash
composer require shipkit-bd/laravel-courier-bd
```

Publish the configuration file and database migrations using the artisan installer:

```bash
php artisan shipkit:install
```

Run database migrations (optional, if automatic shipment logging is enabled):

```bash
php artisan migrate
```

---

## Environment Configuration

Add your courier credentials to your `.env` file:

```env
COURIER_DEFAULT_DRIVER=steadfast
COURIER_AUTO_LOG=true

# Pathao Courier Credentials
PATHAO_SANDBOX=true
PATHAO_CLIENT_ID=your_client_id
PATHAO_CLIENT_SECRET=your_client_secret
PATHAO_USERNAME=your_merchant_username
PATHAO_PASSWORD=your_merchant_password
PATHAO_STORE_ID=your_store_id

# RedX Courier Credentials
REDX_SANDBOX=true
REDX_API_TOKEN=your_redx_api_token

# Steadfast Courier Credentials
STEADFAST_SANDBOX=false
STEADFAST_API_KEY=your_steadfast_api_key
STEADFAST_SECRET_KEY=your_steadfast_secret_key
```

---

## Usage

### 1. Creating a Shipment Order

Using the default driver:

```php
use Shipkit\CourierBD\Facades\Courier;
use Shipkit\CourierBD\DTOs\OrderRequest;

$order = new OrderRequest(
    merchantOrderId: 'INV-2026-001',
    recipientName: 'Abul Hossain',
    recipientPhone: '01711223344',
    recipientAddress: 'House 12, Road 5, Dhanmondi, Dhaka',
    recipientCity: 'Dhaka',
    amountToCollect: 1500.00, // COD Amount
    itemWeight: 1.0,           // kg
    itemDescription: 'Cotton Polo Shirt x 2',
    specialInstruction: 'Handle with care'
);

// Create shipment with default driver
$response = Courier::createOrder($order);

echo $response->consignmentId;  // e.g. ST100200
echo $response->status->label(); // Pending
echo $response->trackingUrl;    // https://steadfast.com.bd/t/ST100200
```

Explicitly specifying a driver:

```php
// Use Pathao
$pathaoResponse = Courier::via('pathao')->createOrder($order);

// Use RedX
$redxResponse = Courier::via('redx')->createOrder($order);
```

---

### 2. Tracking a Shipment

```php
$tracking = Courier::via('pathao')->track('PTH123456');

if ($tracking->status->isFinal()) {
    echo "Delivery Completed or Cancelled: " . $tracking->status->label();
}
```

---

### 3. Fee Comparison Feature ⭐

Find the cheapest courier for an order automatically:

```php
$rates = Courier::compareFees($order);

/*
Returns sorted array:
[
    [
        'courier' => 'pathao',
        'fee' => 60.0,
        'available' => true,
        'error' => null
    ],
    [
        'courier' => 'steadfast',
        'fee' => 70.0,
        'available' => true,
        'error' => null
    ],
    [
        'courier' => 'redx',
        'fee' => 85.0,
        'available' => true,
        'error' => null
    ]
]
*/

// Dispatch order with cheapest courier automatically!
$cheapestCourier = $rates[0]['courier'];
$response = Courier::via($cheapestCourier)->createOrder($order);
```

---

### 4. Webhooks & Events

The package automatically exposes webhook routes at `/shipkit/webhooks/{courier}` (e.g. `/shipkit/webhooks/pathao`, `/shipkit/webhooks/redx`, `/shipkit/webhooks/steadfast`).

Listen to delivery events in your `EventServiceProvider` or listeners:

```php
use Shipkit\CourierBD\Events\ShipmentStatusUpdated;
use Shipkit\CourierBD\Events\ShipmentDelivered;

Event::listen(ShipmentStatusUpdated::class, function (ShipmentStatusUpdated $event) {
    logger("Consignment {$event->consignmentId} status changed to {$event->newStatus->label()}");
});

Event::listen(ShipmentDelivered::class, function (ShipmentDelivered $event) {
    // Mark order as completed in your app
});
```

---

## Courier Feature Matrix

| Feature | Pathao | RedX | Steadfast |
| :--- | :---: | :---: | :---: |
| Order Creation | ✅ | ✅ | ✅ |
| Parcel Tracking | ✅ | ✅ | ✅ |
| Order Cancellation | ✅ | ✅ | ✅ |
| Dynamic Fee Calculation | ✅ | ✅ | ✅ |
| Webhook Support | ✅ | ✅ | ✅ |
| Nationwide Coverage | 64 Districts | 64 Districts | 64 Districts |

---

## Testing

Run tests with PHPUnit:

```bash
vendor/bin/phpunit
```

---

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
