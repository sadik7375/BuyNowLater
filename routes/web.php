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
    Route::get('/', [DashboardController::class, 'index'])->name('home');
    Route::post('/admin/settings', [DashboardController::class, 'saveSettings'])->name('settings.save');
    Route::post('/admin/bookings/{id}/send-balance-link', [DashboardController::class, 'sendBalanceLink'])->name('bookings.send_balance_link');
    Route::post('/admin/bookings/{id}/send-reminder', [DashboardController::class, 'sendReminder'])->name('bookings.send_reminder');
    Route::post('/admin/downgrade', [DashboardController::class, 'downgradePlan'])->name('plan.downgrade');

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
});


