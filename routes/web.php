<?php

use App\Http\Controllers\TelegramController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', [\App\Http\Controllers\FrontController::class, 'index'])->name('front');
// Route::get('/api-docs', [\App\Http\Controllers\Api\PostbackController::class, 'apiDocs']);
Route::post('/telegram/webhook', [TelegramController::class, 'webhook'])->withoutMiddleware(['csrf']);

Route::prefix('/admin')->name('admin.')->middleware(['auth'])->group(function () {
    Route::get('/setwebhook', [TelegramController::class, 'setWebhook']);

    Route::get('/dashboard', [\App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/members', [\App\Http\Controllers\HomeController::class, 'members'])->name('members');
    Route::get('/promocodes', [\App\Http\Controllers\HomeController::class, 'promocodes'])->name('promocodes');
    Route::get('/promocodes/stats', [\App\Http\Controllers\HomeController::class, 'getStatistics'])->name('promocodes.statistics');
    Route::get('/settings', [\App\Http\Controllers\Admin\SettingsController::class, 'index'])->name('settings');
    Route::post('/settings/store', [\App\Http\Controllers\Admin\SettingsController::class, 'store'])->name('settings.store');
    Route::post('/settings/delete-channel', [\App\Http\Controllers\Admin\SettingsController::class, 'deleteChannel'])->name('settings.delete.channel');
    Route::get('/settings/tokens', [\App\Http\Controllers\Admin\SettingsController::class, 'tokens'])->name('settings.tokens');
    Route::post('/settings/tokens/generate', [\App\Http\Controllers\Admin\SettingsController::class, 'saveShopToken'])->name('settings.tokens.generate');
    Route::resource('brands', App\Http\Controllers\Admin\BrandController::class)->except(['show']);
    Route::resource('products', App\Http\Controllers\Admin\ProductController::class)->except(['show']);
    Route::get('/orders', [App\Http\Controllers\Admin\OrderController::class, 'index'])->name('orders.index');
    Route::post('/orders/change-status/{id}', [App\Http\Controllers\Admin\OrderController::class, 'changeStatus'])->name('orders.change-status');
    Route::post('/members/{member}/send-message', [\App\Http\Controllers\Admin\MemberController::class, 'sendMessage'])->name('members.sendMessage');
});

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
