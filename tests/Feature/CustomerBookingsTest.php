<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerBookingsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }

    public function test_get_customer_bookings_returns_empty_when_no_customer_id_provided()
    {
        $this->withoutMiddleware();

        $realUser = User::factory()->create([
            'id' => 1,
            'name' => 'test-shop.myshopify.com'
        ]);

        $this->actingAs($realUser);

        $response = $this->get('/apps/buylater-proxy/customer-bookings?shop=test-shop.myshopify.com');

        $response->assertStatus(200);
        $response->assertJson([
            'bookings' => []
        ]);
    }

    public function test_get_customer_bookings_fetches_customer_from_shopify_and_returns_matched_bookings()
    {
        $this->withoutMiddleware();

        $customerId = '123456789';
        $customerEmail = 'jane.doe@example.com';

        // 1. Mock Shopify API rest client
        $apiMock = \Mockery::mock(\Gnikyt\BasicShopifyAPI\BasicShopifyAPI::class);

        // Expect the customer fetch API call
        $apiMock->shouldReceive('rest')
            ->once()
            ->with('GET', '/admin/api/' . config('shopify-app.api_version') . '/customers/' . $customerId . '.json')
            ->andReturn([
                'errors' => false,
                'status' => 200,
                'body' => [
                    'customer' => [
                        'id' => $customerId,
                        'email' => $customerEmail,
                        'first_name' => 'Jane',
                        'last_name' => 'Doe'
                    ]
                ]
            ]);

        $realUser = User::factory()->create([
            'id' => 1,
            'name' => 'test-shop.myshopify.com'
        ]);

        // Create bookings for this customer
        $booking1 = Booking::create([
            'shop_id' => $realUser->id,
            'email' => $customerEmail,
            'product_id' => '111',
            'product_title' => 'Product 1',
            'product_handle' => 'product-1',
            'product_price' => 100.00,
            'deposit_amount' => 10.00,
            'remaining_balance' => 90.00,
            'status' => 'deposit_paid',
            'token' => 'token1',
        ]);

        $booking2 = Booking::create([
            'shop_id' => $realUser->id,
            'email' => 'other.customer@example.com', // different email
            'product_id' => '222',
            'product_title' => 'Product 2',
            'product_handle' => 'product-2',
            'product_price' => 200.00,
            'deposit_amount' => 20.00,
            'remaining_balance' => 180.00,
            'status' => 'pending',
            'token' => 'token2',
        ]);

        $userMock = \Mockery::mock($realUser)->makePartial();
        $userMock->shouldReceive('api')->andReturn($apiMock);

        $this->actingAs($userMock);

        $response = $this->get('/apps/buylater-proxy/customer-bookings?shop=test-shop.myshopify.com&logged_in_customer_id=' . $customerId);

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'bookings');
        $response->assertJsonPath('bookings.0.email', $customerEmail);
        $response->assertJsonPath('bookings.0.product_title', 'Product 1');
        $response->assertJsonStructure([
            'bookings' => [
                '*' => [
                    'id', 'email', 'product_title', 'expires_at'
                ]
            ]
        ]);
    }
}
