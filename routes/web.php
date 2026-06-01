<?php

use App\Http\Controllers\PushSubscriptionController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

// Web push subscriptions (Sprint 5 / SP-29). Auth vereist; web-middleware levert CSRF.
Route::middleware('auth')->group(function () {
    Route::post('push-subscriptions', [PushSubscriptionController::class, 'store'])->name('push-subscriptions.store');
    Route::delete('push-subscriptions', [PushSubscriptionController::class, 'destroy'])->name('push-subscriptions.destroy');
    Route::post('push-subscriptions/test', [PushSubscriptionController::class, 'test'])->name('push-subscriptions.test');
});

require __DIR__.'/settings.php';
