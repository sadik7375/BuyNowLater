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

        $activeTab = 'tab-overview';
        $subTab = 'support';
        if ($request->is('bookings')) {
            $activeTab = 'tab-bookings-list';
        } elseif ($request->is('reminders')) {
            $activeTab = 'tab-reminders-list';
        } elseif ($request->is('price-alerts')) {
            $activeTab = 'tab-subscribers-list';
        } elseif ($request->is('app-settings')) {
            $activeTab = 'tab-settings';
        } elseif ($request->is('how-it-works') || $request->is('support') || $request->is('benefits')) {
            $activeTab = 'tab-support';
            $subTab = 'support';
        } elseif ($request->is('price-plan')) {
            $activeTab = 'tab-support';
            $subTab = 'pricing';
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

        // Auto-initialize Selling Plan Group if not set up
        if (!$settings->selling_plan_group_id || !$settings->use_selling_plan) {
            try {
                $sellingPlanService = app(\App\Services\SellingPlanService::class);
                $res = $sellingPlanService->createOrUpdatePlanGroup($shop, (int) $settings->deposit_percentage, (int) $settings->hold_duration_days);
                if ($res && !empty($res['group_id'])) {
                    $settings->update([
                        'selling_plan_group_id' => $res['group_id'],
                        'selling_plan_id' => $res['plan_id'] ?? null,
                        'use_selling_plan' => true,
                    ]);
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("DashboardController: Auto selling plan group setup failed: " . $e->getMessage());
            }
        }

        // ---------- Self-Healing: Sync Status of Active Bookings ----------
        $syncCacheKey = "shop_{$shop->id}_sync_lock";
        if ($request->query('force_sync') || !\Illuminate\Support\Facades\Cache::has($syncCacheKey)) {
            $this->syncBookingsWithShopify($shop);
            // Put a lock for 10 seconds to avoid redundant heavy API requests on rapid clicks/reloads
            \Illuminate\Support\Facades\Cache::put($syncCacheKey, true, now()->addSeconds(10));
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
            ->orderBy('created_at', 'desc')
            ->get();
        $subscribers = Subscriber::where('shop_id', $shop->id)
            ->orderBy('created_at', 'desc')
            ->get();
        $bookings    = Booking::where('shop_id', $shop->id)
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
            'pending'      => $bookings->where('status', 'pending')->count(),
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
            ->sum('product_price');

        $activeBookings = Booking::where('shop_id', $shop->id)
            ->where('status', 'deposit_paid')
            ->count();

        $alertSubscribersCount = Subscriber::where('shop_id', $shop->id)
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
            $productCacheKey = "shop_{$shop->id}_targeted_products_" . md5($settings->targeted_product_ids);
            $targetedProducts = \Illuminate\Support\Facades\Cache::remember($productCacheKey, now()->addMinutes(10), function() use ($shop, $settings) {
                $productsList = [];
                try {
                    $ids = array_filter(explode(',', $settings->targeted_product_ids));
                    if (!empty($ids)) {
                        $idsQuery = implode(' OR ', array_map(function($id) {
                            return 'id:' . $id;
                        }, $ids));

                        $gqlQuery = 'query getProducts($query: String!) {
                            products(first: 100, query: $query) {
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
                        }';

                        $response = $shop->api()->graph($gqlQuery, ['query' => $idsQuery]);

                        if (!$response['errors'] && isset($response['body']['data']['products']['edges'])) {
                            $edges = $response['body']['data']['products']['edges'];
                            foreach ($edges as $edge) {
                                $node = $edge['node'] ?? [];
                                $numericId = preg_replace('/[^0-9]/', '', $node['id'] ?? '');
                                $productsList[] = [
                                    'id' => (string) $numericId,
                                    'title' => $node['title'] ?? '',
                                    'handle' => $node['handle'] ?? '',
                                    'image' => $node['featuredImage']['url'] ?? null,
                                ];
                            }
                        }
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("Failed to fetch targeted products in index(): " . $e->getMessage());
                }
                return $productsList;
            });
        }

        return view('dashboard.index', compact(
            'settings', 'reminders', 'subscribers', 'bookings',
            'revenueRecovered', 'activeBookings', 'alertSubscribersCount',
            'conversionRate', 'wishes', 'liveAlerts',
            'expiringToday', 'expiringTomorrow', 'expiringThisWeek', 'isMockExpiring',
            'statusCounts', 'isMockStatus', 'todayRemindersCount',
            'dateFilter', 'start', 'end', 'monthlyUsageCount', 'targetedProducts',
            'activeTab', 'subTab'
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
            return response()->json([]);
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
                $gqlQuery = 'query getDraftOrder($id: ID!) {
                    draftOrder(id: $id) {
                        id
                        status
                        invoiceUrl
                        order {
                            id
                        }
                        lineItems(first: 50) {
                            edges {
                                node {
                                    title
                                    appliedDiscount {
                                        description
                                    }
                                }
                            }
                        }
                    }
                }';
                $response = $shop->api()->graph($gqlQuery, ['id' => 'gid://shopify/DraftOrder/' . $booking->draft_order_id]);
                if (!$response['errors']) {
                    $draftOrderNode = $response['body']['data']['draftOrder'] ?? null;
                    if ($draftOrderNode) {
                        $draftOrder = $this->normalizeGqlDraftOrder($draftOrderNode);
                        $shopifyStatus = $draftOrder['status'] ?? '';
                        if ($shopifyStatus === 'completed') {
                            $isRemaining = $this->isRemainingBalanceDraftOrder($draftOrder);
                            if ($booking->status === 'pending' && !$isRemaining) {
                                $holdDurationDays = $setting->hold_duration_days ?? 14;
                                $booking->update([
                                    'status' => 'deposit_paid',
                                    'expires_at' => now()->addDays($holdDurationDays),
                                    'deposit_paid_at' => now(),
                                    'draft_order_id' => null,
                                    'checkout_url' => null,
                                ]);
                                $booking->status = 'deposit_paid';
                                \Illuminate\Support\Facades\Log::info("Sync: Booking ID {$booking->id} deposit paid on Shopify. Status updated to deposit_paid.");
                            } elseif ($booking->status === 'deposit_paid' && $isRemaining) {
                                $booking->update([
                                    'status' => 'completed',
                                    'completed_at' => now(),
                                    'balance_order_id' => $draftOrder['order_id'] ?? null,
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

        if ($booking->status === 'deposit_paid') {
            // For deposit paid, we send the remaining balance invoice link!
            try {
                $needsNewDraftOrder = true;

                if ($booking->draft_order_id) {
                    // Fetch from Shopify to see if it is completed (deposit) or open (remaining balance)
                    $gqlQuery = 'query getDraftOrder($id: ID!) {
                        draftOrder(id: $id) {
                            id
                            status
                            invoiceUrl
                            order {
                                id
                            }
                            lineItems(first: 50) {
                                edges {
                                    node {
                                        title
                                        appliedDiscount {
                                            description
                                        }
                                    }
                                }
                            }
                        }
                    }';
                    $response = $shop->api()->graph($gqlQuery, ['id' => 'gid://shopify/DraftOrder/' . $booking->draft_order_id]);
                    
                    if (!$response['errors']) {
                        $draftOrderNode = $response['body']['data']['draftOrder'] ?? null;
                        if ($draftOrderNode) {
                            $draftOrder = $this->normalizeGqlDraftOrder($draftOrderNode);
                            $status = $draftOrder['status'] ?? '';
                            if ($status === 'completed') {
                                $isRemainingBalance = $this->isRemainingBalanceDraftOrder($draftOrder);

                                if ($isRemainingBalance) {
                                    $booking->update(['status' => 'completed']);
                                    return back()->with('success', 'This booking has already been paid in full!');
                                }
                            } else {
                                $needsNewDraftOrder = false;
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

                if ($booking->draft_order_id) {
                    $sent = $this->sendShopifyDraftOrderInvoice($shop, $booking->draft_order_id, $booking->email);
                    if ($sent) {
                        $booking->update(['invoice_sent' => true]);
                        return back()->with('success', 'Shopify remaining balance invoice sent successfully to ' . $booking->email);
                    }
                    return back()->with('error', 'Failed to send Shopify remaining balance invoice.');
                }

                return back()->with('error', 'Failed to retrieve invoice ID from Shopify.');

            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Draft Order Balance Creation failed in sendReminder: ' . $e->getMessage());
                return back()->with('error', 'Error generating Shopify invoice: ' . $e->getMessage());
            }
        } else {
            // For pending (deposit not paid yet), send reminder to pay the deposit!
            if (!$booking->draft_order_id) {
                try {
                    $productPrice = (float) $booking->product_price;
                    $depositAmount = (float) $booking->deposit_amount;
                    $remainingBalance = (float) $booking->remaining_balance;
                    $token = $booking->token;

                    // We always use a custom line item representing the deposit amount directly.
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
                            'email' => $booking->email,
                            'note' => 'BuyLater deposit — do not fulfill',
                            'tags' => ['buylater-deposit'],
                            'lineItems' => $gqlLineItems,
                        ]
                    ];

                    \Illuminate\Support\Facades\Log::info("Recreating deposit draft order for booking ID {$booking->id} in sendReminder via GraphQL");
                    $createMutation = 'mutation draftOrderCreate($input: DraftOrderInput!) {
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

                    $createRes = $shop->api()->graph($createMutation, $variables);

                    if ($createRes['errors'] === false && isset($createRes['body']['data']['draftOrderCreate']['draftOrder'])) {
                        $draftOrder = $createRes['body']['data']['draftOrderCreate']['draftOrder'];
                        $gqlId = $draftOrder['id'] ?? null;
                        $checkoutUrl = $draftOrder['invoiceUrl'] ?? null;

                        if ($gqlId && preg_match('/DraftOrder\/(\d+)/', $gqlId, $matches)) {
                            $draftOrderId = $matches[1];
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

            if ($booking->draft_order_id) {
                $sent = $this->sendShopifyDraftOrderInvoice($shop, $booking->draft_order_id, $booking->email);
                if ($sent) {
                    $booking->update(['invoice_sent' => true]);
                    return back()->with('success', 'Shopify deposit invoice sent successfully to ' . $booking->email);
                }
                return back()->with('error', 'Failed to send Shopify deposit invoice.');
            }

            return back()->with('error', 'Failed to retrieve deposit invoice ID from Shopify.');
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
                $gqlQuery = 'query getDraftOrder($id: ID!) {
                    draftOrder(id: $id) {
                        id
                        status
                        invoiceUrl
                        order {
                            id
                        }
                        lineItems(first: 50) {
                            edges {
                                node {
                                    title
                                    appliedDiscount {
                                        description
                                    }
                                }
                            }
                        }
                    }
                }';
                $response = $shop->api()->graph($gqlQuery, ['id' => 'gid://shopify/DraftOrder/' . $booking->draft_order_id]);
                if (!$response['errors']) {
                    $draftOrderNode = $response['body']['data']['draftOrder'] ?? null;
                    if ($draftOrderNode) {
                        $draftOrder = $this->normalizeGqlDraftOrder($draftOrderNode);
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
                $gqlQuery = 'query getDraftOrder($id: ID!) {
                    draftOrder(id: $id) {
                        id
                        status
                        invoiceUrl
                        order {
                            id
                        }
                        lineItems(first: 50) {
                            edges {
                                node {
                                    title
                                    appliedDiscount {
                                        description
                                    }
                                }
                            }
                        }
                    }
                }';
                $response = $shop->api()->graph($gqlQuery, ['id' => 'gid://shopify/DraftOrder/' . $booking->draft_order_id]);
                
                if (!$response['errors']) {
                    $draftOrderNode = $response['body']['data']['draftOrder'] ?? null;
                    if ($draftOrderNode) {
                        $draftOrder = $this->normalizeGqlDraftOrder($draftOrderNode);
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
                        $gqlQuery = 'query getVariant($id: ID!) {
                            productVariant(id: $id) {
                                price
                            }
                        }';
                        $variantRes = $shop->api()->graph($gqlQuery, ['id' => 'gid://shopify/ProductVariant/' . $booking->variant_id]);
                        if ($variantRes['errors'] === false && isset($variantRes['body']['data']['productVariant'])) {
                            $vData = $variantRes['body']['data']['productVariant'];
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

                // Create Draft Order using Shopify GraphQL API via Osiset/Laravel-Shopify
                $gqlLineItems = [];
                if ($booking->variant_id) {
                    $gqlLineItems[] = [
                        'variantId' => 'gid://shopify/ProductVariant/' . $booking->variant_id,
                        'quantity' => 1,
                        'appliedDiscount' => [
                            'title' => 'Deposit Payment Adjustment',
                            'description' => 'Original Deposit Paid',
                            'value' => (float) $discountAmount,
                            'valueType' => 'FIXED_AMOUNT',
                        ]
                    ];
                } else {
                    $gqlLineItems[] = [
                        'title' => 'Remaining Balance - ' . $booking->product_title,
                        'originalUnitPrice' => (string) number_format($booking->remaining_balance, 2, '.', ''),
                        'quantity' => 1,
                    ];
                }

                $variables = [
                    'input' => [
                        'email' => $booking->email,
                        'lineItems' => $gqlLineItems,
                        'note' => 'Remaining balance payment. Original Deposit Paid: $' . number_format($booking->deposit_amount, 2),
                        'customAttributes' => [
                            [
                                'key' => 'buylater_token',
                                'value' => (string) $booking->token
                            ],
                            [
                                'key' => 'Original Deposit Paid',
                                'value' => '$' . number_format($booking->deposit_amount, 2)
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
                    throw new \Exception('Shopify Draft Order API Error: ' . json_encode($response['body']));
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
                            $sendInvoiceMutation = 'mutation draftOrderInvoiceSend($id: ID!, $email: EmailInput) {
                                draftOrderInvoiceSend(id: $id, email: $email) {
                                    draftOrder {
                                        id
                                        invoiceUrl
                                    }
                                }
                            }';

                            $invoiceRes = $shop->api()->graph($sendInvoiceMutation, [
                                'id' => $gqlId,
                                'email' => ['to' => $booking->email]
                            ]);

                            if ($invoiceRes['errors'] === false && isset($invoiceRes['body']['data']['draftOrderInvoiceSend']['draftOrder'])) {
                                $refetchedOrder = $invoiceRes['body']['data']['draftOrderInvoiceSend']['draftOrder'];
                                $checkoutUrl = $refetchedOrder['invoiceUrl'] ?? null;
                            }
                        } catch (\Exception $invoiceEx) {
                            \Illuminate\Support\Facades\Log::error('Failed to send invoice for recreated balance draft order', ['error' => $invoiceEx->getMessage()]);
                        }
                    }

                    $booking->update([
                        'draft_order_id' => $draftOrderId,
                        'checkout_url' => $checkoutUrl
                    ]);
                }
            }

            if ($booking->draft_order_id) {
                $sent = $this->sendShopifyDraftOrderInvoice($shop, $booking->draft_order_id, $booking->email);
                if ($sent) {
                    $booking->update(['invoice_sent' => true]);
                    return back()->with('success', 'Shopify remaining balance invoice sent successfully to ' . $booking->email);
                }
                return back()->with('error', 'Failed to send Shopify remaining balance invoice.');
            }

            return back()->with('error', 'Failed to retrieve invoice ID from Shopify.');

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
     * Convert GraphQL DraftOrder node to standard REST-like array structure.
     */
    private function normalizeGqlDraftOrder($node)
    {
        if (!$node) {
            return null;
        }

        $numericId = preg_replace('/[^0-9]/', '', $node['id'] ?? '');
        $orderId = null;
        if (!empty($node['order']['id'])) {
            $orderId = preg_replace('/[^0-9]/', '', $node['order']['id']);
        }

        $lineItems = [];
        $edges = $node['lineItems']['edges'] ?? [];
        foreach ($edges as $edge) {
            $li = $edge['node'] ?? [];
            $lineItems[] = [
                'title' => $li['title'] ?? '',
                'applied_discount' => isset($li['appliedDiscount']) ? [
                    'description' => $li['appliedDiscount']['description'] ?? ''
                ] : null
            ];
        }

        $noteAttributes = [];
        $attrs = $node['noteAttributes'] ?? [];
        foreach ($attrs as $attr) {
            $noteAttributes[] = [
                'name' => $attr['key'] ?? '',
                'value' => $attr['value'] ?? '',
            ];
        }

        return [
            'id' => $numericId,
            'status' => strtolower($node['status'] ?? ''),
            'order_id' => $orderId,
            'invoice_url' => $node['invoiceUrl'] ?? null,
            'line_items' => $lineItems,
            'note_attributes' => $noteAttributes,
        ];
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

    /**
     * Send Shopify Draft Order Invoice to the customer using draftOrderSendInvoice mutation.
     */
    private function sendShopifyDraftOrderInvoice($shop, $draftOrderId, $emailAddress): bool
    {
        try {
            $gqlId = 'gid://shopify/DraftOrder/' . $draftOrderId;
            $sendInvoiceMutation = 'mutation draftOrderInvoiceSend($id: ID!, $email: EmailInput) {
                draftOrderInvoiceSend(id: $id, email: $email) {
                    draftOrder {
                        id
                    }
                    userErrors {
                        field
                        message
                    }
                }
            }';

            $response = $shop->api()->graph($sendInvoiceMutation, [
                'id' => $gqlId,
                'email' => ['to' => $emailAddress]
            ]);

            if ($response['errors']) {
                \Illuminate\Support\Facades\Log::error("sendShopifyDraftOrderInvoice API errors: " . json_encode($response['body']));
                return false;
            }

            $userErrors = $response['body']['data']['draftOrderInvoiceSend']['userErrors'] ?? [];
            if (!empty($userErrors)) {
                \Illuminate\Support\Facades\Log::error("sendShopifyDraftOrderInvoice User errors: " . json_encode($userErrors));
                return false;
            }

            return true;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("sendShopifyDraftOrderInvoice exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Sync local bookings status and details with Shopify draft orders and orders.
     */
    private function syncBookingsWithShopify($shop)
    {
        if (app()->environment() === 'testing') {
            return;
        }

        try {
            // 1. Fetch latest draft orders matching our app tags/notes
            $gqlDraftOrdersQuery = 'query {
                draftOrders(first: 50, reverse: true, query: "tag:buylater-deposit OR tag:buylater-balance OR note:buylater_token OR note:BuyLater OR note:\'Remaining balance\'") {
                    edges {
                        node {
                            id
                            name
                            status
                            invoiceUrl
                            tags
                            order {
                                id
                                name
                            }
                            note
                            customAttributes {
                                key
                                value
                            }
                            lineItems(first: 10) {
                                edges {
                                    node {
                                        title
                                        quantity
                                        customAttributes {
                                            key
                                            value
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }';

            $draftRes = $shop->api()->graph($gqlDraftOrdersQuery);
            $draftOrders = [];
            if ($draftRes && !empty($draftRes['body']['data']['draftOrders']['edges'])) {
                $draftOrders = $draftRes['body']['data']['draftOrders']['edges'];
            }

            // 2. Fetch latest orders matching our app tags/notes
            $gqlOrdersQuery = 'query {
                orders(first: 50, reverse: true, query: "tag:buylater-deposit OR tag:buylater-balance OR note:buylater_token OR note:\'Remaining balance\'") {
                    edges {
                        node {
                            id
                            name
                            displayFinancialStatus
                            displayFulfillmentStatus
                            tags
                            note
                            customAttributes {
                                key
                                value
                            }
                            lineItems(first: 10) {
                                edges {
                                    node {
                                        title
                                        quantity
                                        customAttributes {
                                            key
                                            value
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }';

            $orderRes = $shop->api()->graph($gqlOrdersQuery);
            $orders = [];
            if ($orderRes && !empty($orderRes['body']['data']['orders']['edges'])) {
                $orders = $orderRes['body']['data']['orders']['edges'];
            }

            $settings = Setting::where('shop_id', $shop->id)->first();
            $holdDurationDays = $settings ? (int) ($settings->hold_duration_days ?? 14) : 14;

            // A. Process Orders (Paid orders are definitive proof of payment)
            foreach ($orders as $edge) {
                $node = $edge['node'] ?? null;
                if (!$node) continue;

                $numericOrderId = preg_replace('/[^0-9]/', '', $node['id'] ?? '');
                $financialStatus = strtoupper($node['displayFinancialStatus'] ?? '');
                $isRemaining = $this->isRemainingBalanceNode($node);
                $orderName = $node['name'] ?? null;
                $fulfillmentStatus = strtolower($node['displayFulfillmentStatus'] ?? '');

                // 1. Try to match by order_id or balance_order_id first
                $booking = null;
                if ($numericOrderId) {
                    if ($isRemaining) {
                        $booking = Booking::where('shop_id', $shop->id)->where('balance_order_id', $numericOrderId)->first();
                    } else {
                        $booking = Booking::where('shop_id', $shop->id)->where('order_id', $numericOrderId)->first();
                    }
                }

                // 2. Fallback to token matching
                if (!$booking) {
                    $token = $this->extractTokenFromNode($node);
                    if ($token) {
                        $booking = Booking::where('shop_id', $shop->id)->where('token', $token)->first();
                    }
                }

                if ($booking) {
                    if ($isRemaining) {
                        $updateData = [
                            'balance_order_id' => $numericOrderId,
                            'balance_order_name' => $orderName,
                            'payment_status' => strtolower($financialStatus),
                            'fulfillment_status' => $fulfillmentStatus,
                        ];
                        if ($booking->status !== 'completed' && ($financialStatus === 'PAID' || $financialStatus === 'PARTIALLY_PAID')) {
                            $updateData['status'] = 'completed';
                            $updateData['completed_at'] = now();
                            \Illuminate\Support\Facades\Log::info("Sync: Booking ID {$booking->id} marked completed via final Order payment.");
                        }
                        $booking->update($updateData);
                    } else {
                        $updateData = [
                            'order_id' => $numericOrderId,
                            'order_name' => $orderName,
                            'payment_status' => strtolower($financialStatus),
                            'fulfillment_status' => $fulfillmentStatus,
                        ];
                        if ($booking->status === 'pending' && ($financialStatus === 'PAID' || $financialStatus === 'PARTIALLY_PAID')) {
                            $updateData['status'] = 'deposit_paid';
                            $updateData['expires_at'] = now()->addDays($holdDurationDays);
                            $updateData['deposit_paid_at'] = now();
                            $updateData['draft_order_id'] = null;
                            $updateData['checkout_url'] = null;
                            \Illuminate\Support\Facades\Log::info("Sync: Booking ID {$booking->id} marked deposit_paid via deposit Order payment.");
                        }
                        $booking->update($updateData);
                    }
                }
            }

            // B. Process Draft Orders
            foreach ($draftOrders as $edge) {
                $node = $edge['node'] ?? null;
                if (!$node) continue;

                $draftStatus = strtoupper($node['status'] ?? '');
                $numericDraftId = preg_replace('/[^0-9]/', '', $node['id'] ?? '');
                $isRemaining = $this->isRemainingBalanceNode($node);
                $checkoutUrl = $node['invoiceUrl'] ?? null;

                // 1. Try to match by draft_order_id first (highly reliable for draft order flow)
                $booking = null;
                if ($numericDraftId) {
                    $booking = Booking::where('shop_id', $shop->id)->where('draft_order_id', $numericDraftId)->first();
                }

                // 2. Fallback to token extraction
                if (!$booking) {
                    $token = $this->extractTokenFromNode($node);
                    if ($token) {
                        $booking = Booking::where('shop_id', $shop->id)->where('token', $token)->first();
                    }
                }

                if ($booking) {
                    if ($draftStatus === 'COMPLETED') {
                        $orderId = !empty($node['order']['id']) ? preg_replace('/[^0-9]/', '', $node['order']['id']) : null;
                        $orderName = !empty($node['order']['name']) ? $node['order']['name'] : null;
                        if ($isRemaining) {
                            if ($booking->status !== 'completed') {
                                $booking->update([
                                    'status' => 'completed',
                                    'completed_at' => now(),
                                    'balance_order_id' => $orderId,
                                    'balance_order_name' => $orderName,
                                ]);
                                \Illuminate\Support\Facades\Log::info("Sync: Booking ID {$booking->id} marked completed via Draft Order completion.");
                            }
                        } else {
                            if ($booking->status === 'pending') {
                                $booking->update([
                                    'status' => 'deposit_paid',
                                    'order_id' => $orderId,
                                    'order_name' => $orderName,
                                    'expires_at' => now()->addDays($holdDurationDays),
                                    'deposit_paid_at' => now(),
                                    'draft_order_id' => null,
                                    'checkout_url' => null,
                                ]);
                                \Illuminate\Support\Facades\Log::info("Sync: Booking ID {$booking->id} marked deposit_paid via Draft Order completion.");
                            }
                        }
                    } else if ($draftStatus === 'OPEN') {
                        if ($isRemaining) {
                            if ($booking->status === 'deposit_paid') {
                                $booking->update([
                                    'draft_order_id' => $numericDraftId,
                                    'checkout_url' => $checkoutUrl,
                                ]);
                            }
                        } else {
                            if ($booking->status === 'pending') {
                                $booking->update([
                                    'draft_order_id' => $numericDraftId,
                                    'checkout_url' => $checkoutUrl,
                                ]);
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to sync bookings with Shopify: " . $e->getMessage());
        }
    }

    /**
     * Extract token from customAttributes or lineItem customAttributes
     */
    private function extractTokenFromNode($node): ?string
    {
        $attrs = $node['customAttributes'] ?? [];
        foreach ($attrs as $attr) {
            $key = strtolower($attr['key'] ?? '');
            if ($key === 'buylater_token' || $key === '_token') {
                return strtolower($attr['value'] ?? '');
            }
        }

        $lineItems = $node['lineItems']['edges'] ?? [];
        foreach ($lineItems as $edge) {
            $li = $edge['node'] ?? [];
            $liAttrs = $li['customAttributes'] ?? [];
            foreach ($liAttrs as $attr) {
                $key = strtolower($attr['key'] ?? '');
                if ($key === 'buylater_token' || $key === '_token') {
                    return strtolower($attr['value'] ?? '');
                }
            }
        }

        $note = $node['note'] ?? '';
        if (preg_match('/buylater_token\s*:\s*([a-zA-Z0-9]+)/i', $note, $matches)) {
            return strtolower($matches[1]);
        }

        return null;
    }

    /**
     * Check if the node is a remaining balance node
     */
    private function isRemainingBalanceNode($node): bool
    {
        // Check tags
        $tags = $node['tags'] ?? [];
        if (is_string($tags)) {
            $tags = array_map('trim', explode(',', $tags));
        }
        if (in_array('buylater-balance', $tags)) {
            return true;
        }

        $note = $node['note'] ?? '';
        if (stripos($note, 'Remaining balance payment') !== false) {
            return true;
        }

        $lineItems = $node['lineItems']['edges'] ?? [];
        foreach ($lineItems as $edge) {
            $li = $edge['node'] ?? [];
            $title = $li['title'] ?? '';
            if (stripos($title, 'Remaining Balance') !== false) {
                return true;
            }
        }

        return false;
    }
}
