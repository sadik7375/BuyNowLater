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
        'currency',
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
        'selling_plan_id',
        'selling_plan_group_id',
        'subscription_contract_id',
        'payment_type',
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

            $gqlLineItems = [
                [
                    'title' => 'Remaining Balance - ' . $this->product_title,
                    'originalUnitPrice' => (string) number_format((float) $this->remaining_balance, 2, '.', ''),
                    'quantity' => 1,
                ]
            ];

            $variables = [
                'input' => [
                    'email' => $this->email,
                    'lineItems' => $gqlLineItems,
                    'note' => 'Remaining balance payment. Original Deposit Paid: ' . number_format((float) $this->deposit_amount, 2) . ' ' . ($this->currency ?: 'USD'),
                    'customAttributes' => [
                        [
                            'key' => 'buylater_token',
                            'value' => (string) $this->token
                        ],
                        [
                            'key' => 'Original Deposit Paid',
                            'value' => number_format((float) $this->deposit_amount, 2) . ' ' . ($this->currency ?: 'USD')
                        ]
                    ]
                ]
            ];

            $gqlMutation = 'mutation draftOrderCreate($input: DraftOrderInput!) {
                draftOrderCreate(input: $input) {
                    draftOrder {
                        id
                        invoiceUrl
                    }
                    userErrors {
                        field
                        message
                    }
                }
            }';

            $response = $shop->api()->graph($gqlMutation, $variables);

            if ($response['errors']) {
                \Illuminate\Support\Facades\Log::error("createRemainingBalanceDraftOrder: Shopify API Error for booking ID {$this->id}: " . json_encode($response['body']));
                return null;
            }

            $draftOrder = $response['body']['data']['draftOrderCreate']['draftOrder'] ?? null;
            if ($draftOrder) {
                $gqlId = $draftOrder['id'] ?? null;
                $draftOrderId = null;
                if ($gqlId && preg_match('/DraftOrder\/(\d+)/', $gqlId, $matches)) {
                    $draftOrderId = $matches[1];
                }
                
                $checkoutUrl = $draftOrder['invoiceUrl'] ?? null;

                // If invoiceUrl is missing, try to generate it by sending invoice
                if (empty($checkoutUrl) && $gqlId) {
                    try {
                        $sendInvoiceMutation = 'mutation draftOrderSendInvoice($id: ID!, $email: DraftOrderEmailInput) {
                            draftOrderSendInvoice(id: $id, email: $email) {
                                draftOrder {
                                    id
                                    invoiceUrl
                                }
                            }
                        }';

                        $invoiceRes = $shop->api()->graph($sendInvoiceMutation, [
                            'id' => $gqlId,
                            'email' => ['to' => $this->email]
                        ]);

                        if ($invoiceRes['errors'] === false && isset($invoiceRes['body']['data']['draftOrderSendInvoice']['draftOrder'])) {
                            $refetchedOrder = $invoiceRes['body']['data']['draftOrderSendInvoice']['draftOrder'];
                            $checkoutUrl = $refetchedOrder['invoiceUrl'] ?? null;
                        }
                    } catch (\Exception $invoiceEx) {
                        \Illuminate\Support\Facades\Log::error('Failed to send invoice for remaining balance draft order in Booking model', ['error' => $invoiceEx->getMessage()]);
                    }
                }

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

    /**
     * Get usage statistics for the shop (Total holds, reminders, and alerts).
     */
    public static function getUsageStats($shopId)
    {
        $holds = \App\Models\Booking::where('shop_id', $shopId)->count();
        $reminders = \App\Models\Reminder::where('shop_id', $shopId)->count();
        $alerts = \App\Models\Subscriber::where('shop_id', $shopId)->count();
        return [
            'holds' => $holds,
            'reminders' => $reminders,
            'alerts' => $alerts,
            'total' => $holds + $reminders + $alerts,
        ];
    }
}
