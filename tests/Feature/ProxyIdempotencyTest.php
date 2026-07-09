<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Booking;
use App\Models\Reminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProxyIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }

    public function test_booking_is_idempotent()
    {
        $this->withoutMiddleware();

        // Mock Shopify API rest client with correct type
        $apiMock = \Mockery::mock(\Gnikyt\BasicShopifyAPI\BasicShopifyAPI::class);
        $apiMock->shouldReceive('rest')
            ->with('POST', '/admin/api/' . config('shopify-app.api_version') . '/draft_orders.json', \Mockery::on(function ($draftOrderData) {
                return isset($draftOrderData['draft_order']['email']) &&
                       $draftOrderData['draft_order']['email'] === 'customer@example.com' &&
                       isset($draftOrderData['draft_order']['customer']['email']) &&
                       $draftOrderData['draft_order']['customer']['email'] === 'customer@example.com';
            }))
            ->andReturn([
                'errors' => false,
                'status' => 201,
                'body' => [
                    'draft_order' => [
                        'id' => 123456789,
                        'invoice_url' => 'https://test-shop.myshopify.com/checkout/123456'
                    ]
                ]
            ]);

        // Create a real user that returns the apiMock
        $realUser = User::factory()->create([
            'id' => 1,
            'name' => 'test-shop.myshopify.com'
        ]);

        // Create a mocked user that returns the apiMock
        $userMock = \Mockery::mock($realUser)->makePartial();
        $userMock->shouldReceive('api')->andReturn($apiMock);

        $this->actingAs($userMock);

        $payload = [
            'product_id' => 'gid://shopify/Product/111111',
            'product_title' => 'Test Product',
            'product_handle' => 'test-product',
            'product_price' => '100.00',
            'email' => 'customer@example.com',
            'shop' => 'test-shop.myshopify.com'
        ];

        // Send first request
        $response1 = $this->post('/apps/buylater-proxy/bookings', $payload);
        $response1->assertStatus(201);

        // Verify one booking is in database
        $this->assertEquals(1, Booking::count());
        $firstBooking = Booking::first();
        $this->assertNotNull($firstBooking->checkout_url);

        // Send second request immediately (within 5 minutes)
        $response2 = $this->post('/apps/buylater-proxy/bookings', $payload);
        $response2->assertStatus(201);

        // Verify still only one booking in database (no duplicates created)
        $this->assertEquals(1, Booking::count());
        
        $data2 = $response2->json();
        $this->assertEquals($firstBooking->id, $data2['booking']['id']);
    }

    public function test_booking_with_variant_id_applies_discount_and_saves_variant_id()
    {
        $this->withoutMiddleware();

        $variantId = '44793613623512';

        // Mock Shopify API rest client
        $apiMock = \Mockery::mock(\Gnikyt\BasicShopifyAPI\BasicShopifyAPI::class);
        $apiMock->shouldReceive('rest')
            ->once()
            ->with('POST', '/admin/api/' . config('shopify-app.api_version') . '/draft_orders.json', \Mockery::on(function ($draftOrderData) use ($variantId) {
                $lineItem = $draftOrderData['draft_order']['line_items'][0] ?? [];
                
                // Assert it links the variant ID and applies a line item discount
                return isset($lineItem['variant_id']) &&
                       $lineItem['variant_id'] === (int)$variantId &&
                       isset($lineItem['applied_discount']) &&
                       $lineItem['applied_discount']['value'] === '90.00' && // 90% of 100 remaining
                       $lineItem['applied_discount']['value_type'] === 'percentage';
            }))
            ->andReturn([
                'errors' => false,
                'status' => 201,
                'body' => [
                    'draft_order' => [
                        'id' => 987654321,
                        'invoice_url' => 'https://test-shop.myshopify.com/checkout/987654321'
                    ]
                ]
            ]);

        $realUser = User::factory()->create([
            'id' => 2,
            'name' => 'test-shop-variant.myshopify.com'
        ]);

        $userMock = \Mockery::mock($realUser)->makePartial();
        $userMock->shouldReceive('api')->andReturn($apiMock);

        $this->actingAs($userMock);

        $payload = [
            'product_id' => 'gid://shopify/Product/111111',
            'variant_id' => $variantId,
            'product_title' => 'Test Product',
            'product_handle' => 'test-product',
            'product_price' => '100.00',
            'email' => 'variant-customer@example.com',
            'shop' => 'test-shop-variant.myshopify.com'
        ];

        $response = $this->post('/apps/buylater-proxy/bookings', $payload);
        $response->assertStatus(201);

        $booking = Booking::where('email', 'variant-customer@example.com')->first();
        $this->assertNotNull($booking);
        $this->assertEquals($variantId, $booking->variant_id);
    }

    public function test_reminder_is_idempotent()
    {
        $this->withoutMiddleware();

        $user = User::factory()->create([
            'name' => 'test-shop.myshopify.com'
        ]);

        $this->actingAs($user);

        $payload = [
            'product_id' => 'gid://shopify/Product/111111',
            'product_title' => 'Test Product',
            'product_handle' => 'test-product',
            'product_price' => '100.00',
            'email' => 'customer@example.com',
            'scheduled_at' => now()->addDays(2)->toIso8601String(),
            'shop' => 'test-shop.myshopify.com'
        ];

        // Send first request
        $response1 = $this->post('/apps/buylater-proxy/reminders', $payload);
        $response1->assertStatus(201);

        // Verify one reminder is in database
        $this->assertEquals(1, Reminder::count());
        $firstReminder = Reminder::first();

        // Send second request immediately (within 5 minutes)
        $response2 = $this->post('/apps/buylater-proxy/reminders', $payload);
        $response2->assertStatus(201);

        // Verify still only one reminder in database (no duplicates created)
        $this->assertEquals(1, Reminder::count());

        $data2 = $response2->json();
        $this->assertEquals($firstReminder->id, $data2['reminder']['id']);
    }
}
