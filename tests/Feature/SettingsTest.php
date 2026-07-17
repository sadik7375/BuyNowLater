<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_save_settings_with_hold_duration()
    {
        $this->withoutMiddleware();

        $user = User::factory()->create([
            'name' => 'test-shop.myshopify.com'
        ]);

        $this->actingAs($user);

        $response = $this->post(route('settings.save'), [
            'sender_display_name' => 'Test Sender',
            'deposit_percentage' => 15,
            'button_text' => 'Buy Later',
            'reminder_email_subject' => 'Reminder Subject',
            'discount_email_subject' => 'Discount Subject',
            'hold_duration_days' => 15,
            'show_deposit' => 1,
            'show_reminders' => 1,
            'show_alerts' => 1,
        ]);

        $response->assertStatus(302);
        
        $setting = Setting::where('shop_id', $user->id)->first();
        $this->assertNotNull($setting);
        $this->assertEquals(15, $setting->deposit_percentage);
        $this->assertEquals(15, $setting->hold_duration_days);
    }

    public function test_can_get_settings_via_proxy()
    {
        $this->withoutMiddleware();

        // Seed the plan
        $plan = \Osiset\ShopifyApp\Storage\Models\Plan::find(1);
        if (!$plan) {
            $plan = new \Osiset\ShopifyApp\Storage\Models\Plan();
            $plan->id = 1;
        }
        $plan->type = 'RECURRING';
        $plan->name = 'Pro Plan';
        $plan->price = 5.00;
        $plan->interval = 'EVERY_30_DAYS';
        $plan->capped_amount = null;
        $plan->terms = 'Unlimited reminders';
        $plan->trial_days = 0;
        $plan->test = true;
        $plan->on_install = false;
        $plan->save();

        $user = User::factory()->create([
            'name' => 'test-shop.myshopify.com',
            'plan_id' => 1,
            'shopify_freemium' => false
        ]);

        Setting::create([
            'shop_id' => $user->id,
            'sender_display_name' => 'Test Sender',
            'deposit_percentage' => 15,
            'button_text' => 'Buy Later',
            'reminder_email_subject' => 'Reminder Subject',
            'discount_email_subject' => 'Discount Subject',
            'hold_duration_days' => 15,
            'show_deposit' => true,
            'show_reminders' => true,
            'show_alerts' => true,
        ]);

        $response = $this->get('/apps/buylater-proxy/settings?shop=test-shop.myshopify.com');

        $response->assertStatus(200);
        $response->assertJson([
            'deposit_percentage' => 15,
            'hold_duration_days' => 15,
            'show_deposit' => true,
        ]);
    }

    public function test_can_get_settings_via_proxy_with_middleware_active_but_no_signature()
    {
        $user = User::factory()->create([
            'name' => 'test-shop.myshopify.com'
        ]);

        Setting::create([
            'shop_id' => $user->id,
            'sender_display_name' => 'Test Sender',
            'deposit_percentage' => 25,
            'button_text' => 'Buy Later',
            'reminder_email_subject' => 'Reminder Subject',
            'discount_email_subject' => 'Discount Subject',
            'hold_duration_days' => 25,
            'show_deposit' => true,
            'show_reminders' => true,
            'show_alerts' => true,
        ]);

        $response = $this->get('/apps/buylater-proxy/settings?shop=test-shop.myshopify.com&path_prefix=/apps/buylater-proxy');

        $response->assertStatus(200);
        $response->assertJson([
            'deposit_percentage' => 25,
            'hold_duration_days' => 25,
        ]);
    }

    public function test_can_save_settings_with_product_targeting()
    {
        $this->withoutMiddleware();

        $user = User::factory()->create([
            'name' => 'test-shop.myshopify.com'
        ]);

        $this->actingAs($user);

        $response = $this->post(route('settings.save'), [
            'sender_display_name' => 'Test Sender',
            'deposit_percentage' => 15,
            'button_text' => 'Buy Later',
            'reminder_email_subject' => 'Reminder Subject',
            'discount_email_subject' => 'Discount Subject',
            'hold_duration_days' => 15,
            'show_deposit' => 1,
            'show_reminders' => 1,
            'show_alerts' => 1,
            'product_targeting_type' => 'specific',
            'targeted_product_ids' => '12345,67890',
        ]);

        $response->assertStatus(302);
        
        $setting = Setting::where('shop_id', $user->id)->first();
        $this->assertNotNull($setting);
        $this->assertEquals('specific', $setting->product_targeting_type);
        $this->assertEquals('12345,67890', $setting->targeted_product_ids);
    }

    public function test_get_settings_via_proxy_filters_product_targeting()
    {
        $user = User::factory()->create([
            'name' => 'test-shop.myshopify.com'
        ]);

        // Specific targeting with product 12345
        Setting::create([
            'shop_id' => $user->id,
            'sender_display_name' => 'Test Sender',
            'deposit_percentage' => 20,
            'button_text' => 'Buy Later',
            'reminder_email_subject' => 'Reminder Subject',
            'discount_email_subject' => 'Discount Subject',
            'hold_duration_days' => 14,
            'show_deposit' => true,
            'show_reminders' => true,
            'show_alerts' => true,
            'product_targeting_type' => 'specific',
            'targeted_product_ids' => '12345',
        ]);

        // Request without product_id -> disabled
        $response1 = $this->get('/apps/buylater-proxy/settings?shop=test-shop.myshopify.com&path_prefix=/apps/buylater-proxy');
        $response1->assertJson(['enabled' => false]);

        // Request with non-matching product_id -> disabled
        $response2 = $this->get('/apps/buylater-proxy/settings?shop=test-shop.myshopify.com&path_prefix=/apps/buylater-proxy&product_id=99999');
        $response2->assertJson(['enabled' => false]);

        // Request with matching product_id -> enabled
        $response3 = $this->get('/apps/buylater-proxy/settings?shop=test-shop.myshopify.com&path_prefix=/apps/buylater-proxy&product_id=12345');
        $response3->assertJson(['enabled' => true]);

        // Request with matching product_id in Shopify global id format -> enabled
        $response4 = $this->get('/apps/buylater-proxy/settings?shop=test-shop.myshopify.com&path_prefix=/apps/buylater-proxy&product_id=gid://shopify/Product/12345');
        $response4->assertJson(['enabled' => true]);
    }
}
