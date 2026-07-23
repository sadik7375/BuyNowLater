<?php

namespace Tests\Feature;

use App\Jobs\SubscriptionContractCreatedJob;
use App\Jobs\SubscriptionBillingSuccessJob;
use App\Models\Booking;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use stdClass;
use Tests\TestCase;

class SubscriptionContractJobsTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_contract_created_job_updates_pending_booking()
    {
        $user = User::factory()->create([
            'name' => 'contract-shop.myshopify.com'
        ]);

        Setting::create([
            'shop_id' => $user->id,
            'hold_duration_days' => 14
        ]);

        $booking = Booking::create([
            'shop_id' => $user->id,
            'email' => 'contract.customer@example.com',
            'product_id' => '12345',
            'product_title' => 'Test Item',
            'product_handle' => 'test-item',
            'product_price' => 100.00,
            'deposit_amount' => 10.00,
            'remaining_balance' => 90.00,
            'status' => 'pending',
            'token' => 'contracttoken123'
        ]);

        $payload = new stdClass();
        $payload->id = 'gid://shopify/SubscriptionContract/998877';
        $payload->sellingPlanGroupId = 'gid://shopify/SellingPlanGroup/1001';
        $payload->currencyCode = 'USD';
        
        $customer = new stdClass();
        $customer->email = 'contract.customer@example.com';
        $customer->first_name = 'Jane';
        $customer->last_name = 'Doe';
        $payload->customer = $customer;

        $line = new stdClass();
        $line->title = 'Test Item';
        $line->productId = '12345';
        $line->variantId = '67890';
        $line->sellingPlanId = 'gid://shopify/SellingPlan/2001';
        $payload->lines = [$line];

        $job = new SubscriptionContractCreatedJob('contract-shop.myshopify.com', $payload);
        $job->handle();

        $booking->refresh();
        $this->assertEquals('deposit_paid', $booking->status);
        $this->assertEquals('gid://shopify/SubscriptionContract/998877', $booking->subscription_contract_id);
        $this->assertEquals('selling_plan', $booking->payment_type);
        $this->assertEquals('Jane Doe', $booking->customer_name);
    }

    public function test_subscription_billing_success_job_completes_booking()
    {
        $user = User::factory()->create([
            'name' => 'contract-shop-2.myshopify.com'
        ]);

        $booking = Booking::create([
            'shop_id' => $user->id,
            'email' => 'paid.customer@example.com',
            'product_id' => '12345',
            'product_title' => 'Test Item',
            'product_handle' => 'test-item',
            'product_price' => 100.00,
            'deposit_amount' => 10.00,
            'remaining_balance' => 90.00,
            'status' => 'deposit_paid',
            'subscription_contract_id' => 'gid://shopify/SubscriptionContract/998877',
            'payment_type' => 'selling_plan',
            'token' => 'paidtoken123'
        ]);

        $payload = new stdClass();
        $payload->id = 'gid://shopify/SubscriptionBillingAttempt/554433';
        $payload->subscription_contract_id = 'gid://shopify/SubscriptionContract/998877';

        $job = new SubscriptionBillingSuccessJob('contract-shop-2.myshopify.com', $payload);
        $job->handle();

        $booking->refresh();
        $this->assertEquals('completed', $booking->status);
        $this->assertNotNull($booking->completed_at);
    }
}
