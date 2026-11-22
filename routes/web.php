<?php

use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::get('locale/{locale}', LanguageController::class)->name('locale.switch');
Route::get('impersonate/leave', [ImpersonationController::class, 'leave'])->middleware('auth')->name('impersonate.leave');
Route::get('impersonate/{user}', [ImpersonationController::class, 'start'])->middleware('auth')->name('impersonate.start');
Route::middleware('auth')->group(function (): void {
    Route::get('receipts/{sale}', [ReceiptController::class, 'show'])->name('receipts.show');
    Route::get('receipts/{sale}/pdf', [ReceiptController::class, 'pdf'])->name('receipts.pdf');
});
Route::post('webhooks/stripe', [StripeWebhookController::class, 'handle'])->name('webhooks.stripe');
