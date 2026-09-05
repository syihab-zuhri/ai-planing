<?php

use App\Http\Controllers\WizardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes (Phase 1)
|--------------------------------------------------------------------------
|
| Halaman UI wizard. API/JSON tetap di routes/api.php.
| Semua POST form akan otomatis kena CSRF middleware (Laravel 11 default
| aktif untuk web middleware group, lihat bootstrap/app.php).
|
*/

Route::get('/',         [WizardController::class, 'landing']);
Route::post('/wizard/start', [WizardController::class, 'startFromWeb']);
Route::get('/about',    [WizardController::class, 'about']);
Route::get('/archive',  [\App\Http\Controllers\ProjectArchiveController::class, 'index']);
Route::get('/archive/download/{id}', [\App\Http\Controllers\ProjectArchiveController::class, 'downloadDirect']);
Route::view('/generate', 'generate');
Route::view('/validate', 'validate');
Route::view('/export', 'export');
Route::get('/wizard',   [WizardController::class, 'wizard']);
Route::get('/wizard/step/{step}', [WizardController::class, 'step'])
    ->where('step', 'intake|domain|scope|architecture|clarify');