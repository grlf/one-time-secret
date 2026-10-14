<?php

use App\Http\Controllers\Secret;
use Illuminate\Support\Facades\Route;

Route::get('/', [Secret::class, 'create']);

Route::post('/save', [Secret::class, 'store'])->middleware('throttle:secret-store');
Route::post('/get', [Secret::class, 'get'])->middleware('throttle:secret-reveal');

// Matches any secret id, so new GET routes must be declared above it.
Route::get('/{id}', [Secret::class, 'show'])->where('id', Secret::ID_PATTERN);
