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

        // Generate a unique token
        $token = strtolower(Str::random(32));

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
                ['name' => 'Original Price', 'value' => number_format($productPrice, 2) . ' ' . $currency],
                ['name' => 'Remaining Balance', 'value' => number_format($remainingBalance, 2) . ' ' . $currency],
            ]
        ]];

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

        // Create booking in database
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

        $settings = Setting::where('shop_id', $shop->id)->first();

        \Illuminate\Support\Facades\Log::info('AppProxy settings found:', [
            'settings_exists' => $settings ? true : false,
            'deposit_percentage' => $settings ? $settings->deposit_percentage : null,
            'hold_duration_days' => $settings ? $settings->hold_duration_days : null,
        ]);

        $showDeposit = $settings ? (bool) ($settings->show_deposit ?? true) : true;

        // Check Product Targeting
        $productTargetingType = $settings ? ($settings->product_targeting_type ?? 'all') : 'all';
        $isWidgetEnabled = true;

        if ($productTargetingType === 'specific') {
            $targetedProductIdsStr = $settings ? $settings->targeted_product_ids : '';
            $targetedProductIds = array_filter(explode(',', $targetedProductIdsStr));
            $currentProductId = $request->query('product_id');
            
            $isWidgetEnabled = false;
            if ($currentProductId) {
                // Strip non-digits to handle format like "gid://shopify/Product/12345" or raw numeric "12345"
                $cleanCurrentId = preg_replace('/[^0-9]/', '', $currentProductId);
                foreach ($targetedProductIds as $id) {
                    $cleanId = preg_replace('/[^0-9]/', '', $id);
                    if ($cleanId !== '' && $cleanId === $cleanCurrentId) {
                        $isWidgetEnabled = true;
                        break;
                    }
                }
            }
        }

        return response()->json([
            'enabled' => $isWidgetEnabled,
            'deposit_percentage' => $settings ? (int) $settings->deposit_percentage : 10,
            'show_deposit' => $showDeposit,
            'show_reminders' => $settings ? (bool) ($settings->show_reminders ?? true) : true,
            'show_alerts' => $settings ? (bool) ($settings->show_alerts ?? true) : true,
            'hold_duration_days' => $settings ? (int) ($settings->hold_duration_days ?? 14) : 14,
            'button_text' => $settings ? $settings->button_text : null,
            'use_selling_plan' => $settings ? (bool) ($settings->use_selling_plan ?? false) : false,
            'selling_plan_group_id' => $settings ? $settings->selling_plan_group_id : null,
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
}
