<?php

namespace App\Jobs;

use App\Models\Booking;
use App\Models\Reminder;
use App\Models\Subscriber;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class AppUninstalledJob extends \Osiset\ShopifyApp\Messaging\Jobs\AppUninstalledJob
{
    /**
     * Execute the job to clean up shop data upon uninstall.
     *
     * @return bool
     */
    public function handle(): bool
    {
        parent::handle();

        try {
            $shopDomainStr = is_object($this->shopDomain) ? $this->shopDomain->toNative() : (string) $this->shopDomain;
            $shop = User::withTrashed()->where('name', $shopDomainStr)->first();

            if ($shop) {
                Log::info("AppUninstalledJob: Purging data and resetting plan for uninstalled shop {$shopDomainStr}");
                $shop->password = '';
                $shop->shopify_offline_refresh_token = null;
                $shop->shopify_offline_access_token_expires_at = null;
                $shop->shopify_offline_refresh_token_expires_at = null;
                $shop->plan_id = null;
                $shop->shopify_freemium = 0;
                $shop->save();

                \Illuminate\Support\Facades\DB::table('charges')->where('user_id', $shop->id)->update(['status' => 'CANCELLED', 'deleted_at' => now()]);

                Booking::where('shop_id', $shop->id)->delete();
                Reminder::where('shop_id', $shop->id)->delete();
                Subscriber::where('shop_id', $shop->id)->delete();
                Setting::where('shop_id', $shop->id)->delete();
            }
        } catch (\Exception $e) {
            Log::error("AppUninstalledJob cleanup error: " . $e->getMessage());
        }

        return true;
    }
}
