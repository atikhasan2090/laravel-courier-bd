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

    public function test_compare_fees_with_multiple_failing_couriers(): void
    {
        // Pathao and RedX both throw exceptions or fail
        Http::fake([
            '*/aladdin/api/v1/issue-token' => Http::response([], 500),
            '*/charge-calculator' => Http::response([], 500),
        ]);

        config([
            'shipkit.enabled_couriers' => ['pathao', 'redx', 'steadfast'],
        ]);

        $order = new OrderRequest(
            merchantOrderId: 'ORD-FAIL-TEST',
            recipientName: 'Customer',
            recipientPhone: '01711111111',
            recipientAddress: 'Dhanmondi, Dhaka',
            recipientCity: 'Dhaka',
            amountToCollect: 500,
            itemWeight: 1.0
        );

        $results = Courier::compareFees($order);

        $this->assertCount(3, $results);

        // Steadfast should be first (the only available courier)
        $this->assertEquals('steadfast', $results[0]['courier']);
        $this->assertTrue($results[0]['available']);
        $this->assertNotNull($results[0]['fee']);

        // Remaining two failed couriers with null fees at the end
        $this->assertFalse($results[1]['available']);
        $this->assertNull($results[1]['fee']);
        $this->assertFalse($results[2]['available']);
        $this->assertNull($results[2]['fee']);
    }
}
