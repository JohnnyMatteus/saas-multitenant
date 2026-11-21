<?php

use App\Modules\Identity\Http\Controllers\MeController;
use App\Modules\Identity\Http\Controllers\MeWhatsAppLinkController;
use Illuminate\Support\Facades\Route;

Route::get('/me', MeController::class)->name('me');

Route::prefix('/me/whatsapp-link')->name('me.whatsapp-link.')->group(function (): void {
    Route::post('/code', [MeWhatsAppLinkController::class, 'issueCode'])->name('code');
    Route::get('/', [MeWhatsAppLinkController::class, 'show'])->name('show');
    Route::delete('/', [MeWhatsAppLinkController::class, 'destroy'])->name('destroy');
});
