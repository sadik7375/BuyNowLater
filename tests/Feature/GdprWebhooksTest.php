<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Booking;
use App\Models\Reminder;
use App\Models\Subscriber;
use App\Models\Setting;
use App\Jobs\GdprCustomersDataRequestJob;
use App\Jobs\GdprCustomersRedactJob;
use App\Jobs\GdprShopRedactJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GdprWebhooksTest extends TestCase
{
    use RefreshDatabase;

    public function test_gdpr_customers_data_request_job()
    {
        $data = (object)[
            'customer' => (object)[
                'email' => 'customer@example.com'
            ]
        ];

        // This job simply logs, verify it runs without throwing exception
        GdprCustomersDataRequestJob::dispatch('test-shop.myshopify.com', $data);
        $this->assertTrue(true);
    }

    public function test_gdpr_customers_redact_job_anonymizes_customer_data()
    {
        $user = User::factory()->create([
            'name' => 'test-shop.myshopify.com'
        ]);

        // Create booking, reminder, and subscriber with email
        Booking::create([
            'shop_id' => $user->id,
            'email' => 'customer@example.com',
            'product_id' => '123456',
            'product_title' => 'Shoes',
            'product_handle' => 'shoes',
            'product_price' => 100.00,
            'deposit_amount' => 10.00,
            'remaining_balance' => 90.00,
            'status' => 'pending',
            'token' => 'token1',
        ]);

        Reminder::create([
            'shop_id' => $user->id,
            'product_id' => '123456',
            'product_title' => 'Shoes',
            'product_handle' => 'shoes',
            'product_price' => '100.00',
            'email' => 'customer@example.com',
            'scheduled_at' => now()->addDays(2),
            'token' => 'token2',
            'status' => 'pending',
        ]);

        Subscriber::create([
            'shop_id' => $user->id,
            'product_id' => '123456',
            'product_title' => 'Shoes',
            'product_handle' => 'shoes',
            'product_price' => '100.00',
            'email' => 'customer@example.com',
            'status' => 'active',
        ]);

        $data = (object)[
            'customer' => (object)[
                'email' => 'customer@example.com'
            ]
        ];

        // Dispatch GDPR customers redact job
        GdprCustomersRedactJob::dispatch('test-shop.myshopify.com', $data);

        // Verify booking email was redacted
        $booking = Booking::first();
        $this->assertEquals('redacted@gdpr.com', $booking->email);
        $this->assertEquals('Redacted', $booking->customer_name);

        // Verify reminder email was redacted
        $reminder = Reminder::first();
        $this->assertEquals('redacted@gdpr.com', $reminder->email);

        // Verify subscriber record was deleted
        $this->assertEquals(0, Subscriber::count());
    }

    public function test_gdpr_shop_redact_job_deletes_shop_data()
    {
        $user = User::factory()->create([
            'name' => 'test-shop.myshopify.com'
        ]);

        Setting::create([
            'shop_id' => $user->id,
            'sender_display_name' => 'Store Name',
            'button_text' => 'Save Later',
            'reminder_email_subject' => 'Remind',
            'discount_email_subject' => 'Discount',
        ]);

        Booking::create([
            'shop_id' => $user->id,
            'email' => 'customer@example.com',
            'product_id' => '123456',
            'product_title' => 'Shoes',
            'product_handle' => 'shoes',
            'product_price' => 100.00,
            'deposit_amount' => 10.00,
            'remaining_balance' => 90.00,
            'status' => 'pending',
            'token' => 'token1',
        ]);

        $data = (object)[
            'shop_domain' => 'test-shop.myshopify.com'
        ];

        // Dispatch GDPR shop redact job
        GdprShopRedactJob::dispatch('test-shop.myshopify.com', $data);

        // Verify all related tables were cleared
        $this->assertEquals(0, Booking::count());
        $this->assertEquals(0, Setting::count());
    }
}
