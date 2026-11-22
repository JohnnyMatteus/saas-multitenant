<?php

use App\Modules\Portability\Http\Controllers\StudioExportController;
use Illuminate\Support\Facades\Route;

Route::get('/studio/export', StudioExportController::class)->middleware('throttle:studio-export')->name('studio.export');
