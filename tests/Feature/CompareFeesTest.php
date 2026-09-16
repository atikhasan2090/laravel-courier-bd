<?php

namespace Shipkit\CourierBD\Tests\Feature;

use Illuminate\Support\Facades\Http;
use Shipkit\CourierBD\DTOs\OrderRequest;
use Shipkit\CourierBD\Facades\Courier;
use Shipkit\CourierBD\Tests\TestCase;

class CompareFeesTest extends TestCase
{
    public function test_compare_fees_returns_sorted_array(): void
    {
        Http::fake([
            '*/aladdin/api/v1/issue-token' => Http::response(['access_token' => 'token'], 200),
            '*/aladdin/api/v1/merchant/price-plan' => Http::response([
                'data' => ['estimated_price' => 60.0]
            ], 200),
            '*/charge-calculator' => Http::response([
                'charge' => 85.0
            ], 200),
        ]);

        config([
            'shipkit.enabled_couriers' => ['pathao', 'redx', 'steadfast'],
        ]);

        $order = new OrderRequest(
            merchantOrderId: 'ORD-COMPARE',
            recipientName: 'Test Customer',
            recipientPhone: '01711111111',
            recipientAddress: 'Gulshan 2, Dhaka',
            recipientCity: 'Dhaka',
            amountToCollect: 1200,
            itemWeight: 1.0
        );

        $results = Courier::compareFees($order);

        $this->assertIsArray($results);
        $this->assertCount(3, $results);

        // Verify sorted by fee ascending
        $fees = array_column(array_filter($results, fn($r) => $r['available']), 'fee');
        $sortedFees = $fees;
        sort($sortedFees);

        $this->assertEquals($sortedFees, $fees);
        $this->assertEquals('pathao', $results[0]['courier']); // 60.0 < 70.0 < 85.0
    }
}
