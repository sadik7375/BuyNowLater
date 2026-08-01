<?php

namespace App\Http\Controllers;

use App\Models\Reminder;
use App\Models\Subscriber;
use App\Models\Booking;
use App\Models\User;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AppProxyController extends Controller
{
    /**
     * Store a new scheduled reminder.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeReminder(Request $request)
    {
        Log::info('AppProxy: Store Reminder request received.', $request->all());

        $request->validate([
            'product_id' => 'required|string',
            'product_title' => 'required|string',
            'product_handle' => 'required|string',
            'product_price' => 'required|string',
            'email' => 'required|email',
            'scheduled_at' => 'required|date',
            'product_image' => 'nullable|string',
        ]);

        $shop = auth()->user(); // Authenticated via AuthProxy middleware
        if (!$shop) {
            $shopDomain = $request->query('shop') ?: $request->input('shop');
            if ($shopDomain) {
                $shop = User::where('name', $shopDomain)->first();
            }
        }

        if (!$shop) {
            return response()->json(['message' => 'Unauthorized shop.'], 401);
        }

        // Pricing limit check: If on Free Plan (no plan_id), limit to 10 combined items
        $usage = Booking::getUsageStats($shop->id);
        if (!$shop->plan_id && $usage['total'] >= 10) {
            return response()->json([
                'message' => 'The store has reached its free reservation limit. Please contact the store owner to upgrade.'
            ], 403);
        }

        // Parse scheduled date
        $scheduledInput = $request->input('scheduled_at_utc') ?: $request->input('scheduled_at');
        $scheduledAt = Carbon::parse($scheduledInput)->setTimezone(config('app.timezone'));

        // Check if reminder is in the past (comparing under the same timezone)
        if ($scheduledAt->isPast()) {
            return response()->json([
                'message' => 'Reminder date cannot be in the past. (Scheduled: ' . $scheduledAt->toDateTimeString() . ', Server Current: ' . Carbon::now(config('app.timezone'))->toDateTimeString() . ')'
            ], 422);
        }

        // Idempotency check: check if a pending reminder already exists for the same shop, email, and product
        // within the last 5 minutes.
        $existingReminder = Reminder::where('shop_id', $shop->id)
            ->where('email', $request->input('email'))
            ->where('product_id', $request->input('product_id'))
            ->where('status', 'pending')
            ->where('created_at', '>=', now()->subMinutes(5))
            ->first();

        if ($existingReminder) {
            Log::info('AppProxy: Duplicate reminder request detected. Returning existing reminder.', [
                'reminder_id' => $existingReminder->id
            ]);
            return response()->json([
                'message' => 'Reminder scheduled successfully.',
                'reminder' => $existingReminder
            ], 201);
        }

        // Create the reminder
        $reminder = Reminder::create([
            'shop_id' => $shop->id,
            'product_id' => $request->input('product_id'),
            'product_title' => $request->input('product_title'),
            'product_handle' => $request->input('product_handle'),
            'product_image' => $request->input('product_image'),
            'product_price' => $request->input('product_price'),
            'email' => $request->input('email'),
            'scheduled_at' => $scheduledAt,
            'token' => Str::random(40),
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Reminder scheduled successfully.',
            'reminder' => $reminder
        ], 201);
    }

    /**
     * Subscribe to a price drop alert.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function subscribePriceDrop(Request $request)
    {
        Log::info('AppProxy: Subscribe Price Drop request received.', $request->all());

        $request->validate([
            'product_id' => 'required|string',
            'product_title' => 'required|string',
            'product_handle' => 'required|string',
            'product_price' => 'required|string',
            'email' => 'required|email',
            'product_image' => 'nullable|string',
        ]);

        $shop = auth()->user(); // Authenticated via AuthProxy middleware
        if (!$shop) {
            $shopDomain = $request->query('shop') ?: $request->input('shop');
            if ($shopDomain) {
                $shop = User::where('name', $shopDomain)->first();
            }
        }

        if (!$shop) {
            return response()->json(['message' => 'Unauthorized shop.'], 401);
        }

        // Pricing limit check: If on Free Plan (no plan_id), limit to 10 combined items
        $usage = Booking::getUsageStats($shop->id);
        if (!$shop->plan_id && $usage['total'] >= 10) {
            return response()->json([
                'message' => 'The store has reached its free reservation limit. Please contact the store owner to upgrade.'
            ], 403);
        }

        // Fetch product's base currency price from Shopify Admin API to resolve currency mismatches
        $productPrice = $request->input('product_price');
        try {
            $productIdClean = $request->input('product_id');
            if (str_contains($productIdClean, '/')) {
                $parts = explode('/', $productIdClean);
                $productIdClean = end($parts);
            }

            $gqlQuery = 'query getProduct($id: ID!) {
                product(id: $id) {
                    variants(first: 100) {
                        edges {
                            node {
                                price
                            }
                        }
                    }
                }
            }';
            
            $response = $shop->api()->graph($gqlQuery, [
                'id' => 'gid://shopify/Product/' . $productIdClean
            ]);

            if ($response['errors'] === false && isset($response['body']['data']['product']['variants']['edges'])) {
                $edges = $response['body']['data']['product']['variants']['edges'];
                $lowestPrice = null;
                foreach ($edges as $edge) {
                    $variant = $edge['node'] ?? [];
                    $vPrice = isset($variant['price']) ? (float) $variant['price'] : null;
                    if ($vPrice !== null) {
                        if ($lowestPrice === null || $vPrice < $lowestPrice) {
                            $lowestPrice = $vPrice;
                        }
                    }
                }

                if ($lowestPrice !== null) {
                    $productPrice = (string) $lowestPrice;
                    Log::info('AppProxy: Successfully fetched base currency product price for subscriber.', [
                        'product_id' => $productIdClean,
                        'original_price_input' => $request->input('product_price'),
                        'base_currency_price' => $productPrice
                    ]);
                }
            } else {
                Log::warning('AppProxy: Product fetch returned errors or missing product body.', [
                    'errors' => $response['errors'],
                    'body' => $response['body'] ?? null
                ]);
            }
        } catch (\Exception $e) {
            Log::error('AppProxy: Exception fetching product details for base price.', [
                'message' => $e->getMessage()
            ]);
        }

        // Create or update subscriber subscription
        $subscriber = Subscriber::updateOrCreate(
            [
                'shop_id' => $shop->id,
                'product_id' => $request->input('product_id'),
                'email' => $request->input('email'),
            ],
            [
                'product_title' => $request->input('product_title'),
                'product_handle' => $request->input('product_handle'),
                'product_image' => $request->input('product_image'),
                'product_price' => $productPrice,
                'status' => 'active',
                'notified_at' => null,
            ]
        );

        return response()->json([
            'message' => 'Subscribed to price drop successfully.',
            'subscriber' => $subscriber
        ], 201);
    }

    /**
     * Cancel a reminder by its token.
     */
    public function cancelReminder($token)
    {
        $reminder = Reminder::where('token', $token)->first();

        if (!$reminder) {
            return view('reminders.customer_action', [
                'type' => 'error',
                'message' => 'Invalid or expired reminder token.'
            ]);
        }

        $reminder->update([
            'status' => 'cancelled'
        ]);

        return view('reminders.customer_action', [
            'type' => 'success',
            'message' => 'Your reminder for "' . $reminder->product_title . '" has been successfully cancelled.'
        ]);
    }

    /**
     * Show the reschedule form.
     */
    public function showRescheduleForm($token)
    {
        $reminder = Reminder::where('token', $token)->first();

        if (!$reminder) {
            return view('reminders.customer_action', [
                'type' => 'error',
                'message' => 'Invalid or expired reminder token.'
            ]);
        }

        return view('reminders.customer_action', [
            'type' => 'reschedule',
            'reminder' => $reminder,
            'message' => 'Select a new date and time to receive your reminder.'
        ]);
    }

    /**
     * Reschedule the reminder.
     */
    public function rescheduleReminder(Request $request, $token)
    {
        $reminder = Reminder::where('token', $token)->first();

        if (!$reminder) {
            return view('reminders.customer_action', [
                'type' => 'error',
                'message' => 'Invalid or expired reminder token.'
            ]);
        }

        $request->validate([
            'scheduled_at' => 'required|date'
        ]);

        $scheduledInput = $request->input('scheduled_at_utc') ?: $request->input('scheduled_at');
        $scheduledAt = Carbon::parse($scheduledInput)->setTimezone(config('app.timezone'));

        if ($scheduledAt->isPast()) {
            return view('reminders.customer_action', [
                'type' => 'reschedule',
                'reminder' => $reminder,
                'message' => 'Reminder date cannot be in the past. Please choose a future date.'
            ]);
        }

        $reminder->update([
            'scheduled_at' => $scheduledAt,
            'status' => 'pending'
        ]);

        return view('reminders.customer_action', [
            'type' => 'success',
            'message' => 'Your reminder has been rescheduled to ' . $scheduledAt->format('F j, Y, g:i a') . '.'
        ]);
    }

    /**
     * Store a new product booking (partial deposit).
     * Uses Shopify Admin GraphQL API to create a Draft Order (avoids REST protected-data restriction).
     */
    public function storeBooking(Request $request)
    {
        Log::info('AppProxy: Store Booking request received.', $request->all());

        $request->validate([
            'product_id' => 'required|string',
            'product_title' => 'required|string',
            'product_handle' => 'required|string',
            'product_price' => 'required|numeric',
            'email' => 'required|email',
            'shop' => 'required|string',
            'variant_id' => 'nullable|string',
        ]);

        $shop = auth()->user();
        if (!$shop) {
            $shopDomain = $request->query('shop') ?: $request->input('shop');
            if ($shopDomain) {
                $shop = User::where('name', $shopDomain)->first();
            }
        }

        if (!$shop) {
            return response()->json(['message' => 'Shop not found.'], 404);
        }

        // Pricing limit check: If on Free Plan (no plan_id), limit to 10 combined items
        $usage = Booking::getUsageStats($shop->id);
        if (!$shop->plan_id && $usage['total'] >= 10) {
            return response()->json([
                'message' => 'The store has reached its free reservation limit. Please contact the store owner to upgrade.'
            ], 403);
        }

        $shopDomain = $shop->name;

        // Idempotency check: check if a pending booking already exists for the same shop, email, and product
        // within the last 5 minutes.
        $existingBooking = Booking::where('shop_id', $shop->id)
            ->where('email', $request->input('email'))
            ->where('product_id', $request->input('product_id'))
            ->where('status', 'pending')
            ->where('created_at', '>=', now()->subMinutes(5))
            ->whereNotNull('checkout_url')
            ->first();

        if ($existingBooking) {
            Log::info('AppProxy: Duplicate booking request detected. Returning existing booking.', [
                'booking_id' => $existingBooking->id,
                'checkout_url' => $existingBooking->checkout_url
            ]);
            return response()->json([
                'message'      => 'Booking retrieved successfully.',
                'booking'      => $existingBooking,
                'checkout_url' => $existingBooking->checkout_url,
            ], 201);
        }

        $productPrice = (float) $request->input('product_price');
        $currency = $request->input('currency', 'USD');
        $settings = Setting::where('shop_id', $shop->id)->first();
        $depositPercentage = $settings ? (int) $settings->deposit_percentage : 10;
        $depositAmount = round($productPrice * ($depositPercentage / 100), 2);
        $remainingBalance = $productPrice - $depositAmount;
        $holdDurationDays = $settings ? (int) ($settings->hold_duration_days ?? 14) : 14;

        // Use provided token or generate a unique token
        $token = strtolower($request->input('token') ?: \Illuminate\Support\Str::random(32));

        $variantId = $request->input('variant_id');
        if ($variantId && str_contains($variantId, '/')) {
            $parts = explode('/', $variantId);
            $variantId = end($parts);
        }

        $paymentType = $request->input('payment_type') ?: ($settings && $settings->use_selling_plan ? 'selling_plan' : 'draft_order');

        if ($paymentType === 'selling_plan') {
            Log::info('AppProxy: Creating selling_plan booking without draft order.');
            $booking = Booking::create([
                'shop_id' => $shop->id,
                'email' => $request->input('email'),
                'product_id' => $request->input('product_id'),
                'variant_id' => $variantId,
                'product_title' => $request->input('product_title'),
                'product_handle' => $request->input('product_handle'),
                'product_image' => $request->input('product_image'),
                'product_price' => $productPrice,
                'deposit_amount' => $depositAmount,
                'remaining_balance' => $remainingBalance,
                'currency' => $currency,
                'status' => 'pending',
                'token' => $token,
                'payment_type' => 'selling_plan',
                'checkout_url' => '/checkout',
                'customer_name' => $request->input('customer_name') ?: ($request->input('name') ?: null),
            ]);

            return response()->json([
                'message' => 'Selling Plan booking created successfully.',
                'booking' => $booking,
                'checkout_url' => '/checkout',
            ], 201);
        }

        $lineItems = [[
            'title' => 'Deposit — ' . $request->input('product_title'),
            'price' => number_format($depositAmount, 2, '.', ''),
            'quantity' => 1,
            'requires_shipping' => false,
            'properties' => [
                ['name' => '_token', 'value' => $token],
                ['name' => '_buylater_token', 'value' => $token],
                ['name' => 'Original Price', 'value' => number_format($productPrice, 2) . ' ' . $currency],
                ['name' => 'Remaining Balance', 'value' => number_format($remainingBalance, 2) . ' ' . $currency],
                ['name' => 'product_id', 'value' => (string) $request->input('product_id')],
                ['name' => 'variant_id', 'value' => (string) $variantId],
            ]
        ]];

        $draftOrderId = null;
        $checkoutUrl = null;

        try {
            $gqlLineItems = [];
            foreach ($lineItems as $item) {
                $customAttributes = [];
                if (isset($item['properties'])) {
                    foreach ($item['properties'] as $prop) {
                        $customAttributes[] = [
                            'key' => $prop['name'],
                            'value' => (string) $prop['value']
                        ];
                    }
                }
                $gqlLineItems[] = [
                    'title' => $item['title'],
                    'originalUnitPrice' => (string) $item['price'],
                    'quantity' => (int) $item['quantity'],
                    'customAttributes' => $customAttributes,
                ];
            }

            $variables = [
                'input' => [
                    'email' => $request->input('email'),
                    'note' => 'BuyLater deposit — do not fulfill',
                    'tags' => ['buylater-deposit'],
                    'lineItems' => $gqlLineItems,
                ]
            ];

            Log::info('Deposit draft order: sending GraphQL request to Shopify', [
                'api_version' => config('shopify-app.api_version'),
                'shop' => $shopDomain,
            ]);

            $createMutation = 'mutation draftOrderCreate($input: DraftOrderInput!) {
                draftOrderCreate(input: $input) {
                    draftOrder {
                        id
                        invoiceUrl
                        status
                    }
                    userErrors {
                        field
                        message
                    }
                }
            }';

            $createRes = $shop->api()->graph($createMutation, $variables);

            Log::info('Deposit draft order create response', [
                'errors' => $createRes['errors'],
                'body' => $createRes['body'] ?? null,
            ]);

            if ($createRes['errors'] === false && isset($createRes['body']['data']['draftOrderCreate']['draftOrder'])) {
                $draftOrder = $createRes['body']['data']['draftOrderCreate']['draftOrder'];
                $gqlId = $draftOrder['id'] ?? null;
                $checkoutUrl = $draftOrder['invoiceUrl'] ?? null;

                if ($gqlId && preg_match('/DraftOrder\/(\d+)/', $gqlId, $matches)) {
                    $draftOrderId = $matches[1];
                }

                // If invoiceUrl is missing, try to get it by sending an invoice
                if (empty($checkoutUrl) && $gqlId) {
                    Log::info('invoiceUrl missing, attempting to send invoice via GraphQL to generate it');
                    try {
                        $sendInvoiceMutation = 'mutation draftOrderSendInvoice($id: ID!, $email: DraftOrderEmailInput) {
                            draftOrderSendInvoice(id: $id, email: $email) {
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

                        $invoiceRes = $shop->api()->graph($sendInvoiceMutation, [
                            'id' => $gqlId,
                            'email' => ['to' => $request->input('email')]
                        ]);

                        if ($invoiceRes['errors'] === false && isset($invoiceRes['body']['data']['draftOrderSendInvoice']['draftOrder'])) {
                            $refetchedOrder = $invoiceRes['body']['data']['draftOrderSendInvoice']['draftOrder'];
                            $checkoutUrl = $refetchedOrder['invoiceUrl'] ?? null;
                            Log::info('Re-fetched draft order invoiceUrl via sendInvoice', ['checkout_url' => $checkoutUrl]);
                        }
                    } catch (\Exception $invoiceEx) {
                        Log::error('Failed to send invoice for draft order', ['error' => $invoiceEx->getMessage()]);
                    }
                }

                Log::info('Deposit draft order created, invoice URL generated', [
                    'draft_order_id' => $draftOrderId,
                    'checkout_url'   => $checkoutUrl,
                ]);
            } else {
                $userErrors = $createRes['body']['data']['draftOrderCreate']['userErrors'] ?? [];
                Log::error('Shopify deposit draft order creation failed', [
                    'errors' => $createRes['errors'],
                    'userErrors' => $userErrors,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Exception creating deposit draft order', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        Log::info('AppProxy: Creating draft order booking in database.');
        $booking = Booking::create([
            'shop_id' => $shop->id,
            'email' => $request->input('email'),
            'product_id' => $request->input('product_id'),
            'variant_id' => $variantId,
            'product_title' => $request->input('product_title'),
            'product_handle' => $request->input('product_handle'),
            'product_image' => $request->input('product_image'),
            'product_price' => $productPrice,
            'deposit_amount' => $depositAmount,
            'remaining_balance' => $remainingBalance,
            'currency' => $currency,
            'draft_order_id' => $draftOrderId,
            'checkout_url' => $checkoutUrl,
            'status' => 'pending',
            'token' => $token,
            'customer_name' => $request->input('customer_name') ?: ($request->input('name') ?: null),
        ]);

        if (!$checkoutUrl) {
            return response()->json([
                'message'      => 'Booking saved but checkout URL could not be generated. Please try again.',
                'booking'      => $booking,
                'checkout_url' => null,
            ], 422);
        }

        return response()->json([
            'message'      => 'Booking created successfully.',
            'booking'      => $booking,
            'checkout_url' => $checkoutUrl,
        ], 201);
    }

    /**
     * Retrieve settings (like deposit percentage) for the storefront widget.
     */
    public function getSettings(Request $request)
    {
        $shop = auth()->user();
        $auth_used = $shop ? 'yes' : 'no';
        if (!$shop) {
            $shopDomain = $request->query('shop') ?: $request->input('shop');
            if ($shopDomain) {
                $shop = User::where('name', $shopDomain)->first();
            }
        }

        \Illuminate\Support\Facades\Log::info('AppProxy getSettings log:', [
            'request_shop' => $request->query('shop') ?: $request->input('shop'),
            'resolved_shop_id' => $shop ? $shop->id : null,
            'resolved_shop_name' => $shop ? $shop->name : null,
            'auth_used' => $auth_used,
            'query_params' => $request->all(),
        ]);

        if (!$shop) {
            return response()->json(['message' => 'Shop not found.'], 404);
        }

        $settings = Setting::firstOrCreate(
            ['shop_id' => $shop->id],
            [
                'sender_display_name'      => $shop->name . ' via BuyLater',
                'deposit_percentage'       => 10,
                'button_text'              => 'Buy Later — not ready yet?',
                'button_color'             => '#1a1a1a',
                'button_text_color'        => '#ffffff',
                'reminder_email_subject'   => 'Reminder: You wanted to buy this later!',
                'discount_email_subject'   => 'Price Drop Alert: A product you wanted is now on sale!',
                'show_deposit'             => true,
                'show_reminders'           => true,
                'show_alerts'              => true,
                'hold_duration_days'       => 14,
                'use_selling_plan'         => true,
            ]
        );

        // Auto-initialize Selling Plan Group if missing so native checkout works out-of-the-box on new stores
        if (empty($settings->selling_plan_group_id) && $settings->use_selling_plan) {
            try {
                $sellingPlanService = app(\App\Services\SellingPlanService::class);
                $res = $sellingPlanService->createOrUpdatePlanGroup($shop, (int)($settings->deposit_percentage ?? 10), (int)($settings->hold_duration_days ?? 14));
                if ($res && !empty($res['group_id'])) {
                    $settings->refresh();
                }
            } catch (\Exception $e) {
                Log::error("Auto SellingPlan setup in getSettings failed: " . $e->getMessage());
            }
        }

        \Illuminate\Support\Facades\Log::info('AppProxy settings found:', [
            'settings_exists' => $settings ? true : false,
            'deposit_percentage' => $settings ? $settings->deposit_percentage : null,
            'hold_duration_days' => $settings ? $settings->hold_duration_days : null,
            'selling_plan_group_id' => $settings ? $settings->selling_plan_group_id : null,
        ]);

        // Pricing limit check: If on Free Plan (no plan_id), limit to 10 total deposit reservations
        $usage = Booking::getUsageStats($shop->id);
        $isLimitReached = (!$shop->plan_id && ($usage['total'] ?? 0) >= 10);

        $showDeposit = $settings ? (bool) ($settings->show_deposit ?? true) : true;

        // Check Product Targeting
        $productTargetingType = $settings ? ($settings->product_targeting_type ?? 'all') : 'all';
        $isWidgetEnabled = true;

        if ($productTargetingType === 'specific' || $productTargetingType === 'exclude') {
            $targetedProductIdsStr = $settings ? $settings->targeted_product_ids : '';
            $targetedProductIds = array_filter(explode(',', $targetedProductIdsStr));
            $currentProductId = $request->query('product_id');
            
            if ($productTargetingType === 'specific') {
                $isWidgetEnabled = false;
                if ($currentProductId) {
                    $cleanCurrentId = preg_replace('/[^0-9]/', '', $currentProductId);
                    foreach ($targetedProductIds as $id) {
                        $cleanId = preg_replace('/[^0-9]/', '', $id);
                        if ($cleanId !== '' && $cleanId === $cleanCurrentId) {
                            $isWidgetEnabled = true;
                            break;
                        }
                    }
                }
            } else if ($productTargetingType === 'exclude') {
                $isWidgetEnabled = true;
                if ($currentProductId) {
                    $cleanCurrentId = preg_replace('/[^0-9]/', '', $currentProductId);
                    foreach ($targetedProductIds as $id) {
                        $cleanId = preg_replace('/[^0-9]/', '', $id);
                        if ($cleanId !== '' && $cleanId === $cleanCurrentId) {
                            $isWidgetEnabled = false;
                            break;
                        }
                    }
                }
            }
        }

        $currentProductId = $request->query('product_id') ?: $request->input('product_id');
        if ($shop && $settings && !$isLimitReached && $settings->use_selling_plan && $settings->selling_plan_group_id && $currentProductId) {
            $cleanId = preg_replace('/[^0-9]/', '', $currentProductId);
            if (!empty($cleanId)) {
                try {
                    $sellingPlanService = app(\App\Services\SellingPlanService::class);
                    $sellingPlanService->attachProducts($shop, $settings->selling_plan_group_id, ["gid://shopify/Product/{$cleanId}"]);
                    $settings->refresh();
                } catch (\Exception $ex) {
                    Log::warning('Auto attach product exception in getSettings: ' . $ex->getMessage());
                }
            }
        }

        $sellingPlanId = ($settings && !$isLimitReached) ? $settings->selling_plan_id : null;
        if ($sellingPlanId && preg_match('/SellingPlan\/(\d+)/', $sellingPlanId, $m)) {
            $sellingPlanId = $m[1];
        }

        return response()->json([
            'enabled' => $isWidgetEnabled,
            'limit_reached' => $isLimitReached,
            'deposit_percentage' => $settings ? (int) $settings->deposit_percentage : 10,
            'show_deposit' => $showDeposit,
            'show_reminders' => $settings ? (bool) ($settings->show_reminders ?? true) : true,
            'show_alerts' => $settings ? (bool) ($settings->show_alerts ?? true) : true,
            'hold_duration_days' => $settings ? (int) ($settings->hold_duration_days ?? 14) : 14,
            'terms_text' => $settings ? $settings->terms_text : 'By reserving, you agree to our deposit terms.',
            'button_text' => $settings ? $settings->button_text : null,
            'use_selling_plan' => ($settings && !$isLimitReached) ? (bool) $settings->use_selling_plan : false,
            'selling_plan_group_id' => ($settings && !$isLimitReached) ? $settings->selling_plan_group_id : null,
            'selling_plan_id' => $sellingPlanId,
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
          ->header('Pragma', 'no-cache');
    }

    /**
     * Retrieve bookings for the logged-in storefront customer.
     */
    public function getCustomerBookings(Request $request)
    {
        $shop = auth()->user();
        if (!$shop) {
            $shopDomain = $request->query('shop') ?: $request->input('shop');
            if ($shopDomain) {
                $shop = User::where('name', $shopDomain)->first();
            }
        }

        if (!$shop) {
            return response()->json(['message' => 'Shop not found.'], 404);
        }

        $customerId = $request->query('logged_in_customer_id');
        if (empty($customerId)) {
            return response()->json(['bookings' => []]);
        }

        try {
            $gqlQuery = 'query getCustomer($id: ID!) {
                customer(id: $id) {
                    email
                }
            }';
            
            $response = $shop->api()->graph($gqlQuery, [
                'id' => 'gid://shopify/Customer/' . $customerId
            ]);

            if ($response['errors']) {
                Log::warning('AppProxy: Failed to fetch Shopify customer details.', [
                    'customer_id' => $customerId,
                    'errors' => $response['body']
                ]);
                return response()->json(['bookings' => []]);
            }

            $customer = $response['body']['data']['customer'] ?? null;
            if (!$customer) {
                return response()->json(['bookings' => []]);
            }

            $email = $customer['email'] ?? null;
            if (!$email) {
                return response()->json(['bookings' => []]);
            }

            $settings = Setting::where('shop_id', $shop->id)->first();
            $holdDurationDays = $settings ? (int) ($settings->hold_duration_days ?? 14) : 14;

            $bookings = Booking::where('shop_id', $shop->id)
                ->where('email', $email)
                ->where('status', '!=', 'pending')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($booking) use ($holdDurationDays) {
                    // Expiry is calculated from when the deposit was paid (updated_at)
                    $depositPaidAt = $booking->updated_at;
                    $expiryDate = $depositPaidAt->copy()->addDays($holdDurationDays);
                    
                    // If the database status is deposit_paid and checkout_url is null,
                    // automatically create the Shopify draft order for the remaining balance.
                    if ($booking->status === 'deposit_paid' && empty($booking->checkout_url)) {
                        Log::info("getCustomerBookings: Generating remaining balance draft order on the fly for booking ID {$booking->id}");
                        $booking->createRemainingBalanceDraftOrder();
                    }
                    
                    $bookingArray = $booking->toArray();
                    $bookingArray['expires_at'] = $expiryDate->toIso8601String();
                    
                    // If the database status is deposit_paid, check if it has expired in time
                    if ($booking->status === 'deposit_paid' && now()->gt($expiryDate)) {
                        $bookingArray['status'] = 'expired';
                    }
                    
                    return $bookingArray;
                });

            return response()->json([
                'bookings' => $bookings
            ]);

        } catch (\Exception $e) {
            Log::error('AppProxy: Exception fetching customer bookings: ' . $e->getMessage());
            return response()->json(['bookings' => []]);
        }
    }

    /**
     * Fetch booking details by Shopify Order ID or Order Name.
     */
    public function getOrderBooking(Request $request)
    {
        $orderId = $request->query('order_id');
        $orderName = $request->query('order_name');
        $token = $request->query('token');

        if (empty($orderId) && empty($orderName) && empty($token)) {
            return response()->json(['booking' => null]);
        }

        $numericOrderId = null;
        if ($orderId) {
            if (preg_match('/Order\/(\d+)/', $orderId, $matches)) {
                $numericOrderId = $matches[1];
            } else {
                $numericOrderId = preg_replace('/\D/', '', $orderId);
            }
        }

        try {
            $booking = null;

            // 1. Try finding by token first (best for avoiding race conditions with Shopify webhook)
            if (!empty($token)) {
                Log::info("getOrderBooking: Searching booking by token: {$token}");
                $booking = Booking::where('token', $token)->first();
            }

            // 2. Fallback to order ID / Name matching
            if (!$booking) {
                $bookingQuery = Booking::query();

                if ($numericOrderId && $orderName) {
                    $cleanName = ltrim($orderName, '#');
                    $bookingQuery->where(function($q) use ($numericOrderId, $cleanName) {
                        $q->where('order_id', $numericOrderId)
                          ->orWhere('balance_order_id', $numericOrderId)
                          ->orWhere('order_name', $cleanName)
                          ->orWhere('balance_order_name', $cleanName)
                          ->orWhere('order_name', '#' . $cleanName)
                          ->orWhere('balance_order_name', '#' . $cleanName);
                    });
                } elseif ($numericOrderId) {
                    $bookingQuery->where(function($q) use ($numericOrderId) {
                        $q->where('order_id', $numericOrderId)
                          ->orWhere('balance_order_id', $numericOrderId);
                    });
                } else {
                    $cleanName = ltrim($orderName, '#');
                    $bookingQuery->where(function($q) use ($cleanName) {
                        $q->where('order_name', $cleanName)
                          ->orWhere('balance_order_name', $cleanName)
                          ->orWhere('order_name', '#' . $cleanName)
                          ->orWhere('balance_order_name', '#' . $cleanName);
                    });
                }

                $booking = $bookingQuery->first();
            }

            if (!$booking) {
                return response()->json(['booking' => null]);
            }

            // 3. Proactively handle pending status if this is a paid order checkout redirection
            if ($booking->status === 'pending' && $numericOrderId) {
                Log::info("getOrderBooking: Booking ID {$booking->id} is pending on checkout redirect. Proactively updating to deposit_paid.", [
                    'order_id' => $numericOrderId,
                    'order_name' => $orderName
                ]);

                $settings = Setting::where('shop_id', $booking->shop_id)->first();
                $holdDurationDays = $settings ? (int) ($settings->hold_duration_days ?? 14) : 14;

                $booking->update([
                    'status'        => 'deposit_paid',
                    'order_id'      => $numericOrderId,
                    'order_name'    => $orderName,
                    'expires_at'    => now()->addDays($holdDurationDays),
                    'deposit_paid_at' => now(),
                    'draft_order_id'=> null,
                    'checkout_url'  => null,
                    'payment_status'=> 'partially_paid',
                ]);

                // Dispatch webhook job in background to set up Shopify holds, etc.
                try {
                    $shop = User::find($booking->shop_id);
                    if ($shop) {
                        OrdersPaidJob::dispatch($shop->name, (object)[
                            'id' => $numericOrderId,
                            'name' => $orderName,
                            'tags' => 'buylater-deposit',
                            'financial_status' => 'partially_paid',
                            'fulfillment_status' => 'unfulfilled',
                            'line_items' => []
                        ]);
                    }
                } catch (\Exception $jobEx) {
                    Log::error('getOrderBooking: Failed to dispatch OrdersPaidJob: ' . $jobEx->getMessage());
                }
            }

            $settings = Setting::where('shop_id', $booking->shop_id)->first();
            $holdDurationDays = $settings ? (int) ($settings->hold_duration_days ?? 14) : 14;

            $depositPaidAt = $booking->updated_at;
            $expiryDate = $depositPaidAt->copy()->addDays($holdDurationDays);

            if ($booking->status === 'deposit_paid' && empty($booking->checkout_url)) {
                Log::info("getOrderBooking: Generating remaining balance draft order on the fly for booking ID {$booking->id}");
                $booking->createRemainingBalanceDraftOrder();
            }

            $bookingArray = $booking->toArray();
            $bookingArray['expires_at'] = $expiryDate->toIso8601String();

            if ($booking->status === 'deposit_paid' && now()->gt($expiryDate)) {
                $bookingArray['status'] = 'expired';
            }

            return response()->json([
                'booking' => $bookingArray
            ]);

        } catch (\Exception $e) {
            Log::error('AppProxy: Exception fetching order booking: ' . $e->getMessage());
            return response()->json(['booking' => null]);
        }
    }

}
