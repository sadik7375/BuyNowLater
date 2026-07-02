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
