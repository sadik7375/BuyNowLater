<?php

namespace App\Jobs;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use stdClass;

class SubscriptionBillingSuccessJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $shopDomain;
    public stdClass $data;

    public function __construct(string $shopDomain, stdClass $data)
    {
        $this->shopDomain = $shopDomain;
        $this->data = $data;
    }

    public function handle(): void
    {
        $this->shopDomain = strtolower(trim($this->shopDomain));
        Log::info('SubscriptionBillingSuccessJob: Webhook received', [
            'shop' => $this->shopDomain,
            'data' => $this->data
        ]);

        $shop = User::where('name', $this->shopDomain)->first();
        if (!$shop) {
            return;
        }

        $contractId = $this->data->subscription_contract_id ?? ($this->data->subscriptionContractId ?? null);
        if (!$contractId) {
            return;
        }

        $booking = Booking::where('shop_id', $shop->id)
            ->where('subscription_contract_id', (string) $contractId)
            ->first();

        if ($booking && $booking->status === 'deposit_paid') {
            $booking->update([
                'status' => 'completed',
                'completed_at' => now(),
                'balance_order_id' => $this->data->id ?? null,
            ]);
            Log::info("SubscriptionBillingSuccessJob: Booking ID {$booking->id} marked completed via contract billing success");
        }
    }
}
