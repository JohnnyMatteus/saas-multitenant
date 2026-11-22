<?php

use App\Modules\Scheduling\Http\Controllers\BookingController;
use App\Modules\Scheduling\Http\Controllers\ClassSeriesController;
use App\Modules\Scheduling\Http\Controllers\SessionBookingController;
use App\Modules\Scheduling\Http\Controllers\SessionController;
use App\Modules\Scheduling\Http\Controllers\SessionManagementController;
use Illuminate\Support\Facades\Route;

Route::post('/class-series', [ClassSeriesController::class, 'store'])->name('class-series.store');

Route::get('/sessions', [SessionController::class, 'index'])->name('sessions.index');
Route::get('/sessions/{session}', [SessionController::class, 'show'])->name('sessions.show');
Route::patch('/sessions/{session}', [SessionManagementController::class, 'update'])->name('sessions.update');
Route::post('/sessions/{session}/cancel', [SessionManagementController::class, 'cancel'])->name('sessions.cancel');
Route::get('/sessions/{session}/bookings', [SessionBookingController::class, 'index'])->name('sessions.bookings.index');
Route::post('/sessions/{session}/bookings', [SessionBookingController::class, 'store'])->name('sessions.bookings.store');

Route::get('/me/bookings', [BookingController::class, 'mine'])->name('me.bookings');
Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');
