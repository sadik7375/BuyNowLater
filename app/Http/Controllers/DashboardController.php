<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\Reminder;
use App\Models\Subscriber;
use App\Models\Booking;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $shop = auth()->user();

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
            ]
        );

        // ---------- Self-Healing: Sync Status of Active Bookings ----------
        $activeBookingsWithDraft = Booking::where('shop_id', $shop->id)
            ->whereIn('status', ['pending', 'deposit_paid'])
            ->whereNotNull('draft_order_id')
            ->get();

        if ($activeBookingsWithDraft->isNotEmpty()) {
            $draftOrderIds = $activeBookingsWithDraft->pluck('draft_order_id')->filter()->toArray();
            if (!empty($draftOrderIds)) {
                try {
                    $response = $shop->api()->rest(
                        'GET',
                        '/admin/api/' . config('shopify-app.api_version') . '/draft_orders.json',
                        ['ids' => implode(',', $draftOrderIds)]
                    );
                    if (!$response['errors']) {
                        $draftOrders = $response['body']['draft_orders'] ?? [];
                        $draftOrdersMap = [];
                        foreach ($draftOrders as $do) {
                            $doArray = $this->normalizeDraftOrder($do);
                            if ($doArray && isset($doArray['id'])) {
                                $draftOrdersMap[$doArray['id']] = $doArray;
                            }
                        }

                        foreach ($activeBookingsWithDraft as $booking) {
                            if (isset($draftOrdersMap[$booking->draft_order_id])) {
                                $draftOrder = $draftOrdersMap[$booking->draft_order_id];
                                $shopifyStatus = $draftOrder['status'] ?? '';
                                
                                if ($shopifyStatus === 'completed') {
                                    $isRemaining = $this->isRemainingBalanceDraftOrder($draftOrder);
                                    if ($booking->status === 'pending' && !$isRemaining) {
                                        $holdDurationDays = $settings->hold_duration_days ?? 14;
                                        $booking->update([
                                            'status' => 'deposit_paid',
                                            'expires_at' => now()->addDays($holdDurationDays),
                                            'draft_order_id' => null,
                                            'checkout_url' => null,
                                        ]);
                                        \Illuminate\Support\Facades\Log::info("Sync index: Booking ID {$booking->id} deposit paid on Shopify. Status updated to deposit_paid.");
                                    } elseif ($booking->status === 'deposit_paid' && $isRemaining) {
                                        $booking->update([
                                            'status' => 'completed'
                                        ]);
                                        \Illuminate\Support\Facades\Log::info("Sync index: Booking ID {$booking->id} balance paid on Shopify. Status updated to completed.");
                                    }
                                }
                            }
                        }
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("Failed to sync Shopify draft orders in index(): " . $e->getMessage());
                }
            }
        }

        // ---------- Date Filter Handling ----------
        $dateFilter = $request->query('date_filter', 'all'); // all, today, week, custom
        $start = null;
        $end = null;
        if ($dateFilter === 'today') {
            $start = Carbon::today();
            $end = Carbon::today()->endOfDay();
        } elseif ($dateFilter === 'week') {
            $start = Carbon::now()->startOfWeek();
            $end = Carbon::now()->endOfWeek();
        } elseif ($dateFilter === 'custom') {
            $startDate = $request->query('start_date');
            $endDate = $request->query('end_date');
            if ($startDate && $endDate) {
                $start = Carbon::parse($startDate)->startOfDay();
                $end = Carbon::parse($endDate)->endOfDay();
            }
        }
        // Prepare filter closure for reuse
        $filterClosure = function($query) use ($dateFilter, $start, $end) {
            if ($dateFilter !== 'all' && $start && $end) {
                $query->whereBetween('created_at', [$start, $end]);
            }
        };

        $reminders   = Reminder::where('shop_id', $shop->id)
            ->when($dateFilter !== 'all' && $start && $end, fn($q) => $q->whereBetween('created_at', [$start, $end]))
            ->orderBy('created_at', 'desc')
            ->get();
        $subscribers = Subscriber::where('shop_id', $shop->id)
            ->when($dateFilter !== 'all' && $start && $end, fn($q) => $q->whereBetween('created_at', [$start, $end]))
            ->orderBy('created_at', 'desc')
            ->get();
        $bookings    = Booking::where('shop_id', $shop->id)
            ->where('status', '!=', 'pending')
            ->when($dateFilter !== 'all' && $start && $end, fn($q) => $q->whereBetween('created_at', [$start, $end]))
            ->orderBy('created_at', 'desc')
            ->get();

        // --- Expiring Soon (Next 7 days, independent of date filter) ---
        $todayStart = Carbon::today()->startOfDay();
        $tomorrowStart = Carbon::tomorrow()->startOfDay();
        $tomorrowEnd = Carbon::tomorrow()->endOfDay();
        $sevenDaysFromNow = Carbon::now()->addDays(7)->endOfDay();

        $expiringSoonRaw = Booking::where('shop_id', $shop->id)
            ->whereNotIn('status', ['completed', 'expired'])
            ->whereNotNull('expires_at')
            ->where('expires_at', '>=', $todayStart)
            ->where('expires_at', '<=', $sevenDaysFromNow)
            ->orderBy('expires_at', 'asc')
            ->get();

        $expiringToday = $expiringSoonRaw->filter(fn($b) => Carbon::parse($b->expires_at)->isToday());
        $expiringTomorrow = $expiringSoonRaw->filter(fn($b) => Carbon::parse($b->expires_at)->isTomorrow());
        $expiringThisWeek = $expiringSoonRaw->filter(fn($b) => 
            !Carbon::parse($b->expires_at)->isToday() && !Carbon::parse($b->expires_at)->isTomorrow()
        );
        $isMockExpiring = false;

        // --- Status Counts (100% Dynamic from Database) ---
        $statusCounts = [
            'pending'      => 0,
            'deposit_paid' => $bookings->where('status', 'deposit_paid')->count(),
            'completed'    => $bookings->where('status', 'completed')->count(),
            'expired'      => $bookings->where('status', 'expired')->count(),
        ];
        $isMockStatus = false; // Always dynamic

        // --- Today's Scheduled Reminders ---
        $todayRemindersCount = Reminder::where('shop_id', $shop->id)
            ->whereDate('scheduled_at', Carbon::today())
            ->count();

        // --- Overview Stats (100% Dynamic from Database) ---
        $revenueRecovered = Booking::where('shop_id', $shop->id)
            ->where('status', 'completed')
            ->when($dateFilter !== 'all' && $start && $end, fn($q) => $q->whereBetween('created_at', [$start, $end]))
            ->sum('product_price');

        $activeBookings = Booking::where('shop_id', $shop->id)
            ->where('status', 'deposit_paid')
            ->when($dateFilter !== 'all' && $start && $end, fn($q) => $q->whereBetween('created_at', [$start, $end]))
            ->count();

        $alertSubscribersCount = Subscriber::where('shop_id', $shop->id)
            ->when($dateFilter !== 'all' && $start && $end, fn($q) => $q->whereBetween('created_at', [$start, $end]))
            ->count();

        $conversionRate = 0.0;

        // --- Wished Products (100% Dynamic from Database) ---
        $wishes = [];
        foreach ($reminders as $r) {
            $wishes[$r->product_title] = ($wishes[$r->product_title] ?? 0) + 1;
        }
        foreach ($subscribers as $s) {
            $wishes[$s->product_title] = ($wishes[$s->product_title] ?? 0) + 1;
        }
        arsort($wishes);
        $wishes = array_slice($wishes, 0, 5, true);

        // --- Live Alerts ---
        $liveAlerts = [];
        foreach ($subscribers as $s) {
            $liveAlerts[$s->product_title] = ($liveAlerts[$s->product_title] ?? 0) + 1;
        }
        arsort($liveAlerts);
        $liveAlerts = array_slice($liveAlerts, 0, 5, true);

        // --- Monthly Usage Count for Freemium Gating ---
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        $monthlyReminders = Reminder::where('shop_id', $shop->id)
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->count();

        $monthlySubscribers = Subscriber::where('shop_id', $shop->id)
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->count();

        $monthlyUsageCount = $monthlyReminders + $monthlySubscribers;

        $targetedProducts = [];
        if (($settings->product_targeting_type ?? 'all') === 'specific' && !empty($settings->targeted_product_ids)) {
            try {
                $ids = array_filter(explode(',', $settings->targeted_product_ids));
                if (!empty($ids)) {
                    $response = $shop->api()->rest('GET', '/admin/api/' . config('shopify-app.api_version') . '/products.json', [
                        'ids' => implode(',', $ids),
                        'fields' => 'id,title,handle,image'
                    ]);
                    if (!$response['errors']) {
                        $shopifyProducts = $response['body']['products'] ?? [];
                        foreach ($shopifyProducts as $sp) {
                            $targetedProducts[] = [
                                'id' => (string) $sp['id'],
                                'title' => $sp['title'],
                                'handle' => $sp['handle'],
                                'image' => $sp['image']['src'] ?? null,
                            ];
                        }
                    }
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Failed to fetch targeted products in index(): " . $e->getMessage());
            }
        }

        return view('dashboard.index', compact(
            'settings', 'reminders', 'subscribers', 'bookings',
            'revenueRecovered', 'activeBookings', 'alertSubscribersCount',
            'conversionRate', 'wishes', 'liveAlerts',
            'expiringToday', 'expiringTomorrow', 'expiringThisWeek', 'isMockExpiring',
            'statusCounts', 'isMockStatus', 'todayRemindersCount',
            'dateFilter', 'start', 'end', 'monthlyUsageCount', 'targetedProducts'
        ));
    }



    /**
     * Downgrade the shop to the Free Plan.
     */
    public function downgradePlan(Request $request)
    {
        $shop = auth()->user();

        // 1. Cancel the active charge via CancelCurrentPlan action
        $cancelCurrentPlan = resolve(\Osiset\ShopifyApp\Actions\CancelCurrentPlan::class);
        $cancelCurrentPlan(\Osiset\ShopifyApp\Objects\Values\ShopId::fromNative($shop->id));

        // 2. Set shop as freemium and clear plan_id
        $shopCommand = resolve(\Osiset\ShopifyApp\Contracts\Commands\Shop::class);
        $shopCommand->setAsFreemium(\Osiset\ShopifyApp\Objects\Values\ShopId::fromNative($shop->id));

        $shop->plan_id = null;
        $shop->save();

        return redirect()->to(route('home', $request->query()))->with('success', 'You have successfully downgraded to the Free Plan.');
    }

    public function searchProducts(Request $request)
    {
        $shop = auth()->user();
        $query = $request->query('q');

        // GraphQL Query for partial/fuzzy title search
        $gqlQuery = '
            query searchProducts($queryStr: String) {
                products(first: 20, query: $queryStr) {
                    edges {
                        node {
                            id
                            title
                            handle
                            featuredImage {
                                url
                            }
                        }
                    }
                }
            }
        ';

        // Construct wildcard query for title search (Lucene syntax)
        $queryStr = !empty($query) ? 'title:*' . $query . '*' : null;

        try {
            $response = $shop->api()->graph($gqlQuery, ['queryStr' => $queryStr]);
            
            $products = [];
            if (!$response['errors']) {
                $edges = $response['body']['data']['products']['edges'] ?? [];
                foreach ($edges as $edge) {
                    $node = $edge['node'];
                    $numericId = preg_replace('/[^0-9]/', '', $node['id']);
                    $products[] = [
                        'id' => (string) $numericId,
                        'title' => $node['title'] ?? '',
                        'handle' => $node['handle'] ?? '',
                        'image' => $node['featuredImage']['url'] ?? null,
                    ];
                }
            }
            return response()->json($products);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to search products in searchProducts() via GraphQL: " . $e->getMessage());

            // Fallback to REST API if GraphQL fails
            $params = [
                'limit' => 20,
                'fields' => 'id,title,handle,image',
            ];
            if (!empty($query)) {
                $params['title'] = $query;
            }

            try {
                $restResponse = $shop->api()->rest(
                    'GET',
                    '/admin/api/' . config('shopify-app.api_version') . '/products.json',
                    $params
                );

                $products = [];
                if (!$restResponse['errors']) {
                    $shopifyProducts = $restResponse['body']['products'] ?? [];
                    foreach ($shopifyProducts as $sp) {
                        $products[] = [
                            'id' => (string) $sp['id'],
                            'title' => $sp['title'],
                            'handle' => $sp['handle'],
                            'image' => $sp['image']['src'] ?? null,
                        ];
                    }
                }
                return response()->json($products);
            } catch (\Exception $restEx) {
                \Illuminate\Support\Facades\Log::error("REST Fallback failed in searchProducts(): " . $restEx->getMessage());
                return response()->json([]);
            }
        }
    }

    public function saveSettings(Request $request)
    {
        $shop = auth()->user();

        $request->validate([
            'sender_display_name'      => 'required|string|max:100',
            'deposit_percentage'       => 'required|integer|min:1|max:100',
            'button_text'              => 'required|string|max:50',
            'reminder_email_subject'   => 'required|string|max:255',
            'reminder_email_template'  => 'nullable|string',
            'discount_email_subject'   => 'required|string|max:255',
            'discount_email_template'  => 'nullable|string',
            'show_deposit'             => 'nullable|boolean',
            'show_reminders'           => 'nullable|boolean',
            'show_alerts'              => 'nullable|boolean',
            'hold_duration_days'       => 'required|integer|min:1|max:365',
            'product_targeting_type'   => 'nullable|string|in:all,specific',
            'targeted_product_ids'     => 'nullable|string',
        ]);

        Setting::updateOrCreate(
            ['shop_id' => $shop->id],
            [
                'sender_display_name'     => $request->input('sender_display_name'),
                'deposit_percentage'      => $request->input('deposit_percentage'),
                'button_text'             => $request->input('button_text'),
                'reminder_email_subject'  => $request->input('reminder_email_subject'),
                'reminder_email_template' => $request->input('reminder_email_template'),
                'discount_email_subject'  => $request->input('discount_email_subject'),
                'discount_email_template' => $request->input('discount_email_template'),
                'show_deposit'            => $request->has('show_deposit'),
                'show_reminders'          => $request->has('show_reminders'),
                'show_alerts'             => $request->has('show_alerts'),
                'hold_duration_days'      => $request->input('hold_duration_days'),
                'product_targeting_type'  => $request->input('product_targeting_type', 'all') ?: 'all',
                'targeted_product_ids'    => $request->input('targeted_product_ids'),
            ]
        );

        return redirect()->to(route('home', request()->query()) . '#settings')->with('success', 'Settings updated successfully.');
    }

    /**
     * Send manual reminder email.
     */
    public function sendReminder($id)
    {
        $shop = auth()->user();
        $booking = Booking::where('shop_id', $shop->id)->findOrFail($id);
        $setting = Setting::where('shop_id', $shop->id)->first();

        // --- SELF-HEALING: Sync Status from Shopify ---
        if ($booking->draft_order_id) {
            try {
                $response = $shop->api()->rest('GET', '/admin/api/' . config('shopify-app.api_version') . '/draft_orders/' . $booking->draft_order_id . '.json');
                if (!$response['errors']) {
                    $draftOrder = $response['body']['draft_order'] ?? null;
                    if ($draftOrder) {
                        $draftOrder = $this->normalizeDraftOrder($draftOrder);
                        $shopifyStatus = $draftOrder['status'] ?? '';
                        if ($shopifyStatus === 'completed') {
                            $isRemaining = $this->isRemainingBalanceDraftOrder($draftOrder);
                            if ($booking->status === 'pending' && !$isRemaining) {
                                $holdDurationDays = $setting->hold_duration_days ?? 14;
                                $booking->update([
                                    'status' => 'deposit_paid',
                                    'expires_at' => now()->addDays($holdDurationDays),
                                    'draft_order_id' => null,
                                    'checkout_url' => null,
                                ]);
                                $booking->status = 'deposit_paid';
                                \Illuminate\Support\Facades\Log::info("Sync: Booking ID {$booking->id} deposit paid on Shopify. Status updated to deposit_paid.");
                            } elseif ($booking->status === 'deposit_paid' && $isRemaining) {
                                $booking->update([
                                    'status' => 'completed'
                                ]);
                                $booking->status = 'completed';
                                \Illuminate\Support\Facades\Log::info("Sync: Booking ID {$booking->id} balance paid on Shopify. Status updated to completed.");
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Failed to sync Shopify draft order status for Booking ID {$booking->id}: " . $e->getMessage());
            }
        }

        if ($booking->status === 'completed') {
            return back()->with('error', 'This booking is already completed.');
        }

        if ($booking->status === 'expired') {
            return back()->with('error', 'This booking has expired.');
        }

        $apiKey = $setting->sendgrid_api_key ?? config('services.sendgrid.api_key');
        $fromEmail = $setting->sendgrid_from_email ?? config('services.sendgrid.from_email');

        // Format Shop Name
        $senderName = $setting->sender_display_name ?? null;
        if (empty($senderName)) {
            $shopDomain = $shop->name;
            $cleanName = str_replace('.myshopify.com', '', $shopDomain);
            $cleanName = ucwords(str_replace(['-', '_'], ' ', $cleanName));
            $senderName = $cleanName;
        }

        if ($booking->status === 'deposit_paid') {
            // For deposit paid, we send the remaining balance invoice link!
            try {
                $needsNewDraftOrder = true;
                $checkoutUrl = null;

                if ($booking->draft_order_id) {
                    // Fetch from Shopify to see if it is completed (deposit) or open (remaining balance)
                    $response = $shop->api()->rest('GET', '/admin/api/' . config('shopify-app.api_version') . '/draft_orders/' . $booking->draft_order_id . '.json');
                    
                    if (!$response['errors']) {
                        $draftOrder = $response['body']['draft_order'] ?? null;
                        if ($draftOrder) {
                            $draftOrder = $this->normalizeDraftOrder($draftOrder);
                            $status = $draftOrder['status'] ?? '';
                            if ($status === 'completed') {
                                $isRemainingBalance = $this->isRemainingBalanceDraftOrder($draftOrder);

                                if ($isRemainingBalance) {
                                    $booking->update(['status' => 'completed']);
                                    return back()->with('success', 'This booking has already been paid in full!');
                                }
                            } else {
                                $needsNewDraftOrder = false;
                                $checkoutUrl = $draftOrder['invoice_url'] ?? '';
                            }
                        }
                    }
                }

                if ($needsNewDraftOrder) {
                    $checkoutUrl = $booking->createRemainingBalanceDraftOrder();
                    if (!$checkoutUrl) {
                        return back()->with('error', 'Failed to generate Shopify draft order for remaining balance.');
                    }
                }

                if ($checkoutUrl) {
                    $subject = "Reminder: Complete Your Booking - Remaining Balance for " . $booking->product_title;
                    
                    $htmlContent = view('emails.booking_reminder', [
                        'booking' => $booking,
                        'senderName' => $senderName,
                        'buttonUrl' => $checkoutUrl,
                        'isDepositPaid' => true
                    ])->render();

                    \App\Services\SendGridService::send($apiKey, $fromEmail, $booking->email, $subject, $htmlContent);

                    return back()->with('success', 'Balance reminder email sent to ' . $booking->email);
                }

                return back()->with('error', 'Failed to retrieve invoice URL from Shopify response.');

            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Draft Order Balance Creation failed in sendReminder: ' . $e->getMessage());
                return back()->with('error', 'Error generating Shopify invoice: ' . $e->getMessage());
            }
        } else {
            // For pending (deposit not paid yet), send reminder to pay the deposit!
            $checkoutUrl = $booking->checkout_url;
            if (!$checkoutUrl || !$booking->draft_order_id) {
                try {
                    $productPrice = (float) $booking->product_price;
                    $depositAmount = (float) $booking->deposit_amount;
                    $remainingBalance = (float) $booking->remaining_balance;
                    $token = $booking->token;

                    // We always use a custom line item representing the deposit amount directly.
                    // This avoids currency localization/conversion issues and prevents confusing
                    // "Deposit Payment Adjustment" discounts from displaying on the checkout page.
                    $lineItems = [[
                        'title'             => 'Deposit — ' . $booking->product_title,
                        'price'             => number_format($depositAmount, 2, '.', ''),
                        'quantity'          => 1,
                        'requires_shipping' => false,
                        'properties'        => [
                            ['name' => '_token', 'value' => $token],
                            ['name' => 'Original Price', 'value' => '$' . number_format($productPrice, 2)],
                            ['name' => 'Remaining Balance', 'value' => '$' . number_format($remainingBalance, 2)],
                        ]
                    ]];

                    $draftOrderData = [
                        'draft_order' => [
                            'email' => $booking->email,
                            'customer' => [
                                'email' => $booking->email,
                            ],
                            'line_items' => $lineItems,
                            'note'  => 'BuyLater deposit — do not fulfill',
                            'tags'  => 'buylater-deposit',
                        ]
                    ];

                    \Illuminate\Support\Facades\Log::info("Recreating deposit draft order for booking ID {$booking->id} in sendReminder");
                    $createRes = $shop->api()->rest('POST', '/admin/api/' . config('shopify-app.api_version') . '/draft_orders.json', $draftOrderData);

                    if ($createRes['errors'] === false && isset($createRes['body']['draft_order'])) {
                        $draftOrder = $createRes['body']['draft_order'];
                        
                        if (is_object($draftOrder) && method_exists($draftOrder, 'toArray')) {
                            $draftOrderArray = $draftOrder->toArray();
                        } elseif ($draftOrder instanceof \ArrayAccess) {
                            $draftOrderArray = json_decode(json_encode($draftOrder), true);
                        } else {
                            $draftOrderArray = (array) $draftOrder;
                        }

                        $draftOrderId = $draftOrderArray['id'] ?? null;
                        // Fix 32-bit PHP integer overflow: extract ID from GraphQL string
                        $gqlId = $draftOrderArray['admin_graphql_api_id'] ?? null;
                        if ($gqlId && preg_match('/DraftOrder\/(\d+)/', $gqlId, $matches)) {
                            $draftOrderId = $matches[1];
                        } elseif ($draftOrderId !== null) {
                            $draftOrderId = (string) $draftOrderId;
                        }
                        $checkoutUrl  = $draftOrderArray['invoice_url'] ?? null;

                        // If invoice_url is missing, try to generate it by sending invoice
                        if (empty($checkoutUrl) && $draftOrderId) {
                            $shop->api()->rest(
                                'POST',
                                '/admin/api/' . config('shopify-app.api_version') . '/draft_orders/' . $draftOrderId . '/send_invoice.json',
                                ['draft_order_invoice' => ['to' => $booking->email]]
                            );
                            
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
                            }
                        }

                        if ($checkoutUrl) {
                            $booking->update([
                                'draft_order_id' => $draftOrderId,
                                'checkout_url' => $checkoutUrl
                            ]);
                        } else {
                            return back()->with('error', 'Failed to retrieve checkout URL from Shopify response.');
                        }
                    } else {
                        return back()->with('error', 'Shopify draft order creation failed: ' . json_encode($createRes['body']));
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Draft Order Deposit Re-creation failed in sendReminder: ' . $e->getMessage());
                    return back()->with('error', 'Error generating Shopify invoice for deposit: ' . $e->getMessage());
                }
            }

            $subject = "Reminder: Secure Your Booking for " . $booking->product_title;
            
            $htmlContent = view('emails.booking_reminder', [
                'booking' => $booking,
                'senderName' => $senderName,
                'buttonUrl' => $checkoutUrl,
                'isDepositPaid' => false
            ])->render();

            $success = \App\Services\SendGridService::send($apiKey, $fromEmail, $booking->email, $subject, $htmlContent);

            if ($success) {
                return back()->with('success', 'Deposit reminder email sent to ' . $booking->email);
            }

            return back()->with('error', 'Failed to dispatch email via SendGrid.');
        }
    }

    /**
     * Send remaining balance link via Draft Order creation.
     */
    public function sendBalanceLink($id)
    {
        $shop = auth()->user();
        $booking = Booking::where('shop_id', $shop->id)->findOrFail($id);
        $setting = Setting::where('shop_id', $shop->id)->first();

        // --- SELF-HEALING: Sync Status from Shopify ---
        if ($booking->draft_order_id) {
            try {
                $response = $shop->api()->rest('GET', '/admin/api/' . config('shopify-app.api_version') . '/draft_orders/' . $booking->draft_order_id . '.json');
                if (!$response['errors']) {
                    $draftOrder = $response['body']['draft_order'] ?? null;
                    if ($draftOrder) {
                        $draftOrder = $this->normalizeDraftOrder($draftOrder);
                        $shopifyStatus = $draftOrder['status'] ?? '';
                        if ($shopifyStatus === 'completed') {
                            $isRemaining = $this->isRemainingBalanceDraftOrder($draftOrder);
                            if ($booking->status === 'pending' && !$isRemaining) {
                                $holdDurationDays = $setting->hold_duration_days ?? 14;
                                $booking->update([
                                    'status' => 'deposit_paid',
                                    'expires_at' => now()->addDays($holdDurationDays),
                                ]);
                                $booking->status = 'deposit_paid';
                                \Illuminate\Support\Facades\Log::info("Sync: Booking ID {$booking->id} deposit paid on Shopify. Status updated to deposit_paid.");
                            } elseif ($booking->status === 'deposit_paid' && $isRemaining) {
                                $booking->update([
                                    'status' => 'completed'
                                ]);
                                $booking->status = 'completed';
                                \Illuminate\Support\Facades\Log::info("Sync: Booking ID {$booking->id} balance paid on Shopify. Status updated to completed.");
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Failed to sync Shopify draft order status for Booking ID {$booking->id}: " . $e->getMessage());
            }
        }

        if ($booking->status === 'completed') {
            return back()->with('error', 'This booking is already completed.');
        }

        if ($booking->status === 'expired') {
            return back()->with('error', 'This booking has expired.');
        }

        if ($booking->status === 'pending') {
            return back()->with('error', 'Customer has not paid the deposit yet. Please send a deposit reminder instead.');
        }

        try {
            $needsNewDraftOrder = true;
            $checkoutUrl = null;

            if ($booking->draft_order_id) {
                // Fetch from Shopify to see if it is completed (deposit) or open (remaining balance)
                $response = $shop->api()->rest('GET', '/admin/api/' . config('shopify-app.api_version') . '/draft_orders/' . $booking->draft_order_id . '.json');
                
                if (!$response['errors']) {
                    $draftOrder = $response['body']['draft_order'] ?? null;
                    if ($draftOrder) {
                        $draftOrder = $this->normalizeDraftOrder($draftOrder);
                        $status = $draftOrder['status'] ?? '';
                        if ($status === 'completed') {
                            $isRemaining = $this->isRemainingBalanceDraftOrder($draftOrder);
                            if ($isRemaining) {
                                $booking->update(['status' => 'completed']);
                                return back()->with('success', 'This booking has already been paid in full!');
                            }
                        } else {
                            $needsNewDraftOrder = false;
                            $checkoutUrl = $draftOrder['invoice_url'] ?? '';
                        }
                    }
                }
            }

            if ($needsNewDraftOrder) {
                if ($booking->variant_id) {
                    // Fetch actual variant price to calculate correct fixed-amount discount
                    $actualVariantPrice = (float) $booking->product_price;
                    try {
                        $variantRes = $shop->api()->rest('GET', '/admin/api/' . config('shopify-app.api_version') . '/variants/' . $booking->variant_id . '.json');
                        if ($variantRes['errors'] === false && isset($variantRes['body']['variant'])) {
                            $vData = $variantRes['body']['variant'];
                            if (is_object($vData) && method_exists($vData, 'toArray')) { $vData = $vData->toArray(); }
                            elseif (is_object($vData)) { $vData = json_decode(json_encode($vData), true); }
                            $actualVariantPrice = (float) ($vData['price'] ?? $booking->product_price);
                        }
                    } catch (\Exception $e) { /* fallback to product_price */ }
                    $discountAmount = max(0, $actualVariantPrice - (float) $booking->deposit_amount);
                    $lineItems = [
                        [
                            'variant_id' => (float) $booking->variant_id,
                            'quantity' => 1,
                            'requires_shipping' => true,
                            'applied_discount' => [
                                'title' => 'Deposit Payment Adjustment',
                                'description' => 'Original Deposit Paid',
                                'value' => number_format($discountAmount, 2, '.', ''),
                                'value_type' => 'fixed_amount',
                            ],
                        ]
                    ];
                } else {
                    $lineItems = [
                        [
                            'title' => 'Remaining Balance - ' . $booking->product_title,
                            'price' => number_format($booking->remaining_balance, 2, '.', ''),
                            'quantity' => 1,
                            'requires_shipping' => true,
                        ]
                    ];
                }

                // Create Draft Order using Shopify REST API via Osiset/Laravel-Shopify
                $draftOrderData = [
                    'draft_order' => [
                        'line_items' => $lineItems,
                        'customer' => [
                            'email' => $booking->email,
                            'first_name' => $booking->customer_name ?? 'Valued',
                            'last_name' => 'Customer'
                        ],
                        'use_customer_default_address' => true,
                        'note' => 'Remaining balance payment. Original Deposit Paid: $' . number_format($booking->deposit_amount, 2),
                        'note_attributes' => [
                            [
                                'name' => 'buylater_token',
                                'value' => $booking->token
                            ],
                            [
                                'name' => 'Original Deposit Paid',
                                'value' => '$' . number_format($booking->deposit_amount, 2)
                            ]
                        ]
                    ]
                ];

                $response = $shop->api()->rest('POST', '/admin/api/' . config('shopify-app.api_version') . '/draft_orders.json', $draftOrderData);

                if ($response['errors']) {
                    throw new \Exception('Shopify Draft Order API Error: ' . json_encode($response['body']));
                }

                $draftOrder = $response['body']['draft_order'] ?? null;
                if ($draftOrder) {
                    $draftOrder = $this->normalizeDraftOrder($draftOrder);
                    $draftOrderId = $draftOrder['id'] ?? null;
                    // Fix 32-bit PHP integer overflow: extract ID from GraphQL string
                    $gqlId = $draftOrder['admin_graphql_api_id'] ?? null;
                    if ($gqlId && preg_match('/DraftOrder\/(\d+)/', $gqlId, $matches)) {
                        $draftOrderId = $matches[1];
                    } elseif ($draftOrderId !== null) {
                        $draftOrderId = (string) $draftOrderId;
                    }
                    $checkoutUrl = $draftOrder['invoice_url'] ?? null;

                    $booking->update([
                        'draft_order_id' => $draftOrderId,
                        'checkout_url' => $checkoutUrl
                    ]);
                }
            }

            if ($checkoutUrl) {
                // Also send email with invoice URL to the customer
                $setting = Setting::where('shop_id', $shop->id)->first();
                $apiKey = $setting->sendgrid_api_key ?? config('services.sendgrid.api_key');
                $fromEmail = $setting->sendgrid_from_email ?? config('services.sendgrid.from_email');

                // Format Shop Name
                $senderName = $setting->sender_display_name ?? null;
                if (empty($senderName)) {
                    $shopDomain = $shop->name;
                    $cleanName = str_replace('.myshopify.com', '', $shopDomain);
                    $cleanName = ucwords(str_replace(['-', '_'], ' ', $cleanName));
                    $senderName = $cleanName;
                }

                $subject = "Complete Your Booking - Remaining Balance for " . $booking->product_title;
                
                // Render the beautiful HTML view
                $htmlContent = view('emails.booking_reminder', [
                    'booking' => $booking,
                    'senderName' => $senderName,
                    'buttonUrl' => $checkoutUrl,
                    'isDepositPaid' => true
                ])->render();

                \App\Services\SendGridService::send($apiKey, $fromEmail, $booking->email, $subject, $htmlContent);

                return back()->with('success', 'Draft order invoice created and sent successfully!');
            }

            return back()->with('error', 'Failed to retrieve invoice URL from Shopify response.');

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Draft Order Balance Creation failed: ' . $e->getMessage());
            return back()->with('error', 'Error generating Shopify invoice: ' . $e->getMessage());
        }
    }

    /**
     * Handle Merchant Feedback/Complaints Submission.
     */
    public function submitFeedback(Request $request)
    {
        $shop = auth()->user();
        if (!$shop) {
            return response()->json(['success' => false, 'message' => 'Unauthorized or session expired.'], 401);
        }

        $request->validate([
            'feedback_type' => 'required|string',
            'feedback_contact' => 'required|email',
            'feedback_subject' => 'required|string|max:255',
            'feedback_message' => 'required|string',
        ]);

        $feedbackType = $request->input('feedback_type');
        $contactEmail = $request->input('feedback_contact');
        $subjectText = $request->input('feedback_subject');
        $messageText = $request->input('feedback_message');

        // Construct notification email body
        $htmlContent = "
            <h2>New Support Feedback/Complaint</h2>
            <p><strong>Shop:</strong> {$shop->name}</p>
            <p><strong>Contact Email:</strong> {$contactEmail}</p>
            <p><strong>Feedback Type:</strong> {$feedbackType}</p>
            <p><strong>Subject:</strong> {$subjectText}</p>
            <hr style='border: none; border-top: 1px solid #ddd; margin: 15px 0;' />
            <p><strong>Message:</strong></p>
            <div style='background: #f9f9f9; padding: 15px; border-radius: 6px; border: 1px solid #eee; white-space: pre-wrap;'>
                " . e($messageText) . "
            </div>
        ";

        $subject = "[BuyLater Admin Support] {$feedbackType}: {$subjectText}";

        // Send to developer (configurable in .env, falling back to sadik7375@gmail.com)
        $developerEmail = env('DEVELOPER_FEEDBACK_EMAIL', 'sadik7375@gmail.com');

        // Get SendGrid credentials (priority: shop-specific settings, fallback: global config)
        $setting = Setting::where('shop_id', $shop->id)->first();
        $apiKey = $setting->sendgrid_api_key ?? config('services.sendgrid.api_key');
        $fromEmail = $setting->sendgrid_from_email ?? config('services.sendgrid.from_email');

        // Fallback sender if not configured
        if (empty($fromEmail)) {
            $fromEmail = config('mail.from.address') ?: 'no-reply@buynowlater.com';
        }

        try {
            $sent = \App\Services\SendGridService::send($apiKey, $fromEmail, $developerEmail, $subject, $htmlContent);
            if ($sent) {
                return response()->json([
                    'success' => true,
                    'message' => 'Thank you! Your feedback has been sent successfully. We will get back to you soon.'
                ]);
            } else {
                throw new \Exception('Failed to deliver email through SendGridService.');
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Feedback submission email failed: ' . $e->getMessage());
            
            // Try standard Laravel Mail direct fallback
            try {
                \Illuminate\Support\Facades\Mail::html($htmlContent, function ($message) use ($developerEmail, $subject) {
                    $message->to($developerEmail)
                            ->subject($subject);
                });
                return response()->json([
                    'success' => true,
                    'message' => 'Thank you! Your feedback has been sent successfully. We will get back to you soon.'
                ]);
            } catch (\Exception $mailEx) {
                \Illuminate\Support\Facades\Log::error('Feedback fallback Laravel Mail failed: ' . $mailEx->getMessage());
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to send feedback at this time. Please contact us via email.'
                ], 500);
            }
        }
    }

    /**
     * Convert response object/array to standard array.
     */
    private function normalizeDraftOrder($draftOrder)
    {
        if (!$draftOrder) {
            return null;
        }
        if (is_object($draftOrder) && method_exists($draftOrder, 'toArray')) {
            return $draftOrder->toArray();
        } elseif ($draftOrder instanceof \ArrayAccess) {
            return json_decode(json_encode($draftOrder), true);
        } elseif (is_object($draftOrder)) {
            return (array) $draftOrder;
        }
        return $draftOrder;
    }

    /**
     * Check if a draft order is for the remaining balance.
     */
    private function isRemainingBalanceDraftOrder($draftOrder): bool
    {
        $draftOrder = $this->normalizeDraftOrder($draftOrder);
        $lineItems = $draftOrder['line_items'] ?? [];
        foreach ($lineItems as $item) {
            $title = is_object($item) ? ($item->title ?? '') : ($item['title'] ?? '');
            if (str_contains($title, 'Remaining Balance')) {
                return true;
            }

            // Check applied discount description (for variant-linked items)
            $appliedDiscount = is_object($item) ? ($item->applied_discount ?? null) : ($item['applied_discount'] ?? null);
            if ($appliedDiscount) {
                if (is_object($appliedDiscount) && method_exists($appliedDiscount, 'toArray')) {
                    $appliedDiscount = $appliedDiscount->toArray();
                } else {
                    $appliedDiscount = (array) $appliedDiscount;
                }
                $desc = $appliedDiscount['description'] ?? '';
                if (str_contains($desc, 'Original Deposit Paid')) {
                    return true;
                }
            }
        }

        // Also check note attributes
        $noteAttributes = $draftOrder['note_attributes'] ?? [];
        foreach ($noteAttributes as $attr) {
            $name = is_object($attr) ? ($attr->name ?? '') : ($attr['name'] ?? '');
            if (str_contains($name, 'Original Deposit Paid')) {
                return true;
            }
        }

        return false;
    }
}
