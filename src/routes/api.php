<?php

use App\Http\Controllers\Api\ImportController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Route::get('/health', fn () => response()->json(['ok' => true]));
Route::post('/import', [ImportController::class, 'store']);
