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
        $setting = Setting::where('shop_id', $shop->id)->first();

        $apiKey = $setting->sendgrid_api_key ?? config('services.sendgrid.api_key');
        $fromEmail = $setting->sendgrid_from_email ?? config('services.sendgrid.from_email');

        $subject = $setting->reminder_email_subject ?? 'Reminder: You wanted to buy this later!';
        $htmlTemplate = $setting->reminder_email_template ?? "
            <h2>Reminder: You wanted to buy {product_title} later!</h2>
            <p>Ready to complete your order? Go here: {product_link}</p>
        ";

        $shopDomain = $shop->name;
        $productLink = "https://{$shopDomain}/products/{$booking->product_handle}";

        $replacements = [
            '{product_title}' => htmlspecialchars($booking->product_title),
            '{product_price}' => htmlspecialchars($booking->product_price),
            '{product_link}' => $productLink,
        ];
        $htmlContent = strtr($htmlTemplate, $replacements);

        $success = \App\Services\SendGridService::send($apiKey, $fromEmail, $booking->email, $subject, $htmlContent);

        if ($success) {
            return back()->with('success', 'Reminder email sent to ' . $booking->email);
        }

        return back()->with('error', 'Failed to dispatch email via SendGrid.');
    }

    /**
     * Send remaining balance link via Draft Order creation.
     */
    public function sendBalanceLink($id)
    {
        $shop = auth()->user();
        $booking = Booking::where('shop_id', $shop->id)->findOrFail($id);

        try {
            // If draft_order_id already exists on booking, we can reuse it or fetch invoice url
            if ($booking->draft_order_id) {
                // Return success immediately or fetch from Shopify
                return back()->with('success', 'Remaining balance draft order already created: ID ' . $booking->draft_order_id);
            }

            // Create Draft Order using Shopify REST or GraphQL API via Osiset/Laravel-Shopify
            $draftOrderData = [
                'draft_order' => [
                    'line_items' => [
                        [
                            'title' => 'Remaining Balance - ' . $booking->product_title,
                            'price' => $booking->remaining_balance,
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

            if ($draftOrder && isset($draftOrder['invoice_url'])) {
                $booking->update([
                    'draft_order_id' => $draftOrder['id'],
                    'checkout_url' => $draftOrder['invoice_url']
                ]);

                // Also send email with invoice URL to the customer
                $setting = Setting::where('shop_id', $shop->id)->first();
                $apiKey = $setting->sendgrid_api_key ?? config('services.sendgrid.api_key');
                $fromEmail = $setting->sendgrid_from_email ?? config('services.sendgrid.from_email');

                $subject = "Complete Your Booking - Remaining Balance for " . $booking->product_title;
                $htmlContent = "
                    <h2>Complete Your Purchase</h2>
                    <p>Hi " . htmlspecialchars($booking->customer_name ?? 'there') . ",</p>
                    <p>Thank you for your deposit payment of $" . number_format($booking->deposit_amount, 2) . ".</p>
                    <p>To pay your remaining balance of <strong>$" . number_format($booking->remaining_balance, 2) . "</strong> and receive your items, please click the checkout link below:</p>
                    <p><a href='" . $draftOrder['invoice_url'] . "' style='display:inline-block;padding:12px 24px;background:#008060;color:#fff;text-decoration:none;border-radius:4px;font-weight:bold;'>Complete Payment</a></p>
                    <p>Thank you!</p>
                ";
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
