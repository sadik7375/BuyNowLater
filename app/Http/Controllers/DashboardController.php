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
            ->when($dateFilter !== 'all' && $start && $end, fn($q) => $q->whereBetween('created_at', [$start, $end]))
            ->sum('product_price');

        $activeBookings = Booking::where('shop_id', $shop->id)
            ->whereIn('status', ['pending', 'deposit_paid'])
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

        return view('dashboard.index', compact(
            'settings', 'reminders', 'subscribers', 'bookings',
            'revenueRecovered', 'activeBookings', 'alertSubscribersCount',
            'conversionRate', 'wishes', 'liveAlerts',
            'expiringToday', 'expiringTomorrow', 'expiringThisWeek', 'isMockExpiring',
            'statusCounts', 'isMockStatus', 'todayRemindersCount',
            'dateFilter', 'start', 'end'
        ));
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

        if ($booking->status === 'completed') {
            return back()->with('error', 'This booking is already completed.');
        }

        if ($booking->status === 'expired') {
            return back()->with('error', 'This booking has expired.');
        }

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
                            $status = is_object($draftOrder) ? ($draftOrder->status ?? '') : ($draftOrder['status'] ?? '');
                            if ($status !== 'completed') {
                                $needsNewDraftOrder = false;
                                $checkoutUrl = is_object($draftOrder) ? ($draftOrder->invoice_url ?? '') : ($draftOrder['invoice_url'] ?? '');
                            }
                        }
                    }
                }

                if ($needsNewDraftOrder) {
                    // Create Draft Order using Shopify REST API via Osiset/Laravel-Shopify
                    $draftOrderData = [
                        'draft_order' => [
                            'line_items' => [
                                [
                                    'title' => 'Remaining Balance - ' . $booking->product_title,
                                    'price' => number_format($booking->remaining_balance, 2, '.', ''),
                                    'quantity' => 1,
                                    'requires_shipping' => true,
                                ]
                            ],
                            'customer' => [
                                'email' => $booking->email,
                                'first_name' => $booking->customer_name ?? 'Valued',
                                'last_name' => 'Customer'
                            ],
                            'use_customer_default_address' => true,
                            'note_attributes' => [
                                [
                                    'name' => 'buylater_token',
                                    'value' => $booking->token
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
                        $draftOrderId = is_object($draftOrder) ? $draftOrder->id : $draftOrder['id'];
                        $checkoutUrl = is_object($draftOrder) ? $draftOrder->invoice_url : $draftOrder['invoice_url'];

                        $booking->update([
                            'draft_order_id' => $draftOrderId,
                            'checkout_url' => $checkoutUrl
                        ]);
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

                    $draftOrderData = [
                        'draft_order' => [
                            'email' => $booking->email,
                            'customer' => [
                                'email' => $booking->email,
                            ],
                            'line_items' => [[
                                'title'             => 'Deposit — ' . $booking->product_title,
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

        try {
            $needsNewDraftOrder = true;
            $checkoutUrl = null;

            if ($booking->draft_order_id) {
                // Fetch from Shopify to see if it is completed (deposit) or open (remaining balance)
                $response = $shop->api()->rest('GET', '/admin/api/' . config('shopify-app.api_version') . '/draft_orders/' . $booking->draft_order_id . '.json');
                
                if (!$response['errors']) {
                    $draftOrder = $response['body']['draft_order'] ?? null;
                    if ($draftOrder) {
                        $status = is_object($draftOrder) ? ($draftOrder->status ?? '') : ($draftOrder['status'] ?? '');
                        if ($status !== 'completed') {
                            $needsNewDraftOrder = false;
                            $checkoutUrl = is_object($draftOrder) ? ($draftOrder->invoice_url ?? '') : ($draftOrder['invoice_url'] ?? '');
                        }
                    }
                }
            }

            if ($needsNewDraftOrder) {
                // Create Draft Order using Shopify REST API via Osiset/Laravel-Shopify
                $draftOrderData = [
                    'draft_order' => [
                        'line_items' => [
                            [
                                'title' => 'Remaining Balance - ' . $booking->product_title,
                                'price' => number_format($booking->remaining_balance, 2, '.', ''),
                                'quantity' => 1,
                                'requires_shipping' => true,
                            ]
                        ],
                        'customer' => [
                            'email' => $booking->email,
                            'first_name' => $booking->customer_name ?? 'Valued',
                            'last_name' => 'Customer'
                        ],
                        'use_customer_default_address' => true,
                        'note_attributes' => [
                            [
                                'name' => 'buylater_token',
                                'value' => $booking->token
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
                    $draftOrderId = is_object($draftOrder) ? $draftOrder->id : $draftOrder['id'];
                    $checkoutUrl = is_object($draftOrder) ? $draftOrder->invoice_url : $draftOrder['invoice_url'];

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
}
