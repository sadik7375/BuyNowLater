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

        $isFreePlan = ($shop->plan_id === null || $shop->isFreemium());
        if ($isFreePlan) {
            $startOfMonth = Carbon::now()->startOfMonth();
            $endOfMonth = Carbon::now()->endOfMonth();
            
            $remindersCount = Reminder::where('shop_id', $shop->id)
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->count();
            
            $subscribersCount = Subscriber::where('shop_id', $shop->id)
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->count();
            
            if (($remindersCount + $subscribersCount) >= 20) {
                return response()->json([
                    'message' => 'Monthly limit of 20 events reached on the Free plan. Please upgrade to Pro for unlimited usage.'
                ], 403);
            }
        }

        // Parse scheduled date
        $scheduledInput = $request->input('scheduled_at_utc') ?: $request->input('scheduled_at');
        $scheduledAt = Carbon::parse($scheduledInput);

        // Check if reminder is in the past
        if ($scheduledAt->isPast()) {
            return response()->json(['message' => 'Reminder date cannot be in the past.'], 422);
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

        $isFreePlan = ($shop->plan_id === null || $shop->isFreemium());
        if ($isFreePlan) {
            $startOfMonth = Carbon::now()->startOfMonth();
            $endOfMonth = Carbon::now()->endOfMonth();
            
            $remindersCount = Reminder::where('shop_id', $shop->id)
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->count();
            
            $subscribersCount = Subscriber::where('shop_id', $shop->id)
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->count();
            
            if (($remindersCount + $subscribersCount) >= 20) {
                return response()->json([
                    'message' => 'Monthly limit of 20 events reached on the Free plan. Please upgrade to Pro for unlimited usage.'
                ], 403);
            }
        }

        // Fetch product's base currency price from Shopify Admin API to resolve currency mismatches
        $productPrice = $request->input('product_price');
        try {
            $productIdClean = $request->input('product_id');
            if (str_contains($productIdClean, '/')) {
                $parts = explode('/', $productIdClean);
                $productIdClean = end($parts);
            }

            $response = $shop->api()->rest(
                'GET',
                '/admin/api/' . config('shopify-app.api_version') . '/products/' . $productIdClean . '.json'
            );

            if ($response['errors'] === false && isset($response['body']['product'])) {
                $productData = $response['body']['product'];
                if (is_object($productData) && method_exists($productData, 'toArray')) {
                    $productData = $productData->toArray();
                } else {
                    $productData = json_decode(json_encode($productData), true);
                }

                $variants = $productData['variants'] ?? [];
                $lowestPrice = null;
                foreach ($variants as $variant) {
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
        $scheduledAt = Carbon::parse($scheduledInput);

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

        $isFreePlan = ($shop->plan_id === null || $shop->isFreemium());
        if ($isFreePlan) {
            return response()->json([
                'message' => 'Deposit bookings require the Pro Plan. Please upgrade to Pro.'
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
        $settings = Setting::where('shop_id', $shop->id)->first();
        $depositPercentage = $settings ? (int) $settings->deposit_percentage : 10;
        $depositAmount = round($productPrice * ($depositPercentage / 100), 2);
        $remainingBalance = $productPrice - $depositAmount;
        $holdDurationDays = $settings ? (int) ($settings->hold_duration_days ?? 14) : 14;

        // Generate a unique token
        $token = strtolower(Str::random(32));

        // -----------------------------------------------------------------------
        // Create a Shopify Draft Order.
        // Link to the real variant if variant_id is provided, using a line-item
        // discount so the checkout amount is exactly the deposit amount.
        // -----------------------------------------------------------------------
        $checkoutUrl  = null;
        $draftOrderId = null;

        $variantId = $request->input('variant_id');
        if ($variantId && str_contains($variantId, '/')) {
            $parts = explode('/', $variantId);
            $variantId = end($parts);
        }

        if ($variantId) {
            $discountPercentage = 100 - $depositPercentage;
            $lineItems = [[
                'variant_id'        => (int) $variantId,
                'quantity'          => 1,
                'requires_shipping' => false,
                'applied_discount'  => [
                    'title'       => 'Deposit Payment Adjustment',
                    'description' => 'Buy Now Later deposit discount',
                    'value'       => number_format($discountPercentage, 2, '.', ''),
                    'value_type'  => 'percentage',
                ],
                'properties'        => [
                    ['name' => '_token', 'value' => $token],
                    ['name' => 'Original Price', 'value' => '$' . number_format($productPrice, 2)],
                    ['name' => 'Remaining Balance', 'value' => '$' . number_format($remainingBalance, 2)],
                ]
            ]];
        } else {
            $lineItems = [[
                'title'             => 'Deposit — ' . $request->input('product_title'),
                'price'             => number_format($depositAmount, 2, '.', ''),
                'quantity'          => 1,
                'requires_shipping' => false,
                'properties'        => [
                    ['name' => '_token', 'value' => $token],
                    ['name' => 'Original Price', 'value' => '$' . number_format($productPrice, 2)],
                    ['name' => 'Remaining Balance', 'value' => '$' . number_format($remainingBalance, 2)],
                ]
            ]];
        }

        try {
            $draftOrderData = [
                'draft_order' => [
                    'email' => $request->input('email'),
                    'customer' => [
                        'email' => $request->input('email'),
                    ],
                    'line_items' => $lineItems,
                    'note'  => 'BuyLater deposit — do not fulfill',
                    'tags'  => 'buylater-deposit',
                ]
            ];

            Log::info('Deposit draft order: sending request to Shopify', [
                'api_version' => config('shopify-app.api_version'),
                'shop' => $shopDomain,
            ]);

            $createRes = $shop->api()->rest('POST', '/admin/api/' . config('shopify-app.api_version') . '/draft_orders.json', $draftOrderData);

            Log::info('Deposit draft order create response', [
                'errors' => $createRes['errors'],
                'status' => $createRes['status'] ?? null,
                'body_type' => is_object($createRes['body']) ? get_class($createRes['body']) : gettype($createRes['body']),
            ]);

            if ($createRes['errors'] === false && isset($createRes['body']['draft_order'])) {
                $draftOrder = $createRes['body']['draft_order'];

                // ResponseAccess objects need careful handling — convert to array if needed
                if (is_object($draftOrder) && method_exists($draftOrder, 'toArray')) {
                    $draftOrderArray = $draftOrder->toArray();
                } elseif ($draftOrder instanceof \ArrayAccess) {
                    $draftOrderArray = json_decode(json_encode($draftOrder), true);
                } else {
                    $draftOrderArray = (array) $draftOrder;
                }

                Log::info('Draft order data extracted', [
                    'keys' => array_keys($draftOrderArray),
                    'id' => $draftOrderArray['id'] ?? null,
                    'invoice_url' => $draftOrderArray['invoice_url'] ?? 'NOT_PRESENT',
                    'status' => $draftOrderArray['status'] ?? null,
                ]);

                $draftOrderId = $draftOrderArray['id'] ?? null;
                $checkoutUrl  = $draftOrderArray['invoice_url'] ?? null;

                // If invoice_url is missing, try to get it by sending an invoice
                if (empty($checkoutUrl) && $draftOrderId) {
                    Log::info('invoice_url missing, attempting to send invoice to generate it');
                    try {
                        $invoiceRes = $shop->api()->rest(
                            'POST',
                            '/admin/api/' . config('shopify-app.api_version') . '/draft_orders/' . $draftOrderId . '/send_invoice.json',
                            ['draft_order_invoice' => ['to' => $request->input('email')]]
                        );
                        if ($invoiceRes['errors'] === false) {
                            // Re-fetch the draft order to get the invoice_url
                            $fetchRes = $shop->api()->rest(
                                'GET',
                                '/admin/api/' . config('shopify-app.api_version') . '/draft_orders/' . $draftOrderId . '.json'
                            );
                            if ($fetchRes['errors'] === false && isset($fetchRes['body']['draft_order'])) {
                                $refetchedOrder = $fetchRes['body']['draft_order'];
                                if (is_object($refetchedOrder) && method_exists($refetchedOrder, 'toArray')) {
                                    $refetchedArray = $refetchedOrder->toArray();
                                } else {
                                    $refetchedArray = json_decode(json_encode($refetchedOrder), true);
                                }
                                $checkoutUrl = $refetchedArray['invoice_url'] ?? null;
                                Log::info('Re-fetched draft order invoice_url', ['checkout_url' => $checkoutUrl]);
                            }
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
                // Log the full body for debugging
                $bodyData = $createRes['body'] ?? null;
                if (is_object($bodyData) && method_exists($bodyData, 'toArray')) {
                    $bodyData = $bodyData->toArray();
                }
                Log::error('Shopify deposit draft order creation failed', [
                    'errors' => $createRes['errors'],
                    'body'   => $bodyData,
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
            'draft_order_id' => $draftOrderId,
            'checkout_url' => $checkoutUrl,
            'status' => 'pending',
            'token' => $token,
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
        if (!$shop) {
            $shopDomain = $request->query('shop') ?: $request->input('shop');
            if ($shopDomain) {
                $shop = User::where('name', $shopDomain)->first();
            }
        }

        if (!$shop) {
            return response()->json(['message' => 'Shop not found.'], 404);
        }

        $settings = Setting::where('shop_id', $shop->id)->first();

        $isFreePlan = ($shop->plan_id === null || $shop->isFreemium());
        $showDeposit = $settings ? (bool) ($settings->show_deposit ?? true) : true;
        if ($isFreePlan) {
            $showDeposit = false;
        }

        return response()->json([
            'deposit_percentage' => $settings ? (int) $settings->deposit_percentage : 10,
            'show_deposit' => $showDeposit,
            'show_reminders' => $settings ? (bool) ($settings->show_reminders ?? true) : true,
            'show_alerts' => $settings ? (bool) ($settings->show_alerts ?? true) : true,
            'hold_duration_days' => $settings ? (int) ($settings->hold_duration_days ?? 14) : 14,
        ]);
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
            // Fetch customer details from Shopify Admin API to get their email securely
            $response = $shop->api()->rest(
                'GET',
                '/admin/api/' . config('shopify-app.api_version') . '/customers/' . $customerId . '.json'
            );

            if ($response['errors']) {
                Log::warning('AppProxy: Failed to fetch Shopify customer details.', [
                    'customer_id' => $customerId,
                    'errors' => $response['body']
                ]);
                return response()->json(['bookings' => []]);
            }

            $customer = $response['body']['customer'] ?? null;
            if (!$customer) {
                return response()->json(['bookings' => []]);
            }

            // Normalization
            if (is_object($customer) && method_exists($customer, 'toArray')) {
                $customerArray = $customer->toArray();
            } else {
                $customerArray = json_decode(json_encode($customer), true);
            }

            $email = $customerArray['email'] ?? null;
            if (!$email) {
                return response()->json(['bookings' => []]);
            }

            $settings = Setting::where('shop_id', $shop->id)->first();
            $holdDurationDays = $settings ? (int) ($settings->hold_duration_days ?? 14) : 14;

            $bookings = Booking::where('shop_id', $shop->id)
                ->where('email', $email)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($booking) use ($holdDurationDays) {
                    $bookingArray = $booking->toArray();
                    
                    // Expiry is calculated from when the deposit was paid (updated_at)
                    $depositPaidAt = $booking->updated_at;
                    $expiryDate = $depositPaidAt->copy()->addDays($holdDurationDays);
                    
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
