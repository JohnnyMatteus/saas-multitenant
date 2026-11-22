<?php

use App\Modules\Branding\Http\Controllers\StudioLogoController;
use App\Modules\Branding\Http\Controllers\StudioSettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/studio', [StudioSettingsController::class, 'show'])->name('studio.show');
Route::patch('/studio', [StudioSettingsController::class, 'update'])->name('studio.update');
Route::get('/studio/logo', [StudioLogoController::class, 'show'])->name('studio.logo.show');
Route::post('/studio/logo', [StudioLogoController::class, 'store'])->name('studio.logo.store');
