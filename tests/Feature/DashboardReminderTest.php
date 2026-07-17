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

        // Expect the draft order creation for the deposit via GraphQL
        $apiMock->shouldReceive('graph')
            ->once()
            ->with(\Mockery::on(function ($gqlQuery) {
                return str_contains($gqlQuery, 'mutation draftOrderCreate');
            }), \Mockery::on(function ($variables) {
                return isset($variables['input']['tags']) && in_array('buylater-deposit', $variables['input']['tags']);
            }))
            ->andReturn([
                'errors' => false,
                'body' => [
                    'data' => [
                        'draftOrderCreate' => [
                            'draftOrder' => [
                                'id' => 'gid://shopify/DraftOrder/999111',
                                'invoiceUrl' => 'https://test-shop.myshopify.com/checkout/999111'
                            ],
                            'userErrors' => []
                        ]
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

        // First & Second GraphQL call: Sync check & inside if block (query getDraftOrder)
        $apiMock->shouldReceive('graph')
            ->twice()
            ->with(\Mockery::on(function ($gqlQuery) {
                return str_contains($gqlQuery, 'getDraftOrder');
            }), \Mockery::any())
            ->andReturn([
                'errors' => false,
                'body' => [
                    'data' => [
                        'draftOrder' => [
                            'id' => 'gid://shopify/DraftOrder/55555',
                            'status' => 'COMPLETED',
                            'invoiceUrl' => 'https://test-shop.myshopify.com/checkout/55555',
                            'order' => null,
                            'lineItems' => [
                                'edges' => [
                                    [
                                        'node' => [
                                            'title' => 'Deposit — Cool Shoes',
                                            'appliedDiscount' => null
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]);

        // Third GraphQL call: Creates the remaining balance draft order
        $apiMock->shouldReceive('graph')
            ->once()
            ->with(\Mockery::on(function ($gqlQuery) {
                return str_contains($gqlQuery, 'draftOrderCreate');
            }), \Mockery::any())
            ->andReturn([
                'errors' => false,
                'body' => [
                    'data' => [
                        'draftOrderCreate' => [
                            'draftOrder' => [
                                'id' => 'gid://shopify/DraftOrder/77777',
                                'invoiceUrl' => 'https://test-shop.myshopify.com/checkout/77777'
                            ],
                            'userErrors' => []
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
            'draft_order_id' => 55555, // Deposit draft order ID
            'checkout_url' => 'https://test-shop.myshopify.com/checkout/55555',
        ]);

        $apiHelperMock = \Mockery::mock(\Osiset\ShopifyApp\Contracts\ApiHelper::class);
        $apiHelperMock->shouldReceive('make')->andReturnSelf();
        $apiHelperMock->shouldReceive('getApi')->andReturn($apiMock);
        $this->app->instance(\Osiset\ShopifyApp\Contracts\ApiHelper::class, $apiHelperMock);

        $this->actingAs($realUser);

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

        // Sync check: fetches the remaining balance draft order (77777) which is completed via GraphQL
        $apiMock->shouldReceive('graph')
            ->once()
            ->with(\Mockery::on(function ($gqlQuery) {
                return str_contains($gqlQuery, 'query getDraftOrder');
            }), ['id' => 'gid://shopify/DraftOrder/77777'])
            ->andReturn([
                'errors' => false,
                'body' => [
                    'data' => [
                        'draftOrder' => [
                            'id' => 'gid://shopify/DraftOrder/77777',
                            'status' => 'COMPLETED',
                            'invoiceUrl' => 'https://test-shop.myshopify.com/checkout/77777',
                            'order' => [
                                'id' => 'gid://shopify/Order/999'
                            ],
                            'lineItems' => [
                                'edges' => [
                                    [
                                        'node' => [
                                            'title' => 'Remaining Balance - Cool Shoes',
                                            'appliedDiscount' => null
                                        ]
                                    ]
                                ]
                            ]
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

        // Expect the draft order creation for the deposit using GraphQL
        $apiMock->shouldReceive('graph')
            ->once()
            ->with(\Mockery::on(function ($gqlQuery) {
                return str_contains($gqlQuery, 'mutation draftOrderCreate');
            }), \Mockery::on(function ($variables) use ($variantId) {
                $lineItem = $variables['input']['lineItems'][0] ?? [];
                return isset($lineItem['customAttributes']) &&
                       $lineItem['customAttributes'][1]['key'] === 'Original Price' &&
                       $lineItem['customAttributes'][2]['key'] === 'Remaining Balance';
            }))
            ->andReturn([
                'errors' => false,
                'body' => [
                    'data' => [
                        'draftOrderCreate' => [
                            'draftOrder' => [
                                'id' => 'gid://shopify/DraftOrder/999222',
                                'invoiceUrl' => 'https://test-shop.myshopify.com/checkout/999222'
                            ],
                            'userErrors' => []
                        ]
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

        // First & Second GraphQL call: Sync check & inside if block (query getDraftOrder)
        $apiMock->shouldReceive('graph')
            ->twice()
            ->with(\Mockery::on(function ($gqlQuery) {
                return str_contains($gqlQuery, 'getDraftOrder');
            }), \Mockery::any())
            ->andReturn([
                'errors' => false,
                'body' => [
                    'data' => [
                        'draftOrder' => [
                            'id' => 'gid://shopify/DraftOrder/55555',
                            'status' => 'COMPLETED',
                            'invoiceUrl' => 'https://test-shop.myshopify.com/checkout/55555',
                            'order' => null,
                            'lineItems' => [
                                'edges' => [
                                    [
                                        'node' => [
                                            'title' => 'Deposit — Cool Shoes',
                                            'appliedDiscount' => null
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]);



        // Draft order creation call via GraphQL
        $apiMock->shouldReceive('graph')
            ->once()
            ->with(\Mockery::on(function ($gqlQuery) {
                return str_contains($gqlQuery, 'draftOrderCreate');
            }), \Mockery::any())
            ->andReturn([
                'errors' => false,
                'body' => [
                    'data' => [
                        'draftOrderCreate' => [
                            'draftOrder' => [
                                'id' => 'gid://shopify/DraftOrder/88888',
                                'invoiceUrl' => 'https://test-shop.myshopify.com/checkout/88888'
                            ],
                            'userErrors' => []
                        ]
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

        $apiHelperMock = \Mockery::mock(\Osiset\ShopifyApp\Contracts\ApiHelper::class);
        $apiHelperMock->shouldReceive('make')->andReturnSelf();
        $apiHelperMock->shouldReceive('getApi')->andReturn($apiMock);
        $this->app->instance(\Osiset\ShopifyApp\Contracts\ApiHelper::class, $apiHelperMock);

        $this->actingAs($realUser);

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
