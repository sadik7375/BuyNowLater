<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Setting;
use App\Models\Booking;
use App\Jobs\OrdersPaidJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrdersPaidJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_orders_paid_job_sets_correct_expires_at_using_settings()
    {
        $user = User::factory()->create([
            'name' => 'test-shop.myshopify.com'
        ]);

        // Create setting with 15 days hold duration
        Setting::create([
            'shop_id' => $user->id,
            'sender_display_name' => 'Test Store',
            'deposit_percentage' => 20,
            'hold_duration_days' => 15,
            'button_text' => 'Buy Later',
            'reminder_email_subject' => 'Remind',
            'discount_email_subject' => 'Discount',
        ]);

        // Create booking with pending status
        $booking = Booking::create([
            'shop_id' => $user->id,
            'email' => 'customer@example.com',
            'product_id' => '123456',
            'product_title' => 'Cool Shoes',
            'product_handle' => 'cool-shoes',
            'product_price' => 100.00,
            'deposit_amount' => 20.00,
            'remaining_balance' => 80.00,
            'status' => 'pending',
            'token' => 'abc123token',
        ]);

        // Mock Webhook Data
        $webhookData = (object)[
            'id' => 99999,
            'tags' => 'buylater-deposit',
            'customer' => (object)[
                'first_name' => 'John',
                'last_name' => 'Doe'
            ],
            'line_items' => [
                (object)[
                    'sku' => 'BUYLATER-DEP-ABC123TOKEN',
                    'price' => '20.00'
                ]
            ],
            'note_attributes' => []
        ];

        // Dispatch Job synchronously
        OrdersPaidJob::dispatch('test-shop.myshopify.com', $webhookData);

        // Assert booking was updated to deposit_paid
        $booking->refresh();
        $this->assertEquals('deposit_paid', $booking->status);
        $this->assertEquals('John Doe', $booking->customer_name);
        
        // Assert expires_at is approximately 15 days from now
        $this->assertNotNull($booking->expires_at);
        $expectedExpiry = now()->addDays(15);
        $this->assertTrue($booking->expires_at->diffInDays($expectedExpiry) == 0);
    }

    public function test_orders_paid_job_extracts_token_from_properties_for_draft_orders()
    {
        $user = User::factory()->create([
            'name' => 'test-shop.myshopify.com'
        ]);

        Setting::create([
            'shop_id' => $user->id,
            'sender_display_name' => 'Test Store',
            'deposit_percentage' => 10,
            'hold_duration_days' => 14,
            'button_text' => 'Buy Later',
            'reminder_email_subject' => 'Remind',
            'discount_email_subject' => 'Discount',
        ]);

        $booking = Booking::create([
            'shop_id' => $user->id,
            'email' => 'customer@example.com',
            'product_id' => '123456',
            'product_title' => 'Cool Shoes',
            'product_handle' => 'cool-shoes',
            'product_price' => 100.00,
            'deposit_amount' => 10.00,
            'remaining_balance' => 90.00,
            'status' => 'pending',
            'token' => 'drafttoken123',
        ]);

        $webhookData = (object)[
            'id' => 99999,
            'tags' => 'buylater-deposit',
            'customer' => (object)[
                'first_name' => 'Jane',
                'last_name' => 'Doe'
            ],
            'line_items' => [
                (object)[
                    'title' => 'Deposit — Cool Shoes',
                    'price' => '10.00',
                    'properties' => [
                        (object)[
                            'name' => '_token',
                            'value' => 'drafttoken123'
                        ],
                        (object)[
                            'name' => 'Original Price',
                            'value' => '$100.00'
                        ],
                        (object)[
                            'name' => 'Remaining Balance',
                            'value' => '$90.00'
                        ]
                    ]
                ]
            ],
            'note_attributes' => []
        ];

        OrdersPaidJob::dispatch('test-shop.myshopify.com', $webhookData);

        $booking->refresh();
        $this->assertEquals('deposit_paid', $booking->status);
        $this->assertEquals('Jane Doe', $booking->customer_name);
        $this->assertNotNull($booking->expires_at);
    }

    public function test_orders_paid_job_ignores_duplicate_deposit()
    {
        $user = User::factory()->create([
            'name' => 'test-shop.myshopify.com'
        ]);

        Setting::create([
            'shop_id' => $user->id,
            'sender_display_name' => 'Test Store',
            'deposit_percentage' => 10,
            'hold_duration_days' => 14,
            'button_text' => 'Buy Later',
            'reminder_email_subject' => 'Remind',
            'discount_email_subject' => 'Discount',
        ]);

        // Start already in deposit_paid status
        $booking = Booking::create([
            'shop_id' => $user->id,
            'email' => 'customer@example.com',
            'product_id' => '123456',
            'product_title' => 'Cool Shoes',
            'product_handle' => 'cool-shoes',
            'product_price' => 100.00,
            'deposit_amount' => 10.00,
            'remaining_balance' => 90.00,
            'status' => 'deposit_paid',
            'token' => 'drafttoken123',
        ]);

        // Duplicate deposit webhook payload
        $webhookData = (object)[
            'id' => 99999,
            'tags' => 'buylater-deposit',
            'customer' => (object)[
                'first_name' => 'Jane',
                'last_name' => 'Doe'
            ],
            'line_items' => [
                (object)[
                    'title' => 'Deposit — Cool Shoes',
                    'price' => '10.00',
                    'properties' => [
                        (object)[
                            'name' => '_token',
                            'value' => 'drafttoken123'
                        ]
                    ]
                ]
            ],
            'note_attributes' => []
        ];

        // Should ignore and remain 'deposit_paid', NOT transition to 'completed'
        OrdersPaidJob::dispatch('test-shop.myshopify.com', $webhookData);

        $booking->refresh();
        $this->assertEquals('deposit_paid', $booking->status);
    }

    public function test_orders_paid_job_completes_booking_on_balance_payment()
    {
        $user = User::factory()->create([
            'name' => 'test-shop.myshopify.com'
        ]);

        Setting::create([
            'shop_id' => $user->id,
            'sender_display_name' => 'Test Store',
            'deposit_percentage' => 10,
            'hold_duration_days' => 14,
            'button_text' => 'Buy Later',
            'reminder_email_subject' => 'Remind',
            'discount_email_subject' => 'Discount',
        ]);

        // Booking is in deposit_paid status
        $booking = Booking::create([
            'shop_id' => $user->id,
            'email' => 'customer@example.com',
            'product_id' => '123456',
            'product_title' => 'Cool Shoes',
            'product_handle' => 'cool-shoes',
            'product_price' => 100.00,
            'deposit_amount' => 10.00,
            'remaining_balance' => 90.00,
            'status' => 'deposit_paid',
            'token' => 'drafttoken123',
        ]);

        // Remaining balance webhook payload (no buylater-deposit tag, line item title is Remaining Balance, token in note attributes)
        $webhookData = (object)[
            'id' => 100000,
            'tags' => '',
            'customer' => (object)[
                'first_name' => 'Jane',
                'last_name' => 'Doe'
            ],
            'line_items' => [
                (object)[
                    'title' => 'Remaining Balance - Cool Shoes',
                    'price' => '90.00',
                ]
            ],
            'note_attributes' => [
                (object)[
                    'name' => 'buylater_token',
                    'value' => 'drafttoken123'
                ]
            ]
        ];

        // Should transition status from deposit_paid to completed
        OrdersPaidJob::dispatch('test-shop.myshopify.com', $webhookData);

        $booking->refresh();
        $this->assertEquals('completed', $booking->status);
    }
}
