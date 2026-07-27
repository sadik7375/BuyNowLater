<?php

namespace App\Jobs;

use App\Models\Booking;
use App\Models\Reminder;
use App\Models\Subscriber;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Osiset\ShopifyApp\Contracts\Commands\Shop as IShopCommand;
use Osiset\ShopifyApp\Contracts\Queries\Shop as IShopQuery;
use Osiset\ShopifyApp\Actions\CancelCurrentPlan;

class AppUninstalledJob extends \Osiset\ShopifyApp\Messaging\Jobs\AppUninstalledJob
{
    /**
     * Execute the job to clean up shop data upon uninstall.
     *
     * @return bool
     */
    public function handle(
        IShopCommand $shopCommand,
        IShopQuery $shopQuery,
        CancelCurrentPlan $cancelCurrentPlanAction
    ): bool {
        parent::handle($shopCommand, $shopQuery, $cancelCurrentPlanAction);

        try {
            $shopDomainStr = is_object($this->shopDomain) ? $this->shopDomain->toNative() : (string) $this->shopDomain;
            Log::info("AppUninstalledJob processing cleanup for: " . $shopDomainStr);

            $allUserIds = User::withTrashed()->where('name', $shopDomainStr)->pluck('id');

            User::withTrashed()->where('name', $shopDomainStr)->update([
                'password' => '',
                'shopify_offline_refresh_token' => null,
                'shopify_offline_access_token_expires_at' => null,
                'shopify_offline_refresh_token_expires_at' => null,
                'plan_id' => null,
                'shopify_freemium' => 0,
                'deleted_at' => now(),
            ]);

            \Illuminate\Support\Facades\DB::table('charges')->whereIn('user_id', $allUserIds)->update(['status' => 'CANCELLED', 'deleted_at' => now()]);

            Booking::whereIn('shop_id', $allUserIds)->delete();
            Reminder::whereIn('shop_id', $allUserIds)->delete();
            Subscriber::whereIn('shop_id', $allUserIds)->delete();
            Setting::whereIn('shop_id', $allUserIds)->delete();

            Log::info("AppUninstalledJob cleanup successful for: " . $shopDomainStr);
        } catch (\Exception $e) {
            Log::error("AppUninstalledJob cleanup error: " . $e->getMessage());
        }

        return true;
    }
}
