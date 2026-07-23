<?php

namespace App\Jobs;

use App\Models\Booking;
use App\Models\User;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Osiset\ShopifyApp\Objects\Values\ShopDomain;
use Illuminate\Support\Facades\Log;
use stdClass;

class OrdersPaidJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Shop's myshopify domain
     *
     * @var ShopDomain|string
     */
    public $shopDomain;

    /**
     * The webhook data
     *
     * @var object
     */
    public $data;

    /**
     * Create a new job instance.
     *
     * @param string   $shopDomain The shop's myshopify domain.
     * @param stdClass $data       The webhook data (JSON decoded).
     *
     * @return void
     */
    public function __construct($shopDomain, $data)
    {
        $this->shopDomain = $shopDomain;
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->shopDomain = ShopDomain::fromNative($this->shopDomain)->toNative();

        Log::info("OrdersPaidJob: Processing for shop {$this->shopDomain}", [
            'order_id' => $this->data->id ?? null,
            'tags'     => $this->data->tags ?? '',
        ]);

        $tags = $this->data->tags ?? '';
        $noteAttributes = $this->data->note_attributes ?? [];

        $token = null;
        $tokenFromProperties = false;
        $tokenFromSku = false;

        // Check line item properties first (typical for Draft Order initial deposits)
        $lineItems = $this->data->line_items ?? [];
        foreach ($lineItems as $item) {
            $properties = is_object($item) ? ($item->properties ?? []) : ($item['properties'] ?? []);
            foreach ($properties as $prop) {
                $name = is_object($prop) ? ($prop->name ?? '') : ($prop['name'] ?? '');
                $value = is_object($prop) ? ($prop->value ?? '') : ($prop['value'] ?? '');
                if ($name === '_token' || $name === 'buylater_token') {
                    $token = strtolower($value);
                    $tokenFromProperties = true;
                    break 2;
                }
            }
        }

        // Check note_attributes if not found in properties (typical for Draft Order balance payments)
        if (!$token) {
            foreach ($noteAttributes as $attr) {
                $name = is_object($attr) ? ($attr->name ?? '') : ($attr['name'] ?? '');
                $value = is_object($attr) ? ($attr->value ?? '') : ($attr['value'] ?? '');
                if (strtolower($name) === 'buylater_token') {
                    $token = strtolower($value);
                    break;
                }
            }
        }

        // Check SKU if not found yet (typical for initial store deposit checkout of temporary products)
        if (!$token) {
            foreach ($lineItems as $item) {
                $sku = is_object($item) ? ($item->sku ?? '') : ($item['sku'] ?? '');
                if (str_starts_with($sku, 'BUYLATER-DEP-')) {
                    $token = strtolower(substr($sku, strlen('BUYLATER-DEP-')));
                    $tokenFromSku = true;
                    break;
                }
            }
        }

        // Determine if this is the initial deposit order
        $isDeposit = false;
        if ($tokenFromProperties || $tokenFromSku) {
            $isDeposit = true;
        } elseif (stripos($tags, 'buylater-deposit') !== false) {
            $isDeposit = true;
        } else {
            foreach ($lineItems as $item) {
                $title = is_object($item) ? ($item->title ?? '') : ($item['title'] ?? '');
                $sku = is_object($item) ? ($item->sku ?? '') : ($item['sku'] ?? '');
                if (stripos($title, 'Deposit') !== false || str_starts_with($sku, 'BUYLATER-DEP-')) {
                    $isDeposit = true;
                    break;
                }
            }
        }

        // If it's not a deposit order and doesn't contain a buylater token, skip
        if (!$isDeposit && empty($token)) {
            Log::info('OrdersPaidJob: Order is neither a BuyLater deposit nor a balance payment, skipping.');
            return;
        }

        $shop = User::where('name', $this->shopDomain)->first();
        if (!$shop) {
            Log::error("OrdersPaidJob: Shop not found in DB: {$this->shopDomain}");
            return;
        }

        $orderId   = $this->data->id;
        $orderName = $this->data->name ?? '#' . $orderId;

        Log::info('OrdersPaidJob: Processing token', ['token' => $token, 'is_deposit' => $isDeposit]);

        // Update booking status
        if ($token) {
            $booking = Booking::where('token', $token)->where('shop_id', $shop->id)->first();
            if ($booking) {
                if ($isDeposit) {
                    if ($booking->status === 'pending') {
                        // Initial deposit paid
                        $settings = Setting::where('shop_id', $shop->id)->first();
                        $holdDurationDays = $settings ? (int) ($settings->hold_duration_days ?? 14) : 14;

                        $customer = $this->data->customer ?? null;
                        $customerName = null;
                        if ($customer) {
                            $fetchedName = trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? ''));
                            if (!empty($fetchedName)) {
                                $customerName = $fetchedName;
                            }
                        }
                        if (empty($customerName)) {
                            $customerName = $booking->customer_name;
                        }

                        $booking->update([
                            'status'        => 'deposit_paid',
                            'order_id'      => $orderId,
                            'customer_name' => $customerName,
                            'expires_at'    => now()->addDays($holdDurationDays),
                            'deposit_paid_at' => now(),
                            'draft_order_id'=> null,
                            'checkout_url'  => null,
                        ]);
                        Log::info('OrdersPaidJob: Booking updated to deposit_paid', ['booking_id' => $booking->id]);
                    } else {
                        Log::info('OrdersPaidJob: Booking is already ' . $booking->status . ', ignoring duplicate deposit webhook.', ['booking_id' => $booking->id]);
                    }
                } else {
                    // This is the remaining balance order being paid!
                    if ($booking->status === 'deposit_paid') {
                        $booking->update([
                            'status' => 'completed',
                            'completed_at' => now(),
                            'balance_order_id' => $orderId,
                        ]);
                        Log::info('OrdersPaidJob: Booking marked completed (balance paid)', ['booking_id' => $booking->id]);
                        // No need to hold fulfillment for the final balance order
                        return;
                    } else {
                        Log::info('OrdersPaidJob: Booking status is ' . $booking->status . ', cannot mark completed.', ['booking_id' => $booking->id]);
                        return;
                    }
                }
            } else {
                Log::warning('OrdersPaidJob: No booking found for token', ['token' => $token]);
            }
        }

        // Put fulfillment on HOLD via Shopify GraphQL (only for initial deposit order)
        if ($isDeposit) {
            $this->holdOrderFulfillment($shop, $orderId, $orderName);
        }
    }

    /**
     * Put all fulfillment orders for this order on HOLD.
     * Uses Shopify Admin GraphQL fulfillmentOrderHold mutation.
     */
    private function holdOrderFulfillment(User $shop, $orderId, string $orderName): void
    {
        try {
            // Step 1: Get fulfillment order IDs for this order
            $foQuery = 'query getFulfillmentOrders($id: ID!) {
              order(id: $id) {
                fulfillmentOrders(first: 10) {
                  edges {
                    node {
                      id
                      status
                    }
                  }
                }
              }
            }';
            $gid = 'gid://shopify/Order/' . $orderId;
            $foResponse = $shop->api()->graph($foQuery, ['id' => $gid]);

            $edges = $foResponse['body']['data']['order']['fulfillmentOrders']['edges'] ?? [];

            if (empty($edges)) {
                Log::warning('OrdersPaidJob: No fulfillment orders found for order', ['order_id' => $orderId]);
                return;
            }

            // Step 2: Hold each fulfillment order
            $holdMutation = 'mutation fulfillmentOrderHold($id: ID!, $fulfillmentHold: FulfillmentOrderHoldInput!) {
              fulfillmentOrderHold(id: $id, fulfillmentHold: $fulfillmentHold) {
                fulfillmentOrder {
                  id
                  status
                }
                remainingFulfillmentOrder {
                  id
                }
                userErrors {
                  field
                  message
                }
              }
            }';

            $noteForMerchant = '⛔ BuyLater DEPOSIT ONLY — Do NOT fulfill this order. '
                . 'Customer paid a deposit. '
                . 'Remaining balance must be collected before shipping. '
                . 'Order: ' . $orderName;

            foreach ($edges as $edge) {
                $foId   = $edge['node']['id'];
                $status = $edge['node']['status'] ?? '';

                // Only hold OPEN fulfillment orders
                if ($status !== 'OPEN') {
                    continue;
                }

                $holdRes = $shop->api()->graph($holdMutation, [
                    'id' => $foId,
                    'fulfillmentHold' => [
                        'reason'      => 'HIGH_RISK_OF_FRAUD', // closest available reason
                        'reasonNotes' => $noteForMerchant,
                        'notifyMerchant' => true,
                    ],
                ]);

                $userErrors = $holdRes['body']['data']['fulfillmentOrderHold']['userErrors'] ?? [];
                if (!empty($userErrors)) {
                    Log::error('OrdersPaidJob: Fulfillment hold failed', ['errors' => $userErrors, 'fo_id' => $foId]);
                } else {
                    Log::info('OrdersPaidJob: Fulfillment hold placed', ['fo_id' => $foId, 'order_id' => $orderId]);
                }
            }
        } catch (\Exception $e) {
            Log::error('OrdersPaidJob: Exception placing fulfillment hold', ['message' => $e->getMessage()]);
        }
    }
}
