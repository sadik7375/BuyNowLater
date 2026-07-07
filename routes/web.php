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

    // Fallbacks to handle GET redirects caused by Shopify App Bridge re-auth redirection on POST routes
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
    Route::get('/apps/buylater-proxy/settings', [AppProxyController::class, 'getSettings']);

    // Alternate routes in case Shopify strips the /apps/buylater-proxy prefix
    Route::post('/reminders', [AppProxyController::class, 'storeReminder']);
    Route::post('/discounts/subscribe', [AppProxyController::class, 'subscribePriceDrop']);
    Route::post('/bookings', [AppProxyController::class, 'storeBooking']);
    Route::get('/settings', [AppProxyController::class, 'getSettings']);
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
            \Illuminate\Support\Facades\Artisan::call('key:generate');
            return 'Key generated successfully: <br><pre>' . \Illuminate\Support\Facades\Artisan::output() . '</pre>';
        } catch (\Exception $e) {
            return 'Key generation failed: ' . $e->getMessage();
        }
    });
});

