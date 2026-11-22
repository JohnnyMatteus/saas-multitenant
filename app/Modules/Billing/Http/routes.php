<?php

use App\Modules\Billing\Http\Controllers\LessonPackageController;
use App\Modules\Billing\Http\Controllers\PackageOrderController;
use Illuminate\Support\Facades\Route;

Route::get('/lesson-packages', [LessonPackageController::class, 'index'])->name('lesson-packages.index');
Route::post('/lesson-packages', [LessonPackageController::class, 'store'])->name('lesson-packages.store');
Route::patch('/lesson-packages/{package}', [LessonPackageController::class, 'update'])->name('lesson-packages.update');

Route::get('/package-orders', [PackageOrderController::class, 'index'])->name('package-orders.index');
Route::post('/package-orders', [PackageOrderController::class, 'store'])->name('package-orders.store');
Route::get('/me/package-orders', [PackageOrderController::class, 'mine'])->name('me.package-orders');
