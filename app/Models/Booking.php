<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $table = 'bookings';

    protected $fillable = [
        'shop_id',
        'email',
        'product_id',
        'variant_id',
        'product_title',
        'product_handle',
        'product_image',
        'product_price',
        'deposit_amount',
        'remaining_balance',
        'draft_order_id',
        'order_id',
        'checkout_url',
        'status',
        'token',
        'customer_name',
        'expires_at',
        'deposit_paid_at',
        'completed_at',
        'balance_order_id',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'deposit_paid_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the shop that owns the booking.
     */
    public function shop()
    {
        return $this->belongsTo(User::class, 'shop_id');
    }

    /**
     * Create a Shopify Draft Order for the remaining balance.
     * Updates draft_order_id and checkout_url in the database.
     *
     * @return string|null The checkout URL for the remaining balance
     */
    public function createRemainingBalanceDraftOrder()
    {
        $shop = $this->shop;
        if (!$shop) {
            \Illuminate\Support\Facades\Log::error("createRemainingBalanceDraftOrder: Shop not found for booking ID {$this->id}");
            return null;
        }

        try {
            $lineItems = [
                [
                    'title' => 'Remaining Balance - ' . $this->product_title,
                    'price' => number_format((float) $this->remaining_balance, 2, '.', ''),
                    'quantity' => 1,
                    'requires_shipping' => true,
                ]
            ];

            $draftOrderData = [
                'draft_order' => [
                    'line_items' => $lineItems,
                    'customer' => [
                        'email' => $this->email,
                        'first_name' => $this->customer_name ?? 'Valued',
                        'last_name' => 'Customer'
                    ],
                    'use_customer_default_address' => true,
                    'note' => 'Remaining balance payment. Original Deposit Paid: $' . number_format((float) $this->deposit_amount, 2),
                    'note_attributes' => [
                        [
                            'name' => 'buylater_token',
                            'value' => $this->token
                        ],
                        [
                            'name' => 'Original Deposit Paid',
                            'value' => '$' . number_format((float) $this->deposit_amount, 2)
                        ]
                    ]
                ]
            ];

            $response = $shop->api()->rest(
                'POST',
                '/admin/api/' . config('shopify-app.api_version') . '/draft_orders.json',
                $draftOrderData
            );

            if ($response['errors']) {
                \Illuminate\Support\Facades\Log::error("createRemainingBalanceDraftOrder: Shopify API Error for booking ID {$this->id}: " . json_encode($response['body']));
                return null;
            }

            $draftOrder = $response['body']['draft_order'] ?? null;
            if ($draftOrder) {
                if (is_object($draftOrder) && method_exists($draftOrder, 'toArray')) {
                    $draftOrderArray = $draftOrder->toArray();
                } else {
                    $draftOrderArray = json_decode(json_encode($draftOrder), true);
                }

                $draftOrderId = $draftOrderArray['id'] ?? null;
                $gqlId = $draftOrderArray['admin_graphql_api_id'] ?? null;
                if ($gqlId && preg_match('/DraftOrder\/(\d+)/', $gqlId, $matches)) {
                    $draftOrderId = $matches[1];
                } elseif ($draftOrderId !== null) {
                    $draftOrderId = (string) $draftOrderId;
                }
                
                $checkoutUrl = $draftOrderArray['invoice_url'] ?? null;

                $this->update([
                    'draft_order_id' => $draftOrderId,
                    'checkout_url' => $checkoutUrl
                ]);

                return $checkoutUrl;
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("createRemainingBalanceDraftOrder failed for booking ID {$this->id}: " . $e->getMessage());
        }

        return null;
    }
}
