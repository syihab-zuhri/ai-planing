<?php

use App\Http\Controllers\ClarifyController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\GenerateController;
use App\Http\Controllers\ValidateController;
use App\Http\Controllers\WizardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Wizard API Routes (Phase 1)
|--------------------------------------------------------------------------
|
| Acuan: API.md §3 (Endpoint Inventory) + SECURITY.md §10 (rate limit).
| Rate limit per-session: wizard 30/min, generate 10/min, export 5/min.
|
*/

// ---------------------------------------------------------------------------
// /api/wizard/* — phase awal, intake sampai klarifikasi
// ---------------------------------------------------------------------------
Route::middleware('throttle.session:wizard')->prefix('wizard')->group(function () {
    Route::post('/start',           [WizardController::class, 'start']);
    Route::get('/state',            [WizardController::class, 'state']);
    Route::post('/intake',          [WizardController::class, 'intake']);
    Route::post('/domain',          [WizardController::class, 'domain']);
    Route::post('/scope',           [WizardController::class, 'scope']);
    Route::post('/architecture',    [WizardController::class, 'architecture']);

    Route::prefix('clarify')->group(function () {
        Route::post('/questions', [ClarifyController::class, 'questions']);
        Route::post('/answers',   [ClarifyController::class, 'answers']);
    });
});

// ---------------------------------------------------------------------------
// /api/generate/* — pipeline generate dokumen (mock AI di Phase 1)
// ---------------------------------------------------------------------------
Route::middleware('throttle.session:generate')->prefix('generate')->group(function () {
    Route::post('/start',         [GenerateController::class, 'start']);
    Route::post('/retry/{doc_id}',[GenerateController::class, 'retry'])->where('doc_id', '.*');
    Route::post('/cancel',        [GenerateController::class, 'cancel']);
    Route::get('/stream',         [GenerateController::class, 'stream']);
});

// ---------------------------------------------------------------------------
// /api/validate/* — gate validator (no rate limit khusus; pakai wizard budget)
// ---------------------------------------------------------------------------
Route::middleware('throttle.session:wizard')->prefix('validate')->group(function () {
    Route::post('/run',      [ValidateController::class, 'run']);
    Route::get('/status',    [ValidateController::class, 'status']);
    Route::post('/override', [ValidateController::class, 'override']);
});

// ---------------------------------------------------------------------------
// /api/export/* — trigger ZIP + download via signed token
// ---------------------------------------------------------------------------
Route::middleware('throttle.session:export')->prefix('export')->group(function () {
    Route::post('/start',                       [ExportController::class, 'start']);
    Route::get('/download/{token}',             [ExportController::class, 'download']);
});