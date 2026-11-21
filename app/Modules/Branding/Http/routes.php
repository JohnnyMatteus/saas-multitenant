<?php

use App\Modules\Branding\Http\Controllers\StudioLogoController;
use Illuminate\Support\Facades\Route;

Route::get('/studio/logo', [StudioLogoController::class, 'show'])->name('studio.logo.show');
Route::post('/studio/logo', [StudioLogoController::class, 'store'])->name('studio.logo.store');
