<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TalentProfileController;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use App\Http\Controllers\TalentEmbeddingController;
use App\Http\Controllers\SemanticSearchController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(20); // 10 requests per minute
});

Route::middleware(['apikey'])->prefix('talent')->group(function () {
    // Route::post('/ingest', [TalentProfileController::class, 'store']); // Ingest a new talent profile
    Route::post('/ingest', [TalentProfileController::class, 'ingestPortfolio']); // Ingest a new talent profile
    Route::get('/{id}', [TalentProfileController::class, 'show']); // Retrieve a talent profile by ID
    Route::put('/{id}', [TalentProfileController::class, 'update']); // Update name and description of an existing talent profile 
    Route::delete('/{id}', [TalentProfileController::class, 'destroy']); // Delete a talent profile by ID
    Route::get('/semantic-search', [SemanticSearchController::class, 'search']);
});
