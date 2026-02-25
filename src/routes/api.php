<?php

use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\ImportController;
use Illuminate\Support\Facades\Route;


Route::post('/import', [ImportController::class, 'store']);
Route::get('/customers', [CustomerController::class, 'index']);
