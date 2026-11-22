<?php

use App\Http\Controllers\LanguageController;
use App\Http\Controllers\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::get('locale/{locale}', LanguageController::class)->name('locale.switch');
Route::post('webhooks/stripe', [StripeWebhookController::class, 'handle'])->name('webhooks.stripe');
