<?php

namespace Shipkit\CourierBD;

use Illuminate\Support\Manager;
use Shipkit\CourierBD\Contracts\CourierInterface;
use Shipkit\CourierBD\Drivers\PathaoCourier;
use Shipkit\CourierBD\Drivers\RedxCourier;
use Shipkit\CourierBD\Drivers\SteadfastCourier;
use Shipkit\CourierBD\DTOs\OrderRequest;
use Shipkit\CourierBD\Exceptions\CourierNotSupportedException;

class CourierManager extends Manager implements CourierInterface
{
    public function getDefaultDriver()
    {
        return $this->config->get('shipkit.default', 'steadfast');
    }

    public function via(?string $driver = null): CourierInterface
    {
        return $this->driver($driver);
    }

    protected function createPathaoDriver(): CourierInterface
    {
        $config = $this->config->get('shipkit.couriers.pathao', []);
        return new PathaoCourier($config);
    }

    protected function createRedxDriver(): CourierInterface
    {
        $config = $this->config->get('shipkit.couriers.redx', []);
        return new RedxCourier($config);
    }

    protected function createSteadfastDriver(): CourierInterface
    {
        $config = $this->config->get('shipkit.couriers.steadfast', []);
        return new SteadfastCourier($config);
    }

    protected function createDriver($driver)
    {
        try {
            return parent::createDriver($driver);
        } catch (\InvalidArgumentException $e) {
            throw CourierNotSupportedException::make($driver);
        }
    }

    /**
     * Compare fees across all enabled couriers for a given order request.
     * Returns an array sorted by fee from lowest to highest.
     */
    public function compareFees(OrderRequest $order): array
    {
        $enabledCouriers = $this->config->get('shipkit.enabled_couriers', ['pathao', 'redx', 'steadfast']);
        $comparison = [];

        foreach ($enabledCouriers as $driverName) {
            try {
                $driver = $this->driver($driverName);
                $fee = $driver->calculateFee($order);
                $comparison[] = [
                    'courier' => $driverName,
                    'fee' => $fee,
                    'available' => true,
                    'error' => null,
                ];
            } catch (\Throwable $e) {
                $comparison[] = [
                    'courier' => $driverName,
                    'fee' => null,
                    'available' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        usort($comparison, function ($a, $b) {
            if ($a['fee'] === null && $b['fee'] === null) return 0;
            if ($a['fee'] === null) return 1;
            if ($b['fee'] === null) return -1;
            return $a['fee'] <=> $b['fee'];
        });

        return $comparison;
    }

    // Proxy CourierInterface calls to default driver
    public function createOrder(OrderRequest $order): DTOs\OrderResponse
    {
        return $this->driver()->createOrder($order);
    }

    public function track(string $consignmentId): DTOs\OrderResponse
    {
        return $this->driver()->track($consignmentId);
    }

    public function cancelOrder(string $consignmentId): bool
    {
        return $this->driver()->cancelOrder($consignmentId);
    }

    public function calculateFee(OrderRequest $order): float
    {
        return $this->driver()->calculateFee($order);
    }

    public function checkCoverage(string $areaIdentifier): bool
    {
        return $this->driver()->checkCoverage($areaIdentifier);
    }

    public function mapStatus(string $rawStatus): Enums\DeliveryStatus
    {
        return $this->driver()->mapStatus($rawStatus);
    }

    public function verifyWebhook(\Illuminate\Http\Request $request): bool
    {
        return $this->driver()->verifyWebhook($request);
    }

    public function getName(): string
    {
        return $this->driver()->getName();
    }
}
