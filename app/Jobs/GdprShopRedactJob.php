<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Booking;
use App\Models\Reminder;
use App\Models\Subscriber;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

class GdprShopRedactJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $shopDomain;
    public $data;

    public function __construct($shopDomain, $data)
    {
        $this->shopDomain = $shopDomain;
        $this->data = $data;
    }

    public function handle()
    {
        Log::info("GDPR Shop Redact received for shop {$this->shopDomain}", (array)$this->data);

        $shop = User::where('name', $this->shopDomain)->first();
        if (!$shop) {
            return;
        }

        // Delete all data associated with this shop
        Booking::where('shop_id', $shop->id)->delete();
        Reminder::where('shop_id', $shop->id)->delete();
        Subscriber::where('shop_id', $shop->id)->delete();
        Setting::where('shop_id', $shop->id)->delete();

        Log::info("GDPR Shop Redact completed for shop id: {$shop->id}");
    }
}
