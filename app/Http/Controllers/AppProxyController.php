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

        // Parse scheduled date
        $scheduledAt = Carbon::parse($request->input('scheduled_at'));

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
                'product_price' => $request->input('product_price'),
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

        $scheduledAt = Carbon::parse($request->input('scheduled_at'));

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
        // Create a temporary Shopify Product with the deposit price as variant.
        // Then generate a /cart/ checkout URL using that variant ID.
        // This avoids Draft Order API (protected customer data restriction).
        // -----------------------------------------------------------------------
        $checkoutUrl  = null;
        $draftOrderId = null;

        try {
            $draftOrderData = [
                'draft_order' => [
                    'email' => $request->input('email'),
                    'customer' => [
                        'email' => $request->input('email'),
                    ],
                    'line_items' => [[
                        'title'             => 'Deposit — ' . $request->input('product_title'),
                        'price'             => number_format($depositAmount, 2, '.', ''),
                        'quantity'          => 1,
                        'requires_shipping' => false,
                        'properties'        => [
                            ['name' => '_token', 'value' => $token],
                            ['name' => 'Original Price', 'value' => '$' . number_format($productPrice, 2)],
                            ['name' => 'Remaining Balance', 'value' => '$' . number_format($remainingBalance, 2)],
                        ]
                    ]],
                    'note'  => 'BuyLater deposit — do not fulfill',
                    'tags'  => 'buylater-deposit',
                ]
            ];

            $createRes = $shop->api()->rest('POST', '/admin/api/' . config('shopify-app.api_version') . '/draft_orders.json', $draftOrderData);

            Log::info('Deposit draft order create response', ['errors' => $createRes['errors'], 'status' => $createRes['status'] ?? null]);

            if ($createRes['errors'] === false && isset($createRes['body']['draft_order'])) {
                $draftOrder   = $createRes['body']['draft_order'];
                $draftOrderId = $draftOrder['id'];
                $checkoutUrl  = $draftOrder['invoice_url'];

                Log::info('Deposit draft order created, invoice URL generated', [
                    'draft_order_id' => $draftOrderId,
                    'checkout_url'   => $checkoutUrl,
                ]);
            } else {
                Log::error('Shopify deposit draft order creation failed', [
                    'errors' => $createRes['errors'],
                    'body'   => $createRes['body'] ?? [],
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Exception creating deposit draft order', ['message' => $e->getMessage()]);
        }

        // Create booking in database
        $booking = Booking::create([
            'shop_id' => $shop->id,
            'email' => $request->input('email'),
            'product_id' => $request->input('product_id'),
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
            ], 201);
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

        return response()->json([
            'deposit_percentage' => $settings ? (int) $settings->deposit_percentage : 10,
            'show_deposit' => $settings ? (bool) ($settings->show_deposit ?? true) : true,
            'show_reminders' => $settings ? (bool) ($settings->show_reminders ?? true) : true,
            'show_alerts' => $settings ? (bool) ($settings->show_alerts ?? true) : true,
            'hold_duration_days' => $settings ? (int) ($settings->hold_duration_days ?? 14) : 14,
        ]);
    }
}
