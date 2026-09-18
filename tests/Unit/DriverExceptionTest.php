<?php

namespace Shipkit\CourierBD\Tests\Unit;

use Illuminate\Support\Facades\Http;
use Shipkit\CourierBD\DTOs\OrderRequest;
use Shipkit\CourierBD\Exceptions\CourierApiException;
use Shipkit\CourierBD\Exceptions\OrderCreationFailedException;
use Shipkit\CourierBD\Facades\Courier;
use Shipkit\CourierBD\Tests\TestCase;

class DriverExceptionTest extends TestCase
{
    public function test_pathao_order_creation_throws_on_api_error(): void
    {
        Http::fake([
            '*/aladdin/api/v1/issue-token' => Http::response(['access_token' => 'valid_token'], 200),
            '*/aladdin/api/v1/orders' => Http::response(['message' => 'Invalid store ID'], 400),
        ]);

        $order = new OrderRequest(
            merchantOrderId: 'ERR-1',
            recipientName: 'Test',
            recipientPhone: '01711111111',
            recipientAddress: 'Dhaka',
            amountToCollect: 100
        );

        $this->expectException(CourierApiException::class);
        Courier::via('pathao')->createOrder($order);
    }

    public function test_pathao_order_creation_throws_when_consignment_id_empty(): void
    {
        Http::fake([
            '*/aladdin/api/v1/issue-token' => Http::response(['access_token' => 'valid_token'], 200),
            '*/aladdin/api/v1/orders' => Http::response(['data' => []], 200),
        ]);

        $order = new OrderRequest(
            merchantOrderId: 'ERR-2',
            recipientName: 'Test',
            recipientPhone: '01711111111',
            recipientAddress: 'Dhaka',
            amountToCollect: 100
        );

        $this->expectException(OrderCreationFailedException::class);
        Courier::via('pathao')->createOrder($order);
    }

    public function test_redx_order_creation_throws_on_api_error(): void
    {
        Http::fake([
            '*/parcels' => Http::response(['message' => 'Unauthorized or invalid token'], 401),
        ]);

        $order = new OrderRequest(
            merchantOrderId: 'ERR-3',
            recipientName: 'Test',
            recipientPhone: '01811111111',
            recipientAddress: 'Dhaka',
            amountToCollect: 100
        );

        $this->expectException(CourierApiException::class);
        Courier::via('redx')->createOrder($order);
    }

    public function test_steadfast_order_creation_throws_on_api_error(): void
    {
        Http::fake([
            '*/create_order' => Http::response(['status' => 400, 'message' => 'Validation error'], 400),
        ]);

        $order = new OrderRequest(
            merchantOrderId: 'ERR-4',
            recipientName: 'Test',
            recipientPhone: '01911111111',
            recipientAddress: 'Dhaka',
            amountToCollect: 100
        );

        $this->expectException(CourierApiException::class);
        Courier::via('steadfast')->createOrder($order);
    }

    public function test_coverage_check_across_drivers(): void
    {
        Http::fake([
            '*/aladdin/api/v1/issue-token' => Http::response(['access_token' => 'valid_token'], 200),
            '*/aladdin/api/v1/cities' => Http::response([
                'data' => [
                    ['city_id' => 1, 'city_name' => 'Dhaka'],
                    ['city_id' => 2, 'city_name' => 'Chittagong'],
                ]
            ], 200),
            '*/areas' => Http::response([
                'areas' => [
                    ['id' => 1, 'name' => 'Dhaka'],
                ]
            ], 200),
        ]);

        $this->assertTrue(Courier::via('pathao')->checkCoverage('Dhaka'));
        $this->assertTrue(Courier::via('redx')->checkCoverage('Dhaka'));
        $this->assertTrue(Courier::via('steadfast')->checkCoverage('Dhaka'));
    }
}
