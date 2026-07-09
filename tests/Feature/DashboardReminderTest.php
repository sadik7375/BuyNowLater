<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Setting;
use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }

    public function test_send_reminder_pending_booking_recreates_deposit_draft_order_and_sends_email()
    {
        $this->withoutMiddleware();

        // 1. Mock Shopify API rest client
        $apiMock = \Mockery::mock(\Gnikyt\BasicShopifyAPI\BasicShopifyAPI::class);

        // Expect the draft order creation for the deposit
        $apiMock->shouldReceive('rest')
            ->once()
            ->with('POST', '/admin/api/' . config('shopify-app.api_version') . '/draft_orders.json', \Mockery::on(function ($draftOrderData) {
                return isset($draftOrderData['draft_order']['tags']) && $draftOrderData['draft_order']['tags'] === 'buylater-deposit';
            }))
            ->andReturn([
                'errors' => false,
                'status' => 201,
                'body' => [
                    'draft_order' => [
                        'id' => 999111,
                        'invoice_url' => 'https://test-shop.myshopify.com/checkout/999111'
                    ]
                ]
            ]);

        // Create a real user that returns the apiMock
        $realUser = User::factory()->create([
            'id' => 1,
            'name' => 'test-shop.myshopify.com'
        ]);

        $setting = Setting::create([
            'shop_id' => $realUser->id,
            'sendgrid_api_key' => 'SG.fake_key',
            'sendgrid_from_email' => 'from@example.com',
            'hold_duration_days' => 14,
        ]);

        // Create booking with pending status and no draft order (or needs recreation)
        $booking = Booking::create([
            'shop_id' => $realUser->id,
            'email' => 'customer@example.com',
            'product_id' => '123456',
            'product_title' => 'Cool Shoes',
            'product_handle' => 'cool-shoes',
            'product_price' => 100.00,
            'deposit_amount' => 20.00,
            'remaining_balance' => 80.00,
            'status' => 'pending',
            'token' => 'pendingtoken123',
            'draft_order_id' => null,
            'checkout_url' => null,
        ]);

        $userMock = \Mockery::mock($realUser)->makePartial();
        $userMock->shouldReceive('api')->andReturn($apiMock);

        $this->actingAs($userMock);

        // Mock SendGridService email sending
        \Mockery::mock('alias:App\Services\SendGridService')
            ->shouldReceive('send')
            ->once()
            ->andReturn(true);

        $response = $this->post(route('bookings.send_reminder', ['id' => $booking->id]));

        $response->assertStatus(302);
        $booking->refresh();

        // Status should still be pending (since deposit has not been paid yet)
        $this->assertEquals('pending', $booking->status);
        $this->assertEquals(999111, $booking->draft_order_id);
        $this->assertEquals('https://test-shop.myshopify.com/checkout/999111', $booking->checkout_url);
    }

    public function test_send_reminder_deposit_paid_booking_creates_remaining_balance_draft_order_and_sends_email()
    {
        $this->withoutMiddleware();

        $apiMock = \Mockery::mock(\Gnikyt\BasicShopifyAPI\BasicShopifyAPI::class);

        // First rest call: Sync check at top of sendReminder (fetches current draft order ID 55555 which is the deposit)
        // Since it is completed, it will be returned as completed.
        $apiMock->shouldReceive('rest')
            ->once()
            ->with('GET', '/admin/api/' . config('shopify-app.api_version') . '/draft_orders/55555.json')
            ->andReturn([
                'errors' => false,
                'status' => 200,
                'body' => [
                    'draft_order' => [
                        'id' => 55555,
                        'status' => 'completed',
                        'line_items' => [
                            ['title' => 'Deposit — Cool Shoes', 'price' => '20.00']
                        ]
                    ]
                ]
            ]);

        // Second rest call: Inside if (deposit_paid) block (fetches the draft order again to determine if it is completed or remaining balance)
        $apiMock->shouldReceive('rest')
            ->once()
            ->with('GET', '/admin/api/' . config('shopify-app.api_version') . '/draft_orders/55555.json')
            ->andReturn([
                'errors' => false,
                'status' => 200,
                'body' => [
                    'draft_order' => [
                        'id' => 55555,
                        'status' => 'completed',
                        'line_items' => [
                            ['title' => 'Deposit — Cool Shoes', 'price' => '20.00']
                        ]
                    ]
                ]
            ]);

        // Third rest call: Creates the remaining balance draft order
        $apiMock->shouldReceive('rest')
            ->once()
            ->with('POST', '/admin/api/' . config('shopify-app.api_version') . '/draft_orders.json', \Mockery::on(function ($draftOrderData) {
                return isset($draftOrderData['draft_order']['line_items'][0]['title']) &&
                       str_contains($draftOrderData['draft_order']['line_items'][0]['title'], 'Remaining Balance');
            }))
            ->andReturn([
                'errors' => false,
                'status' => 201,
                'body' => [
                    'draft_order' => [
                        'id' => 77777,
                        'invoice_url' => 'https://test-shop.myshopify.com/checkout/77777'
                    ]
                ]
            ]);

        $realUser = User::factory()->create([
            'id' => 1,
            'name' => 'test-shop.myshopify.com'
        ]);

        Setting::create([
            'shop_id' => $realUser->id,
            'sendgrid_api_key' => 'SG.fake_key',
            'sendgrid_from_email' => 'from@example.com',
            'hold_duration_days' => 14,
        ]);

        $booking = Booking::create([
            'shop_id' => $realUser->id,
            'email' => 'customer@example.com',
            'product_id' => '123456',
            'product_title' => 'Cool Shoes',
            'product_handle' => 'cool-shoes',
            'product_price' => 100.00,
            'deposit_amount' => 20.00,
            'remaining_balance' => 80.00,
            'status' => 'deposit_paid',
            'token' => 'paidtoken123',
            'draft_order_id' => 55555, // Deposit draft order ID
            'checkout_url' => 'https://test-shop.myshopify.com/checkout/55555',
        ]);

        $userMock = \Mockery::mock($realUser)->makePartial();
        $userMock->shouldReceive('api')->andReturn($apiMock);

        $this->actingAs($userMock);

        // Mock SendGridService email sending
        \Mockery::mock('alias:App\Services\SendGridService')
            ->shouldReceive('send')
            ->once()
            ->andReturn(true);

        $response = $this->post(route('bookings.send_reminder', ['id' => $booking->id]));

        $response->assertStatus(302);
        $booking->refresh();

        // CRITICAL CHECK: Booking should STILL be deposit_paid, NOT completed!
        $this->assertEquals('deposit_paid', $booking->status);
        $this->assertEquals(77777, $booking->draft_order_id);
        $this->assertEquals('https://test-shop.myshopify.com/checkout/77777', $booking->checkout_url);
    }

    public function test_send_reminder_deposit_paid_booking_transitions_to_completed_when_remaining_balance_is_paid()
    {
        $this->withoutMiddleware();

        $apiMock = \Mockery::mock(\Gnikyt\BasicShopifyAPI\BasicShopifyAPI::class);

        // Sync check: fetches the remaining balance draft order (77777) which is completed
        $apiMock->shouldReceive('rest')
            ->once()
            ->with('GET', '/admin/api/' . config('shopify-app.api_version') . '/draft_orders/77777.json')
            ->andReturn([
                'errors' => false,
                'status' => 200,
                'body' => [
                    'draft_order' => [
                        'id' => 77777,
                        'status' => 'completed',
                        'line_items' => [
                            ['title' => 'Remaining Balance - Cool Shoes', 'price' => '80.00']
                        ]
                    ]
                ]
            ]);

        $realUser = User::factory()->create([
            'id' => 1,
            'name' => 'test-shop.myshopify.com'
        ]);

        Setting::create([
            'shop_id' => $realUser->id,
            'sendgrid_api_key' => 'SG.fake_key',
            'sendgrid_from_email' => 'from@example.com',
            'hold_duration_days' => 14,
        ]);

        $booking = Booking::create([
            'shop_id' => $realUser->id,
            'email' => 'customer@example.com',
            'product_id' => '123456',
            'product_title' => 'Cool Shoes',
            'product_handle' => 'cool-shoes',
            'product_price' => 100.00,
            'deposit_amount' => 20.00,
            'remaining_balance' => 80.00,
            'status' => 'deposit_paid',
            'token' => 'paidtoken123',
            'draft_order_id' => 77777, // Remaining balance draft order ID
            'checkout_url' => 'https://test-shop.myshopify.com/checkout/77777',
        ]);

        $userMock = \Mockery::mock($realUser)->makePartial();
        $userMock->shouldReceive('api')->andReturn($apiMock);

        $this->actingAs($userMock);

        $response = $this->post(route('bookings.send_reminder', ['id' => $booking->id]));

        $response->assertStatus(302);
        $booking->refresh();

        // CRITICAL CHECK: Booking should be updated to completed!
        $this->assertEquals('completed', $booking->status);
    }

    public function test_send_reminder_pending_booking_with_variant_id_recreates_deposit_draft_order()
    {
        $this->withoutMiddleware();

        $variantId = '44793613623512';
        $apiMock = \Mockery::mock(\Gnikyt\BasicShopifyAPI\BasicShopifyAPI::class);

        // Expect the draft order creation for the deposit using variant_id and discount
        $apiMock->shouldReceive('rest')
            ->once()
            ->with('POST', '/admin/api/' . config('shopify-app.api_version') . '/draft_orders.json', \Mockery::on(function ($draftOrderData) use ($variantId) {
                $lineItem = $draftOrderData['draft_order']['line_items'][0] ?? [];
                return isset($lineItem['variant_id']) &&
                       $lineItem['variant_id'] === (int)$variantId &&
                       isset($lineItem['applied_discount']) &&
                       $lineItem['applied_discount']['value'] === '80.00' && // 100 - 20 = 80 remaining
                       $lineItem['applied_discount']['value_type'] === 'fixed_amount';
            }))
            ->andReturn([
                'errors' => false,
                'status' => 201,
                'body' => [
                    'draft_order' => [
                        'id' => 999222,
                        'invoice_url' => 'https://test-shop.myshopify.com/checkout/999222'
                    ]
                ]
            ]);

        $realUser = User::factory()->create([
            'id' => 2,
            'name' => 'test-shop-var.myshopify.com'
        ]);

        Setting::create([
            'shop_id' => $realUser->id,
            'sendgrid_api_key' => 'SG.fake_key',
            'sendgrid_from_email' => 'from@example.com',
            'hold_duration_days' => 14,
        ]);

        $booking = Booking::create([
            'shop_id' => $realUser->id,
            'email' => 'customer@example.com',
            'product_id' => '123456',
            'variant_id' => $variantId,
            'product_title' => 'Cool Shoes',
            'product_handle' => 'cool-shoes',
            'product_price' => 100.00,
            'deposit_amount' => 20.00,
            'remaining_balance' => 80.00,
            'status' => 'pending',
            'token' => 'pendingtoken123',
            'draft_order_id' => null,
            'checkout_url' => null,
        ]);

        $userMock = \Mockery::mock($realUser)->makePartial();
        $userMock->shouldReceive('api')->andReturn($apiMock);

        $this->actingAs($userMock);

        \Mockery::mock('alias:App\Services\SendGridService')
            ->shouldReceive('send')
            ->once()
            ->andReturn(true);

        $response = $this->post(route('bookings.send_reminder', ['id' => $booking->id]));
        $response->assertStatus(302);

        $booking->refresh();
        $this->assertEquals(999222, $booking->draft_order_id);
    }

    public function test_send_reminder_deposit_paid_booking_with_variant_id_creates_remaining_balance_draft_order()
    {
        $this->withoutMiddleware();

        $variantId = '44793613623512';
        $apiMock = \Mockery::mock(\Gnikyt\BasicShopifyAPI\BasicShopifyAPI::class);

        // GET call to sync check
        $apiMock->shouldReceive('rest')
            ->once()
            ->with('GET', '/admin/api/' . config('shopify-app.api_version') . '/draft_orders/55555.json')
            ->andReturn([
                'errors' => false,
                'status' => 200,
                'body' => [
                    'draft_order' => [
                        'id' => 55555,
                        'status' => 'completed',
                        'line_items' => [
                            ['title' => 'Deposit — Cool Shoes', 'price' => '20.00']
                        ]
                    ]
                ]
            ]);

        // GET call inside sendReminder to check status again
        $apiMock->shouldReceive('rest')
            ->once()
            ->with('GET', '/admin/api/' . config('shopify-app.api_version') . '/draft_orders/55555.json')
            ->andReturn([
                'errors' => false,
                'status' => 200,
                'body' => [
                    'draft_order' => [
                        'id' => 55555,
                        'status' => 'completed',
                        'line_items' => [
                            ['title' => 'Deposit — Cool Shoes', 'price' => '20.00']
                        ]
                    ]
                ]
            ]);

        // POST call to create remaining balance draft order with variant_id and discount
        $apiMock->shouldReceive('rest')
            ->once()
            ->with('POST', '/admin/api/' . config('shopify-app.api_version') . '/draft_orders.json', \Mockery::on(function ($draftOrderData) use ($variantId) {
                $lineItem = $draftOrderData['draft_order']['line_items'][0] ?? [];
                return isset($lineItem['variant_id']) &&
                       $lineItem['variant_id'] === (int)$variantId &&
                       isset($lineItem['applied_discount']) &&
                       $lineItem['applied_discount']['value'] === '20.00' && // deposit discount applied
                       $lineItem['applied_discount']['value_type'] === 'fixed_amount';
            }))
            ->andReturn([
                'errors' => false,
                'status' => 201,
                'body' => [
                    'draft_order' => [
                        'id' => 88888,
                        'invoice_url' => 'https://test-shop.myshopify.com/checkout/88888'
                    ]
                ]
            ]);

        $realUser = User::factory()->create([
            'id' => 3,
            'name' => 'test-shop-var2.myshopify.com'
        ]);

        Setting::create([
            'shop_id' => $realUser->id,
            'sendgrid_api_key' => 'SG.fake_key',
            'sendgrid_from_email' => 'from@example.com',
            'hold_duration_days' => 14,
        ]);

        $booking = Booking::create([
            'shop_id' => $realUser->id,
            'email' => 'customer@example.com',
            'product_id' => '123456',
            'variant_id' => $variantId,
            'product_title' => 'Cool Shoes',
            'product_handle' => 'cool-shoes',
            'product_price' => 100.00,
            'deposit_amount' => 20.00,
            'remaining_balance' => 80.00,
            'status' => 'deposit_paid',
            'token' => 'paidtoken123',
            'draft_order_id' => 55555,
            'checkout_url' => 'https://test-shop.myshopify.com/checkout/55555',
        ]);

        $userMock = \Mockery::mock($realUser)->makePartial();
        $userMock->shouldReceive('api')->andReturn($apiMock);

        $this->actingAs($userMock);

        \Mockery::mock('alias:App\Services\SendGridService')
            ->shouldReceive('send')
            ->once()
            ->andReturn(true);

        $response = $this->post(route('bookings.send_reminder', ['id' => $booking->id]));
        $response->assertStatus(302);

        $booking->refresh();
        $this->assertEquals(88888, $booking->draft_order_id);
    }
}
