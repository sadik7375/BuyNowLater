<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\Reminder;
use App\Models\Subscriber;
use App\Models\Booking;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Inertia\Inertia;


class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $shop = auth()->user();

        if (!$shop) {
            $shopDomain = $request->get('shop');
            if ($shopDomain) {
                return redirect()->route('authenticate', ['shop' => $shopDomain, 'host' => $request->get('host')]);
            }
        }

        // Validate access token on dashboard load to guarantee fresh token before any billing action
        if ($shop) {
            if (empty($shop->password)) {
                $shopHandle = explode('.', $shop->name)[0];
                $host = $request->get('host') ?: base64_encode("admin.shopify.com/store/" . $shopHandle);
                return redirect()->route('authenticate', ['shop' => $shop->name, 'host' => $host]);
            }

            try {
                $shop->apiHelper()->make();
            } catch (\Throwable $t) {
                \Illuminate\Support\Facades\Log::warning('DashboardController: Invalid token on page load. Wiping password to force re-auth for: ' . $shop->name);
                $shop->shopify_offline_refresh_token = null;
                $shop->shopify_offline_access_token_expires_at = null;
                $shop->shopify_offline_refresh_token_expires_at = null;
                $shop->password = '';
                $shop->save();

                $shopHandle = explode('.', $shop->name)[0];
                $host = $request->get('host') ?: base64_encode("admin.shopify.com/store/" . $shopHandle);
                return redirect()->route('authenticate', ['shop' => $shop->name, 'host' => $host]);
            }
        }

        // Synchronize plan_id with active charge status in local database
        if ($shop) {
            $activeCharge = \Illuminate\Support\Facades\DB::table('charges')
                ->where('user_id', $shop->id)
                ->where('status', 'ACTIVE')
                ->whereNull('deleted_at')
                ->first();

            if (!$activeCharge) {
                if ($shop->plan_id !== null || $shop->shopify_freemium != 0) {
                    \Illuminate\Support\Facades\Log::info("DashboardController: No ACTIVE charge found for {$shop->name}. Resetting plan_id to free plan across all DB rows.");
                    \App\Models\User::withTrashed()
                        ->where('name', $shop->name)
                        ->update(['plan_id' => null, 'shopify_freemium' => 0]);
                    $shop->plan_id = null;
                    $shop->shopify_freemium = 0;
                }
            } else {
                if ($shop->plan_id === null) {
                    $shop->plan_id = $activeCharge->plan_id ?: 1;
                    $shop->save();
                }
            }

            if ($shop->plan_id) {
                $this->verifySubscriptionWithShopify($shop);
            }
        }

        if ($request->query('clear_all_bookings') === 'yes') {
            Booking::where('shop_id', $shop->id)->delete();
            return redirect($request->url());
        }

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
            $activeTab = 'tab-pricing';
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

        // Auto-initialize Selling Plan Group if missing so native checkout is enabled by default out-of-the-box
        if (empty($settings->selling_plan_group_id)) {
            try {
                $sellingPlanService = app(\App\Services\SellingPlanService::class);
                $res = $sellingPlanService->createOrUpdatePlanGroup($shop, (int)($settings->deposit_percentage ?? 10), (int)($settings->hold_duration_days ?? 14));
                if ($res && !empty($res['group_id'])) {
                    $gqlQuery = 'query getProducts($first: Int!) { products(first: $first) { edges { node { id } } } }';
                    $response = $shop->api()->graph($gqlQuery, ['first' => 250]);
                    $productGqlIds = [];
                    if (isset($response['body']['data']['products']['edges'])) {
                        foreach ($response['body']['data']['products']['edges'] as $edge) {
                            if (isset($edge['node']['id'])) {
                                $productGqlIds[] = $edge['node']['id'];
                            }
                        }
                    }
                    if (!empty($productGqlIds)) {
                        $sellingPlanService->attachProducts($shop, $res['group_id'], $productGqlIds);
                    }
                }
            } catch (\Exception $e) {
                Log::error("Auto SellingPlan setup failed: " . $e->getMessage());
            }
        }

        // Sync draft orders and orders from Shopify
        try {
            if (!$request->is('price-plan') && !$request->is('app-settings') && !$request->is('support') && !$request->is('how-it-works') && !$request->is('benefits')) {
                $this->syncBookingsWithShopify($shop);
            }
        } catch (\Throwable $t) {
            \Illuminate\Support\Facades\Log::warning("Sync bookings skipped: " . $t->getMessage());
        }
        $dateFilter = $request->query('date_filter', 'all');

        $allBookings = Booking::where('shop_id', $shop->id)
            ->orderBy('created_at', 'desc')
            ->get();

        // Display bookings that have a valid order_id, draft_order_id, or valid deposit_paid/completed/expired status
        $bookings = $allBookings->filter(function($b) {
            return !empty($b->order_id) || !empty($b->draft_order_id) || in_array($b->status, ['deposit_paid', 'completed', 'expired']);
        });

        if ($request->query('format') === 'json' || $request->query('json') == '1' || $request->query('debug') == '1') {
            return response()->json([
                'shop' => $shop->name,
                'filtered_count' => $bookings->count(),
                'total_count' => $allBookings->count(),
                'bookings' => $bookings->values(),
                'all_bookings' => $allBookings->values()
            ]);
        }

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
            'pending'      => $allBookings->where('status', 'pending')->count(),
            'deposit_paid' => $allBookings->filter(fn($b) => $b->status === 'deposit_paid' && strtolower($b->payment_status) !== 'refunded')->count(),
            'completed'    => $allBookings->filter(fn($b) => $b->status === 'completed' && strtolower($b->payment_status) !== 'refunded')->count(),
            'expired'      => $allBookings->filter(fn($b) => $b->status === 'expired' || strtolower($b->payment_status) === 'refunded')->count(),
        ];
        $isMockStatus = false; // Always dynamic

        // --- Today's Scheduled Reminders ---
        $todayRemindersCount = Reminder::where('shop_id', $shop->id)
            ->whereDate('scheduled_at', Carbon::today())
            ->count();

        // --- Overview Stats (100% Dynamic from Database) ---
        $revenueRecovered = Booking::where('shop_id', $shop->id)
            ->where('status', 'completed')
            ->where(function($q) {
                $q->whereNull('payment_status')
                  ->orWhereRaw('LOWER(payment_status) != ?', ['refunded']);
            })
            ->sum('product_price');

        $activeBookings = Booking::where('shop_id', $shop->id)
            ->where('status', 'deposit_paid')
            ->where(function($q) {
                $q->whereNull('payment_status')
                  ->orWhereRaw('LOWER(payment_status) != ?', ['refunded']);
            })
            ->count();

        $now = Carbon::now();
        $holdDays = (int) ($settings->hold_duration_days ?? 7);
        $expiringWindow = Carbon::now()->addDays($holdDays)->endOfDay();
        $expiringSoonCount = Booking::where('shop_id', $shop->id)
            ->where('status', 'deposit_paid')
            ->where(function($q) {
                $q->whereNull('payment_status')
                  ->orWhereRaw('LOWER(payment_status) != ?', ['refunded']);
            })
            ->whereNotNull('expires_at')
            ->where('expires_at', '>=', $now)
            ->where('expires_at', '<=', $expiringWindow)
            ->count();

        $alertSubscribersCount = Subscriber::where('shop_id', $shop->id)
            ->count();

        $conversionRate = 0.0;

        $reminders = Reminder::where('shop_id', $shop->id)
            ->orderBy('created_at', 'desc')
            ->get();
        $subscribers = Subscriber::where('shop_id', $shop->id)
            ->orderBy('created_at', 'desc')
            ->get();

        // --- Wished Products (100% Dynamic from Database) ---
        $wishes = [];
        foreach ($reminders as $r) {
            if (!empty($r->product_title)) {
                $wishes[$r->product_title] = ($wishes[$r->product_title] ?? 0) + 1;
            }
        }
        foreach ($subscribers as $s) {
            if (!empty($s->product_title)) {
                $wishes[$s->product_title] = ($wishes[$s->product_title] ?? 0) + 1;
            }
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

        $parseBody = function($res) {
            if (empty($res['body'])) return null;
            $raw = $res['body'];
            if (is_object($raw)) {
                if (method_exists($raw, 'getBody')) {
                    $raw = (string) $raw->getBody();
                } else {
                    $raw = json_encode($raw);
                }
            }
            if (is_string($raw)) {
                $raw = json_decode($raw, true);
            }
            if (is_array($raw)) {
                return $raw['data'] ?? ($raw['container']['data'] ?? $raw);
            }
            return null;
        };

        $targetedProducts = [];
        if (in_array($settings->product_targeting_type ?? 'all', ['specific', 'exclude']) && !empty($settings->targeted_product_ids)) {
            $ids = array_values(array_filter(array_map('trim', explode(',', $settings->targeted_product_ids))));
            
            // Step 1: Try reading directly from stored targeted_products_json for instant rendering
            if (!empty($settings->targeted_products_json)) {
                $decoded = json_decode($settings->targeted_products_json, true);
                if (is_array($decoded) && !empty($decoded)) {
                    $validDecoded = [];
                    foreach ($decoded as $p) {
                        if (!empty($p['id']) && in_array((string)$p['id'], $ids) && !empty($p['title']) && !str_starts_with($p['title'], 'Product #')) {
                            $validDecoded[] = $p;
                        }
                    }
                    if (count($validDecoded) === count($ids)) {
                        $targetedProducts = $validDecoded;
                    }
                }
            }

            // Step 2: If JSON storage was empty or contained generic titles ("Product #..."), fetch real product titles and images via Shopify GraphQL nodes query
            if (empty($targetedProducts) && !empty($ids)) {
                $productCacheKey = "shop_{$shop->id}_targeted_products_v5_" . md5($settings->targeted_product_ids);
                $targetedProducts = \Illuminate\Support\Facades\Cache::remember($productCacheKey, now()->addMinutes(10), function() use ($shop, $ids, $parseBody) {
                    $productsList = [];
                    try {
                        $numericIds = array_map(function($id) {
                            return preg_replace('/[^0-9]/', '', $id);
                        }, $ids);
                        $numericIds = array_values(array_filter($numericIds));

                        if (!empty($numericIds)) {
                            $gqlIds = array_map(fn($id) => "gid://shopify/Product/{$id}", $numericIds);
                            $nodesQuery = 'query getNodesByIds($ids: [ID!]!) {
                                nodes(ids: $ids) {
                                    ... on Product {
                                        id
                                        title
                                        handle
                                        featuredImage {
                                            url
                                            originalSrc
                                        }
                                    }
                                }
                            }';
                            $response = $shop->api()->graph($nodesQuery, ['ids' => array_values($gqlIds)]);
                            $bodyData = $parseBody($response);
                            $nodes = $bodyData['nodes'] ?? ($bodyData['data']['nodes'] ?? []);

                            if (is_array($nodes)) {
                                foreach ($nodes as $node) {
                                    if (!$node || empty($node['id'])) continue;
                                    $numericId = preg_replace('/[^0-9]/', '', $node['id']);
                                    $imgUrl = $node['featuredImage']['url'] ?? ($node['featuredImage']['originalSrc'] ?? null);
                                    $productsList[] = [
                                        'id' => (string) $numericId,
                                        'title' => $node['title'] ?? ('Product #' . $numericId),
                                        'handle' => $node['handle'] ?? '',
                                        'image' => $imgUrl,
                                    ];
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error("Failed to fetch targeted products in index(): " . $e->getMessage());
                    }

                    // Final Fallback: Ensure any product ID in targeted_product_ids is included
                    $fetchedIds = array_column($productsList, 'id');
                    foreach ($ids as $id) {
                        $numericId = (string) preg_replace('/[^0-9]/', '', $id);
                        if (!empty($numericId) && !in_array($numericId, $fetchedIds)) {
                            $productsList[] = [
                                'id' => $numericId,
                                'title' => 'Product #' . $numericId,
                                'handle' => '',
                                'image' => null,
                            ];
                        }
                    }

                    return $productsList;
                });

                // Auto-update targeted_products_json in DB if real titles were retrieved
                $hasRealTitles = count(array_filter($targetedProducts, fn($p) => !str_starts_with($p['title'] ?? '', 'Product #'))) > 0;
                if ($hasRealTitles && $settings) {
                    $settings->update([
                        'targeted_products_json' => json_encode($targetedProducts)
                    ]);
                }
            }
        }

        // Count bookings created per day for the last 7 days (Downpay orders)
        $downpayChartLabels = [];
        $downpayChartData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $downpayChartLabels[] = $date->format('M j');
            $downpayChartData[] = Booking::where('shop_id', $shop->id)
                ->whereIn('status', ['deposit_paid', 'completed', 'expired'])
                ->whereDate('created_at', $date)
                ->count();
        }

        return Inertia::render('Dashboard', [
            'settings' => $settings,
            'reminders' => $reminders,
            'subscribers' => $subscribers,
            'bookings' => $bookings->values(),
            'revenueRecovered' => $revenueRecovered,
            'activeBookings' => $activeBookings,
            'expiringSoonCount' => $expiringSoonCount,
            'alertSubscribersCount' => $alertSubscribersCount,
            'conversionRate' => $conversionRate,
            'wishes' => $wishes,
            'liveAlerts' => $liveAlerts,
            'expiringToday' => $expiringToday->values(),
            'expiringTomorrow' => $expiringTomorrow->values(),
            'expiringThisWeek' => $expiringThisWeek->values(),
            'statusCounts' => $statusCounts,
            'todayRemindersCount' => $todayRemindersCount,
            'dateFilter' => $dateFilter,
            'monthlyUsageCount' => $monthlyUsageCount,
            'targetedProducts' => $targetedProducts,
            'activeTab' => $activeTab,
            'subTab' => $subTab,
            'downpayChartLabels' => $downpayChartLabels,
            'downpayChartData' => $downpayChartData,
            'hasPlan' => (bool) ($shop->plan_id ?? false),
            'shopName' => $shop->name ?? '',
            'shopEmail' => $shop->email ?? '',
        ]);
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
        if (!$shop) {
            $shopDomain = $request->query('shop');
            if ($shopDomain) {
                $shop = \App\Models\User::where('name', $shopDomain)->first();
            }
        }

        if (!$shop) {
            return response()->json([], 401);
        }

        $rawQuery = trim($request->query('q', ''));
        // Clean query for Shopify GraphQL Lucene syntax (avoid invalid leading wildcards)
        $cleanQuery = preg_replace('/[^\w\s\-\.]/u', '', $rawQuery);

        $gqlQuery = '
            query searchProducts($queryStr: String) {
                products(first: 25, query: $queryStr) {
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

        // Shopify GraphQL requires trailing wildcard e.g. "title:the*", leading wildcards "*the*" are invalid
        $queryStr = !empty($cleanQuery) ? "title:{$cleanQuery}*" : null;

        try {
            $response = $shop->api()->graph($gqlQuery, ['queryStr' => $queryStr]);
            
            $parseBody = function($res) {
                if (empty($res['body'])) return null;
                $raw = $res['body'];
                if (is_object($raw)) {
                    if (method_exists($raw, 'getBody')) {
                        $raw = (string) $raw->getBody();
                    } else {
                        $raw = json_encode($raw);
                    }
                }
                if (is_string($raw)) {
                    $raw = json_decode($raw, true);
                }
                if (is_array($raw)) {
                    return $raw['data'] ?? ($raw['container']['data'] ?? $raw);
                }
                return null;
            };

            $bodyData = $parseBody($response);
            $edges = $bodyData['products']['edges'] ?? [];

            // If primary query had errors or returned empty, fallback to simple title search
            if (empty($edges)) {
                $fallbackQueryStr = !empty($cleanQuery) ? "{$cleanQuery}*" : null;
                $response = $shop->api()->graph($gqlQuery, ['queryStr' => $fallbackQueryStr]);
                $bodyData = $parseBody($response);
                $edges = $bodyData['products']['edges'] ?? [];
            }

            $products = [];
            if (!empty($edges) && is_array($edges)) {
                foreach ($edges as $edge) {
                    $node = $edge['node'] ?? [];
                    if (empty($node['id'])) continue;

                    $numericId = preg_replace('/[^0-9]/', '', $node['id']);
                    $imgUrl = $node['featuredImage']['url'] ?? ($node['featuredImage']['originalSrc'] ?? null);
                    $products[] = [
                        'id' => (string) $numericId,
                        'title' => $node['title'] ?? '',
                        'handle' => $node['handle'] ?? '',
                        'image' => $imgUrl,
                    ];
                }
            } else {
                \Illuminate\Support\Facades\Log::warning("searchProducts: Shopify returned GraphQL errors", [
                    'errors' => $response['errors'] ?? null,
                    'queryStr' => $queryStr
                ]);
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
            'sender_display_name'      => 'nullable|string|max:100',
            'deposit_percentage'       => 'required|integer|min:1|max:100',
            'button_text'              => 'nullable|string|max:50',
            'reminder_email_subject'   => 'nullable|string|max:255',
            'reminder_email_template'  => 'nullable|string',
            'discount_email_subject'   => 'nullable|string|max:255',
            'discount_email_template'  => 'nullable|string',
            'show_deposit'             => 'nullable|boolean',
            'show_reminders'           => 'nullable|boolean',
            'show_alerts'              => 'nullable|boolean',
            'hold_duration_days'       => 'required|integer|min:1|max:365',
            'terms_text'               => 'nullable|string',
            'product_targeting_type'   => 'nullable|string|in:all,specific,exclude',
            'targeted_product_ids'     => 'nullable|string',
            'targeted_products_json'   => 'nullable|string',
        ]);

        $existingSettings = Setting::where('shop_id', $shop->id)->first();

        $newDeposit = (int) $request->input('deposit_percentage');
        $newHoldDays = (int) $request->input('hold_duration_days');
        $needsSellingPlanSync = !$existingSettings 
            || empty($existingSettings->selling_plan_group_id)
            || ((int)($existingSettings->deposit_percentage ?? 0) !== $newDeposit)
            || ((int)($existingSettings->hold_duration_days ?? 0) !== $newHoldDays);

        Setting::updateOrCreate(
            ['shop_id' => $shop->id],
            [
                'sender_display_name'     => $request->input('sender_display_name') ?: ($existingSettings->sender_display_name ?? ($shop->name . ' via BuyLater')),
                'deposit_percentage'      => $newDeposit,
                'button_text'             => $request->input('button_text') ?: ($existingSettings->button_text ?? 'Buy Later'),
                'reminder_email_subject'  => $request->input('reminder_email_subject') ?: ($existingSettings->reminder_email_subject ?? 'Reminder: You wanted to buy this later!'),
                'reminder_email_template' => $request->input('reminder_email_template') ?: ($existingSettings->reminder_email_template ?? ''),
                'discount_email_subject'  => $request->input('discount_email_subject') ?: ($existingSettings->discount_email_subject ?? 'Price Drop Alert: A product you wanted is now on sale!'),
                'discount_email_template' => $request->input('discount_email_template') ?: ($existingSettings->discount_email_template ?? ''),
                'show_deposit'            => $request->has('show_deposit') ? $request->has('show_deposit') : true,
                'show_reminders'          => false,
                'show_alerts'             => false,
                'hold_duration_days'      => $newHoldDays,
                'terms_text'              => $request->input('terms_text'),
                'product_targeting_type'  => $request->input('product_targeting_type', 'all') ?: 'all',
                'targeted_product_ids'    => $request->input('targeted_product_ids'),
                'targeted_products_json'  => $request->input('targeted_products_json'),
                'use_selling_plan'        => true,
            ]
        );

        \Illuminate\Support\Facades\Cache::forget("shop_{$shop->id}_targeted_products");
        if ($existingSettings && !empty($existingSettings->targeted_product_ids)) {
            \Illuminate\Support\Facades\Cache::forget("shop_{$shop->id}_targeted_products_" . md5($existingSettings->targeted_product_ids));
            \Illuminate\Support\Facades\Cache::forget("shop_{$shop->id}_targeted_products_v4_" . md5($existingSettings->targeted_product_ids));
            \Illuminate\Support\Facades\Cache::forget("shop_{$shop->id}_targeted_products_v5_" . md5($existingSettings->targeted_product_ids));
        }
        if ($request->input('targeted_product_ids')) {
            \Illuminate\Support\Facades\Cache::forget("shop_{$shop->id}_targeted_products_" . md5($request->input('targeted_product_ids')));
            \Illuminate\Support\Facades\Cache::forget("shop_{$shop->id}_targeted_products_v4_" . md5($request->input('targeted_product_ids')));
            \Illuminate\Support\Facades\Cache::forget("shop_{$shop->id}_targeted_products_v5_" . md5($request->input('targeted_product_ids')));
        }

        $settings = Setting::where('shop_id', $shop->id)->first();

        // Sync Selling Plan Group on Shopify ONLY if deposit % or hold days changed or group is missing
        if ($needsSellingPlanSync) {
            try {
                $sellingPlanService = app(\App\Services\SellingPlanService::class);
                $result = $sellingPlanService->createOrUpdatePlanGroup(
                    $shop,
                    (int) $settings->deposit_percentage,
                    (int) $settings->hold_duration_days
                );
                if ($result && !empty($result['group_id'])) {
                    $productGqlIds = [];
                    $gqlQuery = 'query getProducts($first: Int!) {
                        products(first: $first) {
                            edges { node { id } }
                        }
                    }';
                    $response = $shop->api()->graph($gqlQuery, ['first' => 250]);
                    if ($response['errors'] === false && isset($response['body']['data']['products']['edges'])) {
                        foreach ($response['body']['data']['products']['edges'] as $edge) {
                            if (isset($edge['node']['id'])) {
                                $productGqlIds[] = $edge['node']['id'];
                            }
                        }
                    }
                    if (!empty($productGqlIds)) {
                        $sellingPlanService->attachProducts($shop, $result['group_id'], $productGqlIds);
                    }
                }
                \Illuminate\Support\Facades\Log::info("saveSettings: Synced Selling Plan Group on Shopify to deposit {$settings->deposit_percentage}%");
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("saveSettings: Failed to update Selling Plan Group on Shopify: " . $e->getMessage());
            }
        }

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
     * Self-Healing: Verify active subscription with Shopify GraphQL API.
     * If Shopify shows no active recurring subscription for this app, reset shop plan_id to null.
     */
    private function verifySubscriptionWithShopify($shop)
    {
        if (app()->environment() === 'testing' || !$shop || !$shop->plan_id || empty($shop->password)) {
            return;
        }

        try {
            $gql = 'query {
                currentAppInstallation {
                    activeSubscriptions {
                        id
                        name
                        status
                    }
                }
            }';

            $response = $shop->api()->graph($gql);
            $rawBody = $response['body'] ?? $response;
            if (is_object($rawBody) && method_exists($rawBody, 'getContainer')) {
                $rawBody = $rawBody->getContainer();
            } elseif (is_object($rawBody)) {
                $rawBody = json_decode(json_encode($rawBody), true);
            }
            $activeSubs = $rawBody['data']['currentAppInstallation']['activeSubscriptions'] ?? [];

            $hasActiveSub = false;
            foreach ($activeSubs as $sub) {
                if (isset($sub['status']) && strtoupper($sub['status']) === 'ACTIVE') {
                    $hasActiveSub = true;
                    break;
                }
            }

            if (!$hasActiveSub) {
                \Illuminate\Support\Facades\Log::info("verifySubscriptionWithShopify: No active Shopify subscription found for {$shop->name}. Resetting to Free Plan across all rows.");
                \App\Models\User::withTrashed()
                    ->where('name', $shop->name)
                    ->update(['plan_id' => null, 'shopify_freemium' => 0]);
                $shop->plan_id = null;
                $shop->shopify_freemium = 0;

                $allUserIds = \App\Models\User::withTrashed()->where('name', $shop->name)->pluck('id');
                \Illuminate\Support\Facades\DB::table('charges')->whereIn('user_id', $allUserIds)->update(['status' => 'CANCELLED', 'deleted_at' => now()]);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("verifySubscriptionWithShopify check error: " . $e->getMessage());
        }
    }

    /**
     * Sync local bookings status and details with Shopify draft orders and orders.
     */
    private function syncBookingsWithShopify($shop)
    {
        if (app()->environment() === 'testing' || !$shop || empty($shop->password)) {
            return;
        }

        try {
            // 1. Fetch latest draft orders matching our app tags/notes
            $gqlDraftOrdersQuery = 'query {
                draftOrders(first: 100, reverse: true) {
                    edges {
                        node {
                            id
                            name
                            status
                            invoiceUrl
                            tags
                            notes
                            totalPrice
                            currencyCode
                            createdAt
                            email
                            billingAddress {
                                name
                            }
                            customer {
                                firstName
                                lastName
                                email
                            }
                            order {
                                id
                                name
                            }
                            customAttributes {
                                key
                                value
                            }
                            lineItems(first: 10) {
                                edges {
                                    node {
                                        title
                                        quantity
                                        variant {
                                            id
                                            product {
                                                id
                                                handle
                                            }
                                        }
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
                $draftOrders = json_decode(json_encode($draftRes['body']['data']['draftOrders']['edges']), true) ?? [];
            }

            // 2. Fetch latest orders matching our app tags/notes
            $gqlOrdersQuery = 'query {
                orders(first: 100, reverse: true) {
                    edges {
                        node {
                            id
                            name
                            displayFinancialStatus
                            displayFulfillmentStatus
                            tags
                            note
                            totalPriceSet {
                                shopMoney {
                                    amount
                                }
                            }
                            netPaymentSet {
                                shopMoney {
                                    amount
                                }
                            }
                            currencyCode
                            createdAt
                            email
                            billingAddress {
                                name
                            }
                            customer {
                                firstName
                                lastName
                                email
                            }
                            customAttributes {
                                key
                                value
                            }
                            lineItems(first: 10) {
                                edges {
                                    node {
                                        title
                                        quantity
                                        sellingPlan {
                                             name
                                             sellingPlanId
                                         }
                                        variant {
                                            id
                                            product {
                                                id
                                                handle
                                            }
                                        }
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
                $orders = json_decode(json_encode($orderRes['body']['data']['orders']['edges']), true) ?? [];
            }

            $settings = Setting::where('shop_id', $shop->id)->first();
            $holdDurationDays = $settings ? (int) ($settings->hold_duration_days ?? 14) : 14;

            // A. Process Orders (Paid orders are definitive proof of payment)
            foreach ($orders as $edge) {
                $node = $edge['node'] ?? null;
                if (!$node) continue;

                $numericOrderId = preg_replace('/[^0-9]/', '', $node['id'] ?? '');
                $financialStatus = strtoupper(str_replace([' ', '-'], '_', $node['displayFinancialStatus'] ?? ''));
                $isRemaining = $this->isRemainingBalanceNode($node);
                $orderName = $node['name'] ?? null;
                $orderNameNumber = preg_replace('/[^0-9]/', '', $orderName ?? '');
                $fulfillmentStatus = strtolower($node['displayFulfillmentStatus'] ?? '');

                // 1. Try to match by order_id, order_name, or balance_order_id first
                $booking = null;
                if (!empty($numericOrderId) || !empty($orderName) || !empty($orderNameNumber)) {
                    if ($isRemaining) {
                        $booking = Booking::where('shop_id', $shop->id)
                            ->where(function($q) use ($numericOrderId, $orderName, $orderNameNumber) {
                                if ($numericOrderId) $q->orWhere('balance_order_id', $numericOrderId);
                                if ($orderName) $q->orWhere('balance_order_name', $orderName)->orWhere('balance_order_id', $orderName);
                                if ($orderNameNumber) $q->orWhere('balance_order_id', $orderNameNumber);
                            })->first();
                    } else {
                        $booking = Booking::where('shop_id', $shop->id)
                            ->where(function($q) use ($numericOrderId, $orderName, $orderNameNumber) {
                                if ($numericOrderId) $q->orWhere('order_id', $numericOrderId);
                                if ($orderName) $q->orWhere('order_name', $orderName)->orWhere('order_id', $orderName);
                                if ($orderNameNumber) $q->orWhere('order_id', $orderNameNumber);
                            })->first();
                    }
                }

                // 2. Fallback to token matching or uncompleted booking matching
                $token = null;
                if (!$booking) {
                    $token = $this->extractTokenFromNode($node);
                    if ($token) {
                        $booking = Booking::where('shop_id', $shop->id)->where('token', $token)->first();
                    }
                }

                $customer = $node['customer'] ?? null;
                $customerName = $customer ? trim(($customer['firstName'] ?? '') . ' ' . ($customer['lastName'] ?? '')) : '';
                if (empty($customerName) && !empty($node['billingAddress']['name'])) {
                    $customerName = $node['billingAddress']['name'];
                }
                if (empty($customerName) && !empty($node['shippingAddress']['name'])) {
                    $customerName = $node['shippingAddress']['name'];
                }
                $email = $customer && !empty($customer['email']) ? $customer['email'] : ($node['email'] ?? 'N/A');
                if (empty($customerName) && !empty($email) && $email !== 'N/A') {
                    $customerName = explode('@', $email)[0];
                }
                if (empty($customerName)) {
                    $customerName = 'Customer ' . ($orderName ?: '#' . ($orderNameNumber ?: $numericOrderId));
                }

                // 2.5 Fallback: link to an uncompleted booking (where order_id is null) for the same customer or pending status
                if (!$booking && !empty($email) && $email !== 'N/A') {
                    $booking = Booking::where('shop_id', $shop->id)
                        ->whereNull('order_id')
                        ->where('email', $email)
                        ->orderBy('created_at', 'desc')
                        ->first();
                }
                if (!$booking) {
                    $booking = Booking::where('shop_id', $shop->id)
                        ->whereNull('order_id')
                        ->where('status', 'pending')
                        ->orderBy('created_at', 'desc')
                        ->first();
                }

                // 3. Auto-create booking if it doesn't exist yet
                if (!$booking) {
                    if (!$token) {
                        $token = $this->extractTokenFromNode($node);
                    }

                    $isWidgetOrSellingPlanOrder = false;
                    if ($token) {
                        $isWidgetOrSellingPlanOrder = true;
                    } else {
                        // Check line items for selling plans or custom attributes
                        $lineItemsNode = $node['lineItems']['edges'] ?? [];
                        foreach ($lineItemsNode as $itemEdge) {
                            $itemNode = $itemEdge['node'] ?? [];
                            if (!empty($itemNode['sellingPlanAllocation']) || !empty($itemNode['sellingPlan']) || !empty($itemNode['customAttributes'])) {
                                $isWidgetOrSellingPlanOrder = true;
                                break;
                            }
                        }
                        // Check tags
                        $tagsStr = is_array($node['tags'] ?? []) ? implode(',', $node['tags']) : ($node['tags'] ?? '');
                        if (stripos($tagsStr, 'buylater') !== false || stripos($tagsStr, 'deposit') !== false) {
                            $isWidgetOrSellingPlanOrder = true;
                        }
                        // Check financial status for partial payments
                        if (!$isWidgetOrSellingPlanOrder && in_array($financialStatus, ['PARTIALLY_PAID', 'PENDING', 'AUTHORIZED', 'PAID'])) {
                            $isWidgetOrSellingPlanOrder = true;
                        }

                        if ($isWidgetOrSellingPlanOrder) {
                            $token = 'bnl_' . ($orderNameNumber ?: $numericOrderId);
                        }
                    }

                    if ($isWidgetOrSellingPlanOrder && $token) {
                        $lineItemsNode = $node['lineItems']['edges'] ?? [];
                        $productTitle = !empty($lineItemsNode[0]['node']['title']) ? $lineItemsNode[0]['node']['title'] : 'N/A';

                        // Extract product and variant IDs
                        $firstLineItemNode = $lineItemsNode[0]['node'] ?? null;
                        $variantNode = $firstLineItemNode['variant'] ?? null;
                        $variantId = null;
                        if ($variantNode && isset($variantNode['id'])) {
                            $variantId = preg_replace('/[^0-9]/', '', $variantNode['id']);
                        }

                        $productNode = $variantNode['product'] ?? null;
                        $productId = null;
                        if ($productNode && isset($productNode['id'])) {
                            $productId = preg_replace('/[^0-9]/', '', $productNode['id']);
                        }
                        $productHandle = $productNode['handle'] ?? 'N/A';

                        // Extract prices
                        $originalPrice = 0.0;
                        $depositAmount = 0.0;
                        $remainingBalance = 0.0;

                        $totalPrice = isset($node['totalPriceSet']['shopMoney']['amount']) ? (float) $node['totalPriceSet']['shopMoney']['amount'] : 0.0;
                        $netPayment = isset($node['netPaymentSet']['shopMoney']['amount']) ? (float) $node['netPaymentSet']['shopMoney']['amount'] : 0.0;
                        $calcOutstanding = round($totalPrice - $netPayment, 2);

                        // If order is partially paid or outstanding balance exists
                        if ($calcOutstanding > 0.0 && ($financialStatus === 'PARTIALLY_PAID' || $financialStatus === 'PENDING' || $financialStatus === 'PARTIALLY_REFUNDED')) {
                            $originalPrice = $totalPrice;
                            $depositAmount = $netPayment > 0.0 ? $netPayment : round($totalPrice * 0.1, 2);
                            $remainingBalance = $calcOutstanding;
                        } else {
                            // Fallback to Draft Order flow customAttributes check
                            $attrs = array_merge($node['customAttributes'] ?? [], !empty($lineItemsNode[0]['node']['customAttributes']) ? $lineItemsNode[0]['node']['customAttributes'] : []);
                            foreach ($attrs as $attr) {
                                $key = strtolower($attr['key'] ?? '');
                                $val = $attr['value'] ?? '';
                                if (stripos($key, 'original price') !== false || stripos($key, 'product price') !== false) {
                                    $originalPrice = (float) preg_replace('/[^0-9.]/', '', $val);
                                }
                                if (stripos($key, 'deposit') !== false) {
                                    $depositAmount = (float) preg_replace('/[^0-9.]/', '', $val);
                                }
                                if (stripos($key, 'balance') !== false || stripos($key, 'remaining') !== false) {
                                    $remainingBalance = (float) preg_replace('/[^0-9.]/', '', $val);
                                }
                            }

                            if ($depositAmount === 0.0) {
                                if ($calcOutstanding === 0.0 || $financialStatus === 'PAID') {
                                    $depositAmount = $totalPrice;
                                    $originalPrice = $totalPrice;
                                    $remainingBalance = 0.0;
                                } else {
                                    $depositAmount = $totalPrice;
                                }
                            }
                            if ($originalPrice === 0.0) {
                                $pct = (float) ($settings->deposit_percentage ?? 10) / 100;
                                if ($pct > 0) {
                                    $originalPrice = $depositAmount / $pct;
                                } else {
                                    $originalPrice = $depositAmount;
                                }
                            }
                            if ($remainingBalance === 0.0) {
                                $remainingBalance = max(0, $originalPrice - $depositAmount);
                            }
                        }

                        $initialStatus = 'deposit_paid';
                        if ($financialStatus === 'PAID' && $remainingBalance == 0) {
                            $initialStatus = 'completed';
                        }

                        $booking = Booking::create([
                            'shop_id' => $shop->id,
                            'token' => $token,
                            'customer_name' => $customerName,
                            'email' => $email,
                            'product_id' => $productId ?? 'N/A',
                            'product_handle' => $productHandle ?? 'N/A',
                            'variant_id' => $variantId,
                            'product_title' => $productTitle,
                            'product_price' => $originalPrice,
                            'deposit_amount' => $depositAmount,
                            'remaining_balance' => $remainingBalance,
                            'status' => $initialStatus,
                            'deposit_paid_at' => now(),
                            'expires_at' => now()->addDays($holdDurationDays),
                            'order_id' => $numericOrderId,
                            'order_name' => $orderName,
                            'payment_status' => strtolower($financialStatus),
                            'fulfillment_status' => $fulfillmentStatus,
                        ]);
                        \Illuminate\Support\Facades\Log::info("Sync: Auto-created Booking ID {$booking->id} from Order {$node['name']} (status: {$initialStatus})");
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
                        if ($financialStatus === 'PAID' || $financialStatus === 'COMPLETED') {
                            $updateData['status'] = 'completed';
                            $updateData['completed_at'] = $booking->completed_at ?? now();
                            $updateData['remaining_balance'] = 0.0;
                            $updateData['payment_status'] = 'paid';
                            \Illuminate\Support\Facades\Log::info("Sync: Booking ID {$booking->id} marked completed via final Order payment.");
                        }
                        $booking->update($updateData);
                    } else {
                        $savedOrderId = !empty($numericOrderId) ? $numericOrderId : $orderNameNumber;
                        $savedOrderName = !empty($orderName) ? $orderName : ('#' . $savedOrderId);

                        $updateData = [
                            'order_id' => $savedOrderId,
                            'order_name' => $savedOrderName,
                            'payment_status' => strtolower($financialStatus),
                            'fulfillment_status' => $fulfillmentStatus,
                        ];

                        if (empty($booking->customer_name) || $booking->customer_name === 'N/A') {
                            $updateData['customer_name'] = $customerName;
                        }
                        if (empty($booking->email) || $booking->email === 'N/A') {
                            $updateData['email'] = $email;
                        }

                        $totalPrice = isset($node['totalPriceSet']['shopMoney']['amount']) ? (float) $node['totalPriceSet']['shopMoney']['amount'] : 0.0;
                        $netPayment = isset($node['netPaymentSet']['shopMoney']['amount']) ? (float) $node['netPaymentSet']['shopMoney']['amount'] : 0.0;
                        $calcOutstanding = round($totalPrice - $netPayment, 2);

                        if ($calcOutstanding > 0.0 && ($financialStatus === 'PARTIALLY_PAID' || $financialStatus === 'PENDING' || $financialStatus === 'PARTIALLY_REFUNDED')) {
                            $updateData['product_price'] = $totalPrice;
                            $updateData['deposit_amount'] = $netPayment;
                            $updateData['remaining_balance'] = $calcOutstanding;
                        }

                        if ($financialStatus === 'PAID' || $financialStatus === 'COMPLETED' || ($calcOutstanding === 0.0 && $financialStatus !== 'PENDING' && $financialStatus !== 'PARTIALLY_PAID')) {
                            $updateData['status'] = 'completed';
                            $updateData['completed_at'] = $booking->completed_at ?? now();
                            $updateData['remaining_balance'] = 0.0;
                            $updateData['payment_status'] = 'paid';
                            \Illuminate\Support\Facades\Log::info("Sync: Booking ID {$booking->id} marked completed via full Order payment.");
                        } else {
                            if ($booking->status === 'pending' || empty($booking->status)) {
                                $updateData['status'] = 'deposit_paid';
                                $updateData['expires_at'] = now()->addDays($holdDurationDays);
                                $updateData['deposit_paid_at'] = now();
                                $updateData['draft_order_id'] = null;
                                $updateData['checkout_url'] = null;
                                \Illuminate\Support\Facades\Log::info("Sync: Booking ID {$booking->id} marked deposit_paid via deposit Order payment.");
                            }
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

                // 2. Fallback to token matching
                $token = null;
                if (!$booking) {
                    $token = $this->extractTokenFromNode($node);
                    if ($token) {
                        $booking = Booking::where('shop_id', $shop->id)->where('token', $token)->first();
                    }
                }

                $customer = $node['customer'] ?? null;
                $customerName = $customer ? trim(($customer['firstName'] ?? '') . ' ' . ($customer['lastName'] ?? '')) : '';
                if (empty($customerName) && !empty($node['billingAddress']['name'])) {
                    $customerName = $node['billingAddress']['name'];
                }
                if (empty($customerName)) {
                    $customerName = 'N/A';
                }

                $email = $customer && !empty($customer['email']) ? $customer['email'] : ($node['email'] ?? 'N/A');

                // 3. Auto-create booking if it doesn't exist but has a token
                if (!$booking) {
                    if (!$token) {
                        $token = $this->extractTokenFromNode($node);
                    }
                    if ($token) {

                        $lineItemsNode = $node['lineItems']['edges'] ?? [];
                        $productTitle = !empty($lineItemsNode[0]['node']['title']) ? $lineItemsNode[0]['node']['title'] : 'N/A';

                        // Extract product and variant IDs
                        $firstLineItemNode = $lineItemsNode[0]['node'] ?? null;
                        $variantNode = $firstLineItemNode['variant'] ?? null;
                        $variantId = null;
                        if ($variantNode && isset($variantNode['id'])) {
                            $variantId = preg_replace('/[^0-9]/', '', $variantNode['id']);
                        }

                        $productNode = $variantNode['product'] ?? null;
                        $productId = null;
                        if ($productNode && isset($productNode['id'])) {
                            $productId = preg_replace('/[^0-9]/', '', $productNode['id']);
                        }
                        $productHandle = $productNode['handle'] ?? 'N/A';

                        // Extract prices
                        $originalPrice = 0.0;
                        $depositAmount = 0.0;
                        $remainingBalance = 0.0;

                        $attrs = array_merge($node['customAttributes'] ?? [], !empty($lineItemsNode[0]['node']['customAttributes']) ? $lineItemsNode[0]['node']['customAttributes'] : []);
                        foreach ($attrs as $attr) {
                            $key = strtolower($attr['key'] ?? '');
                            $val = $attr['value'] ?? '';
                            if (stripos($key, 'original price') !== false || stripos($key, 'product price') !== false) {
                                $originalPrice = (float) preg_replace('/[^0-9.]/', '', $val);
                            }
                            if (stripos($key, 'deposit') !== false) {
                                $depositAmount = (float) preg_replace('/[^0-9.]/', '', $val);
                            }
                            if (stripos($key, 'balance') !== false || stripos($key, 'remaining') !== false) {
                                $remainingBalance = (float) preg_replace('/[^0-9.]/', '', $val);
                            }
                        }

                        if ($depositAmount === 0.0 && isset($node['totalPrice'])) {
                            $depositAmount = (float) $node['totalPrice'];
                        }
                        if ($originalPrice === 0.0) {
                            $pct = (float) ($settings->deposit_percentage ?? 10) / 100;
                            if ($pct > 0) {
                                $originalPrice = $depositAmount / $pct;
                            } else {
                                $originalPrice = $depositAmount;
                            }
                        }
                        if ($remainingBalance === 0.0) {
                            $remainingBalance = $originalPrice - $depositAmount;
                        }

                        $booking = Booking::create([
                            'shop_id' => $shop->id,
                            'token' => $token,
                            'customer_name' => $customerName,
                            'email' => $email,
                            'product_id' => $productId ?? 'N/A',
                            'product_handle' => $productHandle ?? 'N/A',
                            'variant_id' => $variantId,
                            'product_title' => $productTitle,
                            'product_price' => $originalPrice,
                            'deposit_amount' => $depositAmount,
                            'remaining_balance' => $remainingBalance,
                            'status' => 'pending',
                        ]);
                        \Illuminate\Support\Facades\Log::info("Sync: Auto-created Booking ID {$booking->id} from Draft Order {$node['name']}");
                    }
                }

                if ($booking) {
                    $updateData = [];
                    if (empty($booking->customer_name) || $booking->customer_name === 'N/A') {
                        $updateData['customer_name'] = $customerName;
                    }
                    if (empty($booking->email) || $booking->email === 'N/A') {
                        $updateData['email'] = $email;
                    }
                    if (!empty($updateData)) {
                        $booking->update($updateData);
                    }

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
        // 1. Check for specific unique keys first (buylater_token, _buylater_token)
        $attrs = $node['customAttributes'] ?? [];
        foreach ($attrs as $attr) {
            $key = strtolower($attr['key'] ?? '');
            if ($key === 'buylater_token' || $key === '_buylater_token') {
                return strtolower($attr['value'] ?? '');
            }
        }

        $lineItems = $node['lineItems']['edges'] ?? [];
        foreach ($lineItems as $edge) {
            $li = $edge['node'] ?? [];
            $liAttrs = $li['customAttributes'] ?? [];
            foreach ($liAttrs as $attr) {
                $key = strtolower($attr['key'] ?? '');
                if ($key === 'buylater_token' || $key === '_buylater_token') {
                    return strtolower($attr['value'] ?? '');
                }
            }
        }

        // 2. Only check for generic "_token" if the order has BuyNowLater tags or notes
        $tags = $node['tags'] ?? [];
        if ($tags instanceof \Gnikyt\BasicShopifyAPI\ResponseAccess) {
            $tags = json_decode(json_encode($tags), true) ?? [];
        }
        if (is_string($tags)) {
            $tags = array_map('trim', explode(',', $tags));
        }
        $hasAppTag = false;
        if (is_array($tags)) {
            foreach ($tags as $tag) {
                if (stripos($tag, 'buylater') !== false) {
                    $hasAppTag = true;
                    break;
                }
            }
        }

        $note = $node['note'] ?? $node['notes'] ?? '';
        $hasAppNote = (stripos($note, 'buylater') !== false);

        if ($hasAppTag || $hasAppNote) {
            foreach ($attrs as $attr) {
                $key = strtolower($attr['key'] ?? '');
                if ($key === '_token') {
                    return strtolower($attr['value'] ?? '');
                }
            }
            foreach ($lineItems as $edge) {
                $li = $edge['node'] ?? [];
                $liAttrs = $li['customAttributes'] ?? [];
                foreach ($liAttrs as $attr) {
                    $key = strtolower($attr['key'] ?? '');
                    if ($key === '_token') {
                        return strtolower($attr['value'] ?? '');
                    }
                }
            }
        }

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
        if ($tags instanceof \Gnikyt\BasicShopifyAPI\ResponseAccess) {
            $tags = json_decode(json_encode($tags), true) ?? [];
        }
        if (is_string($tags)) {
            $tags = array_map('trim', explode(',', $tags));
        }
        if (is_array($tags) && in_array('buylater-balance', $tags)) {
            return true;
        }

        $note = $node['note'] ?? $node['notes'] ?? '';
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
