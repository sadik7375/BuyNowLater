<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

use App\Http\Controllers\AppProxyController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Log;

Log::info('Web Route Match:', [
    'url' => request()->fullUrl(),
    'method' => request()->method(),
]);


// Embedded App Dashboard Routes (Admin Area)
Route::group(['middleware' => ['verify.shopify']], function () {
    Route::match(['get', 'post'], '/', [DashboardController::class, 'index'])->name('home');
    Route::get('/bookings', [DashboardController::class, 'index'])->name('admin.bookings');
    Route::get('/reminders', [DashboardController::class, 'index'])->name('admin.reminders');
    Route::get('/price-alerts', [DashboardController::class, 'index'])->name('admin.price-alerts');
    Route::get('/app-settings', [DashboardController::class, 'index'])->name('admin.settings');
    Route::get('/support', [DashboardController::class, 'index'])->name('admin.support');
    Route::get('/how-it-works', [DashboardController::class, 'index'])->name('admin.how-it-works');
    Route::get('/benefits', [DashboardController::class, 'index'])->name('admin.benefits');
    Route::get('/price-plan', [DashboardController::class, 'index'])->name('admin.price-plan');

    Route::get('/admin/products/search', [DashboardController::class, 'searchProducts'])->name('products.search');
    Route::post('/admin/settings', [DashboardController::class, 'saveSettings'])->name('settings.save');
    Route::post('/admin/feedback', [DashboardController::class, 'submitFeedback'])->name('feedback.submit');
    Route::post('/admin/bookings/{id}/send-balance-link', [DashboardController::class, 'sendBalanceLink'])->name('bookings.send_balance_link');
    Route::post('/admin/bookings/{id}/send-reminder', [DashboardController::class, 'sendReminder'])->name('bookings.send_reminder');
    Route::post('/admin/downgrade', [DashboardController::class, 'downgradePlan'])->name('plan.downgrade');
    Route::post('/admin/selling-plans/setup', [\App\Http\Controllers\SellingPlanController::class, 'setup'])->name('selling_plans.setup');
    Route::post('/admin/selling-plans/destroy', [\App\Http\Controllers\SellingPlanController::class, 'destroy'])->name('selling_plans.destroy');

    // Fallbacks to handle GET redirects caused by Shopify App Bridge re-auth redirection on POST routes
    Route::get('/admin/downgrade', function () {
        return redirect()->to(route('home', request()->query()));
    });
    Route::get('/admin/settings', function () {
        return redirect()->to(route('home', request()->query()) . '#settings');
    });
    Route::get('/admin/bookings/{id}/send-balance-link', function () {
        return redirect()->to(route('home', request()->query()) . '#bookings');
    });
    Route::get('/admin/bookings/{id}/send-reminder', function () {
        return redirect()->to(route('home', request()->query()) . '#bookings');
    });
});

// Shopify App Proxy Routes (Signed requests from storefront)
Route::group(['middleware' => ['shopify.classify', 'auth.proxy']], function () {
    Route::post('/apps/buylater-proxy/reminders', [AppProxyController::class, 'storeReminder']);
    Route::post('/apps/buylater-proxy/discounts/subscribe', [AppProxyController::class, 'subscribePriceDrop']);
    Route::post('/apps/buylater-proxy/bookings', [AppProxyController::class, 'storeBooking']);

    // Alternate routes in case Shopify strips the /apps/buylater-proxy prefix
    Route::post('/reminders', [AppProxyController::class, 'storeReminder']);
    Route::post('/discounts/subscribe', [AppProxyController::class, 'subscribePriceDrop']);
    Route::post('/bookings', [AppProxyController::class, 'storeBooking']);
});

// Public App Proxy Settings & Bookings Routes (Storefront accesses these to fetch display settings and customer bookings dynamically)
Route::group(['middleware' => ['shopify.classify']], function () {
    Route::get('/apps/buylater-proxy/settings', [AppProxyController::class, 'getSettings']);
    Route::get('/settings', [AppProxyController::class, 'getSettings']);
    Route::get('/apps/buylater-proxy/customer-bookings', [AppProxyController::class, 'getCustomerBookings']);
    Route::get('/customer-bookings', [AppProxyController::class, 'getCustomerBookings']);
});

// Public Customer Actions (Clicked from emails, no shop login required)
Route::get('/reminders/{token}/cancel', [AppProxyController::class, 'cancelReminder'])->name('reminders.cancel');
Route::get('/reminders/{token}/reschedule', [AppProxyController::class, 'showRescheduleForm'])->name('reminders.reschedule.form');
Route::post('/reminders/{token}/reschedule', [AppProxyController::class, 'rescheduleReminder'])->name('reminders.reschedule');

// Alternative URL structures (used in some email templates)
Route::get('/reminder/cancel/{token}', [AppProxyController::class, 'cancelReminder'])->name('reminders.cancel.alt');
Route::get('/reminder/reschedule/{token}', [AppProxyController::class, 'showRescheduleForm'])->name('reminders.reschedule.form.alt');
Route::post('/reminder/reschedule/{token}', [AppProxyController::class, 'rescheduleReminder'])->name('reminders.reschedule.alt');

Route::get('/status-settings-db', function() {
    try {
        $settings = \App\Models\Setting::all()->map(function($s) {
            return [
                'id' => $s->id,
                'shop_id' => $s->shop_id,
                'shop_name' => $s->shop ? $s->shop->name : 'N/A',
                'deposit_percentage' => $s->deposit_percentage,
                'hold_duration_days' => $s->hold_duration_days,
                'product_targeting_type' => $s->product_targeting_type,
                'targeted_product_ids' => $s->targeted_product_ids,
            ];
        });
        return response()->json($settings);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()]);
    }
});

Route::get('/status-bookings-db', function() {
    try {
        $bookings = \App\Models\Booking::orderBy('created_at', 'desc')->get()->map(function($b) {
            return [
                'id' => $b->id,
                'email' => $b->email,
                'product_title' => $b->product_title,
                'product_price' => $b->product_price,
                'deposit_amount' => $b->deposit_amount,
                'remaining_balance' => $b->remaining_balance,
                'status' => $b->status,
                'token' => $b->token,
                'draft_order_id' => $b->draft_order_id,
                'created_at' => $b->created_at ? $b->created_at->toDateTimeString() : 'N/A',
            ];
        });
        return response()->json($bookings);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()]);
    }
});

// Deployment Helpers (For hosting environments without SSH/Terminal access)
Route::group(['prefix' => 'deploy'], function() {

    Route::get('/migrate', function() {
        try {
            \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
            return 'Migration Success: <br><pre>' . \Illuminate\Support\Facades\Artisan::output() . '</pre>';
        } catch (\Exception $e) {
            return 'Migration Failed: ' . $e->getMessage();
        }
    });

    Route::get('/logs', function() {
        try {
            $logPath = storage_path('logs/laravel.log');
            if (!file_exists($logPath)) {
                return 'No log file found.';
            }
            $lines = 150;
            $data = file($logPath);
            $slice = array_slice($data, -$lines);
            return '<pre>' . implode("", $slice) . '</pre>';
        } catch (\Exception $e) {
            return 'Error reading logs: ' . $e->getMessage();
        }
    });

    Route::get('/backfill-history', function() {
        try {
            $shop = \App\Models\User::where('name', 'canny-apps.myshopify.com')->first();
            if (!$shop) {
                $shop = \App\Models\User::first();
            }
            if (!$shop) {
                return 'No shop user found in DB.';
            }

            $bookings = \App\Models\Booking::all();
            $updatedCount = 0;
            $results = [];

            foreach ($bookings as $booking) {
                $updated = false;

                // 1. Sync Deposit Order & Deposit Paid Date
                if ($booking->draft_order_id) {
                    try {
                        $response = $shop->api()->rest(
                            'GET',
                            '/admin/api/' . config('shopify-app.api_version') . '/draft_orders/' . $booking->draft_order_id . '.json'
                        );
                        if (!$response['errors'] && isset($response['body']['draft_order'])) {
                            $do = $response['body']['draft_order'];
                            if (is_object($do) && method_exists($do, 'toArray')) {
                                $do = $do->toArray();
                            } else {
                                $do = json_decode(json_encode($do), true);
                            }

                            if (($do['status'] ?? '') === 'completed') {
                                if (empty($booking->deposit_paid_at)) {
                                    $booking->deposit_paid_at = $do['completed_at'] ?? $booking->updated_at;
                                    $updated = true;
                                }
                                if (empty($booking->order_id) && !empty($do['order_id'])) {
                                    $booking->order_id = $do['order_id'];
                                    $updated = true;
                                }
                            }
                        }
                    } catch (\Exception $ex) {
                        // ignore and continue
                    }
                }

                // If deposit_paid_at is still empty but status is deposit_paid or completed, fallback to created_at/updated_at
                if (in_array($booking->status, ['deposit_paid', 'completed']) && empty($booking->deposit_paid_at)) {
                    $booking->deposit_paid_at = $booking->created_at;
                    $updated = true;
                }

                // 2. Sync Balance Order & Completed Date
                if ($booking->status === 'completed') {
                    if (empty($booking->completed_at)) {
                        $booking->completed_at = $booking->updated_at;
                        $updated = true;
                    }

                    // Try to find the remaining balance draft order from Shopify to get the balance_order_id
                    if (empty($booking->balance_order_id)) {
                        try {
                            $response = $shop->api()->rest(
                                'GET',
                                '/admin/api/' . config('shopify-app.api_version') . '/draft_orders.json',
                                ['status' => 'completed', 'limit' => 50]
                            );
                            if (!$response['errors'] && isset($response['body']['draft_orders'])) {
                                $draftOrders = $response['body']['draft_orders'];
                                if (is_object($draftOrders) && method_exists($draftOrders, 'toArray')) {
                                    $draftOrders = $draftOrders->toArray();
                                } else {
                                    $draftOrders = json_decode(json_encode($draftOrders), true);
                                }

                                foreach ($draftOrders as $do) {
                                    $note = $do['note'] ?? '';
                                    $noteAttrs = $do['note_attributes'] ?? [];
                                    $hasToken = false;

                                    // Match token in note or note_attributes
                                    if (stripos($note, $booking->token) !== false) {
                                        $hasToken = true;
                                    } else {
                                        foreach ($noteAttrs as $attr) {
                                            $val = $attr['value'] ?? '';
                                            if (strtolower($val) === strtolower($booking->token)) {
                                                $hasToken = true;
                                                break;
                                            }
                                        }
                                    }

                                    // Match by customer email and product title if token is not explicit but it's a balance invoice
                                    if (!$hasToken && ($do['customer']['email'] ?? '') === $booking->email) {
                                        if (stripos($note, 'Remaining balance') !== false) {
                                            $hasToken = true; // High probability match
                                        }
                                    }

                                    if ($hasToken && !empty($do['order_id'])) {
                                        $booking->balance_order_id = $do['order_id'];
                                        $booking->completed_at = $do['completed_at'] ?? $booking->updated_at;
                                        $updated = true;
                                        break;
                                    }
                                }
                            }
                        } catch (\Exception $ex) {
                            // ignore and continue
                        }
                    }
                }

                if ($updated) {
                    $booking->save();
                    $updatedCount++;
                    $results[] = "Booking ID {$booking->id} backfilled successfully.";
                }
            }

            return 'Backfill completed. Updated bookings count: ' . $updatedCount . '<br><pre>' . implode("\n", $results) . '</pre>';
        } catch (\Exception $e) {
            return 'Backfill failed: ' . $e->getMessage();
        }
    });

    Route::get('/clear', function() {
        try {
            \Illuminate\Support\Facades\Artisan::call('config:clear');
            \Illuminate\Support\Facades\Artisan::call('route:clear');
            \Illuminate\Support\Facades\Artisan::call('cache:clear');
            \Illuminate\Support\Facades\Artisan::call('view:clear');
            return 'Cache cleared successfully!';
        } catch (\Exception $e) {
            return 'Cache clear failed: ' . $e->getMessage();
        }
    });

    Route::get('/key-generate', function() {
        try {
            \Illuminate\Support\Facades\Artisan::call('key:generate', ['--force' => true]);
            return 'Key generated successfully: <br><pre>' . \Illuminate\Support\Facades\Artisan::output() . '</pre>';
        } catch (\Exception $e) {
            return 'Key generation failed: ' . $e->getMessage();
        }
    });

    Route::get('/run-reminders', function() {
        try {
            \Illuminate\Support\Facades\Artisan::call('app:send-reminders');
            return 'Reminders processed successfully: <br><pre>' . \Illuminate\Support\Facades\Artisan::output() . '</pre>';
        } catch (\Exception $e) {
            return 'Failed to run reminders: ' . $e->getMessage();
        }
    });

    Route::get('/register-webhooks', function() {
        try {
            $shops = \App\Models\User::all();
            $webhooksConfig = config('shopify-app.webhooks');
            $action = app(\Osiset\ShopifyApp\Actions\CreateWebhooks::class);
            $results = [];

            foreach ($shops as $shop) {
                // Ensure the shop model actually has a token/api helper
                if ($shop->password) {
                    $shopId = \Osiset\ShopifyApp\Objects\Values\ShopId::fromNative($shop->id);
                    $res = $action($shopId, $webhooksConfig);
                    $results[$shop->name] = $res;
                }
            }

            return 'Webhooks registration results: <br><pre>' . json_encode($results, JSON_PRETTY_PRINT) . '</pre>';
        } catch (\Exception $e) {
            return 'Webhook registration failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString();
        }
    });

    Route::get('/seed', function() {
        try {
            \Illuminate\Support\Facades\Artisan::call('db:seed', ['--force' => true]);
            return 'Seeding Success: <br><pre>' . \Illuminate\Support\Facades\Artisan::output() . '</pre>';
        } catch (\Exception $e) {
            return 'Seeding Failed: ' . $e->getMessage();
        }
    });

    Route::get('/activate-pro', function() {
        try {
            $shops = \App\Models\User::all();
            if ($shops->isEmpty()) {
                return 'No shops found to activate Pro.';
            }
            
            $activated = [];
            foreach ($shops as $shop) {
                $exists = \DB::table('charges')
                    ->where('user_id', $shop->id)
                    ->where('plan_id', 1)
                    ->exists();
                
                if (!$exists) {
                    \DB::table('charges')->insert([
                        'charge_id' => rand(10000000, 99999999),
                        'type' => 1, // RECURRING
                        'status' => 'ACTIVE',
                        'name' => 'Pro Plan',
                        'price' => 5.00,
                        'interval' => 'EVERY_30_DAYS',
                        'test' => true,
                        'user_id' => $shop->id,
                        'plan_id' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                
                $shop->plan_id = 1;
                $shop->shopify_freemium = 0;
                $shop->save();
                
                $activated[] = $shop->name;
            }
            
            return 'Pro Plan activated successfully for shops: ' . implode(', ', $activated);
        } catch (\Exception $e) {
            return 'Activation failed: ' . $e->getMessage();
        }
    });

    Route::get('/debug-settings', function() {
        try {
            $shops = \App\Models\User::all();
            $settings = \App\Models\Setting::all();
            $out = "Shops:<br>";
            foreach ($shops as $s) {
                $out .= "ID: {$s->id} | Name: {$s->name} | Plan: {$s->plan_id} | Freemium: {$s->shopify_freemium}<br>";
            }
            $out .= "<br>Settings:<br>";
            foreach ($settings as $set) {
                $out .= "ID: {$set->id} | Shop ID: {$set->shop_id} | show_deposit: " . ($set->show_deposit ? '1' : '0') . " | show_reminders: " . ($set->show_reminders ? '1' : '0') . " | show_alerts: " . ($set->show_alerts ? '1' : '0') . "<br>";
            }
            return $out;
        } catch (\Exception $e) {
            return 'Debug failed: ' . $e->getMessage();
        }
    });

    Route::get('/debug-sync-bookings', function() {
        try {
            $shops = \App\Models\User::all();
            $results = [];
            
            foreach ($shops as $shop) {
                $pendingBookings = \App\Models\Booking::where('shop_id', $shop->id)
                    ->where('status', 'pending')
                    ->whereNotNull('draft_order_id')
                    ->get();
                    
                if ($pendingBookings->isEmpty()) {
                    continue;
                }
                
                $draftOrderIds = $pendingBookings->pluck('draft_order_id')->toArray();
                
                $response = $shop->api()->rest(
                    'GET',
                    '/admin/api/' . config('shopify-app.api_version') . '/draft_orders.json',
                    ['ids' => implode(',', $draftOrderIds)]
                );
                
                if ($response['errors']) {
                    $results[$shop->name] = 'API Error: ' . json_encode($response['body']);
                    continue;
                }
                
                $draftOrders = $response['body']['draft_orders'] ?? [];
                $syncCount = 0;
                
                foreach ($draftOrders as $do) {
                    $draftOrderId = $do['id'];
                    $shopifyStatus = $do['status'] ?? '';
                    
                    if ($shopifyStatus === 'completed') {
                        $booking = \App\Models\Booking::where('draft_order_id', $draftOrderId)
                            ->where('status', 'pending')
                            ->first();
                            
                        if ($booking) {
                            $settings = \App\Models\Setting::where('shop_id', $shop->id)->first();
                            $holdDurationDays = $settings ? (int) ($settings->hold_duration_days ?? 14) : 14;
                            
                            $customer = $do['customer'] ?? null;
                            $customerName = null;
                            if ($customer) {
                                $customerName = trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''));
                            }
                            
                            $booking->update([
                                'status' => 'deposit_paid',
                                'customer_name' => $customerName,
                                'expires_at' => now()->addDays($holdDurationDays)
                            ]);
                            $syncCount++;
                        }
                    }
                }
                $results[$shop->name] = "Synced {$syncCount} bookings";
            }
            return 'Sync results: <br><pre>' . json_encode($results, JSON_PRETTY_PRINT) . '</pre>';
        } catch (\Exception $e) {
            return 'Sync failed: ' . $e->getMessage();
        }
    });

    Route::get('/sync/{id}', function($id) {
        try {
            $booking = \App\Models\Booking::findOrFail($id);
            $shop = $booking->shop;
            if (!$shop) {
                return "Shop not found for booking.";
            }
            if (!$booking->draft_order_id) {
                return "No draft order ID for booking.";
            }

            $response = $shop->api()->rest(
                'GET',
                '/admin/api/' . config('shopify-app.api_version') . '/draft_orders/' . $booking->draft_order_id . '.json'
            );

            if ($response['errors']) {
                return 'Shopify API Error: ' . json_encode($response['body']);
            }

            $draftOrder = $response['body']['draft_order'] ?? null;
            if (!$draftOrder) {
                return "Draft order not found on Shopify.";
            }

            // Convert to array
            if (is_object($draftOrder) && method_exists($draftOrder, 'toArray')) {
                $doArray = $draftOrder->toArray();
            } else {
                $doArray = json_decode(json_encode($draftOrder), true);
            }

            $status = $doArray['status'] ?? '';
            $results = [
                'booking_id' => $booking->id,
                'draft_order_id' => $booking->draft_order_id,
                'shopify_status' => $status,
                'shopify_draft_order' => $doArray,
            ];

            if ($status === 'completed') {
                $settings = \App\Models\Setting::where('shop_id', $shop->id)->first();
                $holdDurationDays = $settings ? (int) ($settings->hold_duration_days ?? 14) : 14;
                
                $customer = $doArray['customer'] ?? null;
                $customerName = null;
                if ($customer) {
                    $customerName = trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''));
                }

                $booking->update([
                    'status' => 'deposit_paid',
                    'customer_name' => $customerName,
                    'expires_at' => now()->addDays($holdDurationDays)
                ]);
                $results['sync_action'] = 'Updated booking status to deposit_paid!';
            } else {
                $results['sync_action'] = 'No action taken (status is ' . $status . ')';
            }

            return 'Sync results: <br><pre>' . json_encode($results, JSON_PRETTY_PRINT) . '</pre>';
        } catch (\Exception $e) {
            return 'Sync failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString();
        }
    });

    Route::get('/db-check', function() {
        try {
            $tableName = 'bookings';
            $columns = \DB::select("SHOW COLUMNS FROM {$tableName}");
            
            $out = "Database columns for '{$tableName}':<br><pre>";
            foreach ($columns as $column) {
                $out .= "Field: {$column->Field} | Type: {$column->Type} | Null: {$column->Null} | Key: {$column->Key}<br>";
            }
            $out .= "</pre><br>";
            
            // Check if draft_order_id is int or bigint and needs modification
            $draftOrderIdCol = collect($columns)->firstWhere('Field', 'draft_order_id');
            if ($draftOrderIdCol && (str_contains(strtolower($draftOrderIdCol->Type), 'int') && !str_contains(strtolower($draftOrderIdCol->Type), 'varchar'))) {
                $out .= "Warning: draft_order_id column is currently type: {$draftOrderIdCol->Type}. This will truncate 64-bit Shopify IDs!<br>";
                $out .= "Attempting to ALTER TABLE to modify draft_order_id to VARCHAR(255)...<br>";
                
                \DB::statement("ALTER TABLE bookings MODIFY draft_order_id VARCHAR(255) NULL");
                
                $out .= "Success! Column type altered. Re-checking columns:<br><pre>";
                $columnsUpdated = \DB::select("SHOW COLUMNS FROM {$tableName}");
                foreach ($columnsUpdated as $column) {
                    $out .= "Field: {$column->Field} | Type: {$column->Type}<br>";
                }
                $out .= "</pre>";
            } else {
                $out .= "draft_order_id column type looks correct ({$draftOrderIdCol->Type}). No modification needed.";
            }
            
            return $out;
        } catch (\Exception $e) {
            return 'DB Check failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString();
        }
    });
});



