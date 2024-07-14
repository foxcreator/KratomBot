<?php

use App\Http\Middleware\VerifyApiToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/promocode/check', [\App\Http\Controllers\Api\PostbackController::class, 'check']);
Route::post('/promocode/update-status', [\App\Http\Controllers\Api\PostbackController::class, 'updateStatus']);
