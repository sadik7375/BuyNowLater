<?php

namespace App\Jobs;

use App\Models\Booking;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Osiset\ShopifyApp\Objects\Values\ShopDomain;
use stdClass;

class SubscriptionContractCreatedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The shop domain.
     *
     * @var ShopDomain
     */
    public string $shopDomain;

    /**
     * The webhook data.
     *
     * @var stdClass
     */
    public stdClass $data;

    /**
     * Create a new job instance.
     *
     * @param string $shopDomain
     * @param stdClass $data
     */
    public function __construct(string $shopDomain, stdClass $data)
    {
        $this->shopDomain = $shopDomain;
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        $this->shopDomain = strtolower(trim($this->shopDomain));
        Log::info('SubscriptionContractCreatedJob: Webhook received', [
            'shop' => $this->shopDomain,
            'contract_id' => $this->data->id ?? null,
        ]);

        $shop = User::where('name', $this->shopDomain)->first();
        if (!$shop) {
            Log::error('SubscriptionContractCreatedJob: Shop not found for domain: ' . $this->shopDomain);
            return;
        }

        $contractId = $this->data->id ?? null;
        if (!$contractId) {
            Log::error('SubscriptionContractCreatedJob: Missing contract ID in payload.');
            return;
        }

        $customer = $this->data->customer ?? null;
        $email = $customer->email ?? ($this->data->email ?? 'unknown@customer.com');
        $customerName = null;
        if ($customer) {
            $customerName = trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? ''));
        }

        // Extract line items
        $lines = $this->data->lines->edges ?? ($this->data->lines ?? []);
        $firstLine = null;
        if (is_array($lines) && count($lines) > 0) {
            $firstLine = is_object($lines[0]) && isset($lines[0]->node) ? $lines[0]->node : $lines[0];
        }

        $productTitle = $firstLine->title ?? 'Reserved Product';
        $productId = $firstLine->productId ?? 'unknown';
        $variantId = $firstLine->variantId ?? null;
        $sellingPlanId = $firstLine->sellingPlanId ?? null;
        $sellingPlanGroupId = $this->data->sellingPlanGroupId ?? null;

        $settings = Setting::where('shop_id', $shop->id)->first();
        $holdDurationDays = $settings ? (int) ($settings->hold_duration_days ?? 14) : 14;

        // Check if a pending booking exists for this email and shop
        $booking = Booking::where('shop_id', $shop->id)
            ->where('email', $email)
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->first();

        if ($booking) {
            $booking->update([
                'status' => 'deposit_paid',
                'subscription_contract_id' => (string) $contractId,
                'selling_plan_id' => (string) $sellingPlanId,
                'selling_plan_group_id' => (string) $sellingPlanGroupId,
                'payment_type' => 'selling_plan',
                'customer_name' => $customerName ?: $booking->customer_name,
                'deposit_paid_at' => now(),
                'expires_at' => now()->addDays($holdDurationDays),
            ]);
            Log::info("SubscriptionContractCreatedJob: Updated pending booking ID {$booking->id} to deposit_paid via contract {$contractId}");
        } else {
            // Create new deposit_paid booking directly from contract payload
            $booking = Booking::create([
                'shop_id' => $shop->id,
                'email' => $email,
                'customer_name' => $customerName,
                'product_id' => (string) $productId,
                'variant_id' => (string) $variantId,
                'product_title' => $productTitle,
                'product_handle' => 'product',
                'product_price' => 0.00,
                'deposit_amount' => 0.00,
                'remaining_balance' => 0.00,
                'currency' => $this->data->currencyCode ?? 'USD',
                'subscription_contract_id' => (string) $contractId,
                'selling_plan_id' => (string) $sellingPlanId,
                'selling_plan_group_id' => (string) $sellingPlanGroupId,
                'payment_type' => 'selling_plan',
                'status' => 'deposit_paid',
                'token' => strtolower(\Illuminate\Support\Str::random(32)),
                'deposit_paid_at' => now(),
                'expires_at' => now()->addDays($holdDurationDays),
            ]);
            Log::info("SubscriptionContractCreatedJob: Created new deposit_paid booking ID {$booking->id} via contract {$contractId}");
        }
    }
}
