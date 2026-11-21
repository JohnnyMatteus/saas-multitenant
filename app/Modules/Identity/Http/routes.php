<?php

use App\Modules\Identity\Http\Controllers\MeController;
use Illuminate\Support\Facades\Route;

Route::get('/me', MeController::class)->name('me');
