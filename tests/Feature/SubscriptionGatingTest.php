<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Booking;
use App\Models\Reminder;
use App\Models\Subscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionGatingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed the plan
        $this->plan = new \Osiset\ShopifyApp\Storage\Models\Plan();
        $this->plan->id = 1;
        $this->plan->type = 'RECURRING';
        $this->plan->name = 'Pro Plan';
        $this->plan->price = 5.00;
        $this->plan->interval = 'EVERY_30_DAYS';
        $this->plan->capped_amount = null;
        $this->plan->terms = 'Unlimited reminders';
        $this->plan->trial_days = 0;
        $this->plan->test = true;
        $this->plan->on_install = false;
        $this->plan->save();
    }

    public function test_free_plan_blocks_booking_creation()
    {
        $this->withoutMiddleware();

        $user = User::factory()->create([
            'name' => 'test-shop.myshopify.com',
            'plan_id' => null,
            'shopify_freemium' => true
        ]);

        $this->actingAs($user);

        $payload = [
            'product_id' => 'gid://shopify/Product/111111',
            'product_title' => 'Test Product',
            'product_handle' => 'test-product',
            'product_price' => '100.00',
            'email' => 'customer@example.com',
            'shop' => 'test-shop.myshopify.com'
        ];

        $response = $this->post('/apps/buylater-proxy/bookings', $payload);
        $response->assertStatus(403);
        $response->assertJsonFragment([
            'message' => 'Deposit bookings require the Pro Plan. Please upgrade to Pro.'
        ]);
    }

    public function test_pro_plan_allows_booking_creation()
    {
        $this->withoutMiddleware();

        // Mock Shopify API rest client
        $apiMock = \Mockery::mock(\Gnikyt\BasicShopifyAPI\BasicShopifyAPI::class);
        $apiMock->shouldReceive('rest')
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

        $realUser = User::factory()->create([
            'name' => 'test-shop.myshopify.com',
            'plan_id' => 1,
            'shopify_freemium' => false
        ]);

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

        $response = $this->post('/apps/buylater-proxy/bookings', $payload);
        $response->assertStatus(201);
    }

    public function test_free_plan_limits_reminders_to_twenty()
    {
        $this->withoutMiddleware();

        $user = User::factory()->create([
            'name' => 'test-shop.myshopify.com',
            'plan_id' => null,
            'shopify_freemium' => true
        ]);

        $this->actingAs($user);

        // Create 20 reminders/subscribers for this month
        for ($i = 0; $i < 20; $i++) {
            Reminder::create([
                'shop_id' => $user->id,
                'product_id' => 'gid://shopify/Product/111111',
                'product_title' => 'Test Product',
                'product_handle' => 'test-product',
                'product_price' => '100.00',
                'email' => "customer{$i}@example.com",
                'scheduled_at' => now()->addDays(2),
                'token' => "token-{$i}",
                'status' => 'pending'
            ]);
        }

        $payload = [
            'product_id' => 'gid://shopify/Product/111111',
            'product_title' => 'Test Product',
            'product_handle' => 'test-product',
            'product_price' => '100.00',
            'email' => 'limit-test@example.com',
            'scheduled_at' => now()->addDays(2)->toIso8601String(),
            'shop' => 'test-shop.myshopify.com'
        ];

        // The 21st reminder should be blocked
        $response = $this->post('/apps/buylater-proxy/reminders', $payload);
        $response->assertStatus(403);
        $response->assertJsonFragment([
            'message' => 'Monthly limit of 20 events reached on the Free plan. Please upgrade to Pro for unlimited usage.'
        ]);
    }

    public function test_pro_plan_allows_unlimited_reminders()
    {
        $this->withoutMiddleware();

        $user = User::factory()->create([
            'name' => 'test-shop.myshopify.com',
            'plan_id' => 1,
            'shopify_freemium' => false
        ]);

        $this->actingAs($user);

        // Create 20 reminders/subscribers
        for ($i = 0; $i < 20; $i++) {
            Reminder::create([
                'shop_id' => $user->id,
                'product_id' => 'gid://shopify/Product/111111',
                'product_title' => 'Test Product',
                'product_handle' => 'test-product',
                'product_price' => '100.00',
                'email' => "customer{$i}@example.com",
                'scheduled_at' => now()->addDays(2),
                'token' => "token-{$i}",
                'status' => 'pending'
            ]);
        }

        $payload = [
            'product_id' => 'gid://shopify/Product/111111',
            'product_title' => 'Test Product',
            'product_handle' => 'test-product',
            'product_price' => '100.00',
            'email' => 'limit-test@example.com',
            'scheduled_at' => now()->addDays(2)->toIso8601String(),
            'shop' => 'test-shop.myshopify.com'
        ];

        // The 21st reminder should be allowed on Pro
        $response = $this->post('/apps/buylater-proxy/reminders', $payload);
        $response->assertStatus(201);
    }

    public function test_shop_can_be_downgraded_to_free_plan()
    {
        $this->withoutMiddleware();

        $user = User::factory()->create([
            'name' => 'test-shop.myshopify.com',
            'plan_id' => 1,
            'shopify_freemium' => false
        ]);

        $this->actingAs($user);

        // Mock CancelCurrentPlan action
        $cancelMock = \Mockery::mock(\Osiset\ShopifyApp\Actions\CancelCurrentPlan::class);
        $cancelMock->shouldReceive('__invoke')->once()->with(\Mockery::on(function ($shopIdObj) use ($user) {
            return $shopIdObj->toNative() === $user->id;
        }));
        $this->app->instance(\Osiset\ShopifyApp\Actions\CancelCurrentPlan::class, $cancelMock);

        // Mock Shop command to set as freemium
        $shopCommandMock = \Mockery::mock(\Osiset\ShopifyApp\Contracts\Commands\Shop::class);
        $shopCommandMock->shouldReceive('setAsFreemium')->once()->with(\Mockery::on(function ($shopIdObj) use ($user) {
            return $shopIdObj->toNative() === $user->id;
        }));
        $this->app->instance(\Osiset\ShopifyApp\Contracts\Commands\Shop::class, $shopCommandMock);

        $response = $this->post(route('plan.downgrade'));
        
        $response->assertStatus(302);
        $response->assertRedirect(route('home'));
        $response->assertSessionHas('success', 'You have successfully downgraded to the Free Plan.');

        // Refresh and check plan_id is cleared
        $user->refresh();
        $this->assertNull($user->plan_id);
    }
}
