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

        $user = User::factory()->create([
            'name' => 'test-shop.myshopify.com'
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
}
