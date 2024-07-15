<?php

use App\Http\Controllers\TelegramController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('admin.dashboard');
});

Route::get('/setwebhook', [TelegramController::class, 'setWebhook']);
Route::post('/telegram/webhook', [TelegramController::class, 'webhook'])->withoutMiddleware(['csrf']);

Route::prefix('/admin')->name('admin.')->middleware(['auth'])->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/members', [\App\Http\Controllers\HomeController::class, 'members'])->name('members');
    Route::get('/promocodes', [\App\Http\Controllers\HomeController::class, 'promocodes'])->name('promocodes');
    Route::get('/promocodes/stats', [\App\Http\Controllers\HomeController::class, 'getStatistics'])->name('promocodes.statistics');
    Route::get('/settings', [\App\Http\Controllers\Admin\SettingsController::class, 'index'])->name('settings');
    Route::post('/settings/store', [\App\Http\Controllers\Admin\SettingsController::class, 'store'])->name('settings.store');
    Route::post('/settings/delete-channel', [\App\Http\Controllers\Admin\SettingsController::class, 'deleteChannel'])->name('settings.delete.channel');
    Route::get('/settings/tokens', [\App\Http\Controllers\Admin\SettingsController::class, 'tokens'])->name('settings.tokens');
    Route::post('/settings/tokens/generate', [\App\Http\Controllers\Admin\SettingsController::class, 'saveShopToken'])->name('settings.tokens.generate');
});

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
