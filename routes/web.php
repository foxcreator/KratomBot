<?php

use App\Http\Controllers\TelegramController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', [\App\Http\Controllers\FrontController::class, 'index'])->name('front');
// Route::get('/api-docs', [\App\Http\Controllers\Api\PostbackController::class, 'apiDocs']);
Route::post('/telegram/webhook', [TelegramController::class, 'webhook'])->withoutMiddleware(['csrf']);

Route::prefix('/old-admin')->name('admin.')->middleware(['auth'])->group(function () {
    Route::get('/setwebhook', [TelegramController::class, 'setWebhook']);

    Route::get('/members', [\App\Http\Controllers\HomeController::class, 'members'])->name('members');
    Route::post('/members/{member}/send-message', [\App\Http\Controllers\Admin\MemberController::class, 'sendMessage'])->name('members.sendMessage');

    Route::middleware('isAdmin')->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');
        Route::resource('/users', \App\Http\Controllers\Admin\UserController::class);
        Route::get('/reports', [\App\Http\Controllers\Admin\ReportController::class, 'index'])->name('reports.index');
        Route::get('/settings', [\App\Http\Controllers\Admin\SettingsController::class, 'index'])->name('settings');
        Route::post('/settings/store', [\App\Http\Controllers\Admin\SettingsController::class, 'store'])->name('settings.store');
        Route::post('/settings/delete-channel', [\App\Http\Controllers\Admin\SettingsController::class, 'deleteChannel'])->name('settings.delete.channel');
        Route::get('/settings/tokens', [\App\Http\Controllers\Admin\SettingsController::class, 'tokens'])->name('settings.tokens');
        Route::post('/settings/tokens/generate', [\App\Http\Controllers\Admin\SettingsController::class, 'saveShopToken'])->name('settings.tokens.generate');
        Route::resource('brands', App\Http\Controllers\Admin\BrandController::class)->except(['show']);
        Route::resource('products', App\Http\Controllers\Admin\ProductController::class)->except(['show']);
        Route::resource('subcategories', App\Http\Controllers\Admin\SubcategoryController::class)->except(['show']);
    });


    Route::get('/orders', [App\Http\Controllers\Admin\OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/create', [App\Http\Controllers\Admin\OrderController::class, 'create'])->name('orders.create');
    Route::post('/orders/store', [App\Http\Controllers\Admin\OrderController::class, 'store'])->name('orders.store');
    Route::get('/orders/{id}', [App\Http\Controllers\Admin\OrderController::class, 'show'])->name('orders.show');
    Route::patch('orders/{order}/status', [App\Http\Controllers\Admin\OrderController::class, 'updateStatus'])->name('orders.update-status');
    Route::patch('orders/{order}/notes', [App\Http\Controllers\Admin\OrderController::class, 'updateNotes'])->name('orders.update-notes');
    Route::post('/orders/change-status/{id}', [App\Http\Controllers\Admin\OrderController::class, 'changeStatus'])->name('orders.change-status');
});

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
