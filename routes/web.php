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
Route::get('/about',    [WizardController::class, 'about']);
Route::view('/generate', 'generate');
Route::view('/validate', 'validate');
Route::view('/export', 'export');
Route::get('/wizard',   [WizardController::class, 'wizard']);
Route::get('/wizard/step/{step}', [WizardController::class, 'step'])
    ->where('step', 'intake|domain|scope|architecture|clarify');